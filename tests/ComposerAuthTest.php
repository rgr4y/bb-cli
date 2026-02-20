<?php

use PHPUnit\Framework\TestCase;
use BBCli\BBCli\Actions\Auth;

/**
 * Tests for bb auth composer-auth — generates a Composer auth.json
 * for pulling private Bitbucket packages.
 */
class ComposerAuthTest extends TestCase
{
    private string $tmpConfig;
    private string $tmpOutput;

    protected function setUp(): void
    {
        $this->tmpConfig = tempnam(sys_get_temp_dir(), 'bb_ca_');
        unlink($this->tmpConfig);
        putenv("BB_CLI_CONFIG={$this->tmpConfig}");

        $this->tmpOutput = tempnam(sys_get_temp_dir(), 'bb_auth_json_');
        unlink($this->tmpOutput);
    }

    protected function tearDown(): void
    {
        putenv('BB_CLI_CONFIG');
        @unlink($this->tmpConfig);
        @unlink($this->tmpOutput);
    }

    // ── command registration ──────────────────────────────────────────────────

    public function test_composer_auth_command_exists_in_available_commands(): void
    {
        $this->assertArrayHasKey('composerAuth', Auth::AVAILABLE_COMMANDS);
    }

    public function test_composer_auth_alias_resolves(): void
    {
        $auth = new Auth();
        $this->assertSame('composerAuth', $auth->getMethodNameFromAlias('composer-auth'));
    }

    public function test_composer_auth_method_exists(): void
    {
        $this->assertTrue(method_exists(Auth::class, 'composerAuth'));
    }

    // ── generated JSON structure ──────────────────────────────────────────────

    public function test_composer_auth_json_has_correct_username_for_api_token(): void
    {
        userConfig(['auth' => [
            'type'     => 'api_token',
            'email'    => 'user@example.com',
            'apiToken' => 'my-api-token',
        ]]);

        $json = $this->generateComposerAuth();

        $this->assertArrayHasKey('http-basic', $json);
        $this->assertArrayHasKey('bitbucket.org', $json['http-basic']);
        $this->assertSame('x-bitbucket-api-token-auth', $json['http-basic']['bitbucket.org']['username']);
        $this->assertSame('my-api-token', $json['http-basic']['bitbucket.org']['password']);
    }

    public function test_composer_auth_json_has_correct_username_for_app_password(): void
    {
        userConfig(['auth' => [
            'type'        => 'app_password',
            'username'    => 'myuser',
            'appPassword' => 'mypass',
        ]]);

        $json = $this->generateComposerAuth();

        $this->assertSame('myuser', $json['http-basic']['bitbucket.org']['username']);
        $this->assertSame('mypass', $json['http-basic']['bitbucket.org']['password']);
    }

    public function test_composer_auth_json_is_valid_json(): void
    {
        userConfig(['auth' => [
            'type'     => 'api_token',
            'email'    => 'user@example.com',
            'apiToken' => 'my-api-token',
        ]]);

        $raw = $this->generateComposerAuthRaw();
        $this->assertNotNull(json_decode($raw));
        $this->assertSame(JSON_ERROR_NONE, json_last_error());
    }

    public function test_composer_auth_json_is_pretty_printed(): void
    {
        userConfig(['auth' => [
            'type'     => 'api_token',
            'email'    => 'user@example.com',
            'apiToken' => 'my-api-token',
        ]]);

        $raw = $this->generateComposerAuthRaw();
        $this->assertStringContainsString("\n", $raw);
    }

    // ── file output ───────────────────────────────────────────────────────────

    public function test_composer_auth_writes_file_when_path_given(): void
    {
        userConfig(['auth' => [
            'type'     => 'api_token',
            'email'    => 'user@example.com',
            'apiToken' => 'my-api-token',
        ]]);

        $auth = new Auth();
        ob_start();
        $auth->composerAuth($this->tmpOutput);
        ob_end_clean();

        $this->assertFileExists($this->tmpOutput);
        $json = json_decode(file_get_contents($this->tmpOutput), true);
        $this->assertSame('x-bitbucket-api-token-auth', $json['http-basic']['bitbucket.org']['username']);
    }

    public function test_composer_auth_file_has_0600_permissions(): void
    {
        userConfig(['auth' => [
            'type'     => 'api_token',
            'email'    => 'user@example.com',
            'apiToken' => 'my-api-token',
        ]]);

        $auth = new Auth();
        ob_start();
        $auth->composerAuth($this->tmpOutput);
        ob_end_clean();

        $perms = fileperms($this->tmpOutput) & 0777;
        $this->assertSame(0600, $perms);
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function generateComposerAuthRaw(): string
    {
        $auth = new Auth();
        ob_start();
        $auth->composerAuth();
        return ob_get_clean();
    }

    private function generateComposerAuth(): array
    {
        // Strip any non-JSON output lines (status messages), find the JSON block
        $raw = $this->generateComposerAuthRaw();
        // Strip ANSI codes
        $raw = preg_replace('/\x1b\[[0-9;]*m/', '', $raw);
        // Find the JSON object in output
        if (preg_match('/(\{.*\})/s', $raw, $m)) {
            return json_decode($m[1], true) ?? [];
        }
        return [];
    }
}
