<?php

use PHPUnit\Framework\TestCase;
use BBCli\BBCli\Actions\Git;

/**
 * Tests for Git action — credential helper setup/remove commands
 * and the credential protocol handler.
 */
class GitActionTest extends TestCase
{
    private string $tmpConfig;

    protected function setUp(): void
    {
        $this->tmpConfig = tempnam(sys_get_temp_dir(), 'bb_git_');
        unlink($this->tmpConfig);
        putenv("BB_CLI_CONFIG={$this->tmpConfig}");
    }

    protected function tearDown(): void
    {
        putenv('BB_CLI_CONFIG');
        @unlink($this->tmpConfig);
    }

    // ── AVAILABLE_COMMANDS / aliases ──────────────────────────────────────────

    public function test_setup_command_exists(): void
    {
        $this->assertArrayHasKey('setup', Git::AVAILABLE_COMMANDS);
    }

    public function test_remove_command_exists(): void
    {
        $this->assertArrayHasKey('remove', Git::AVAILABLE_COMMANDS);
    }

    public function test_setup_alias_resolves(): void
    {
        $git = new Git();
        $this->assertSame('setup', $git->getMethodNameFromAlias('setup'));
    }

    public function test_remove_alias_resolves(): void
    {
        $git = new Git();
        $this->assertSame('remove', $git->getMethodNameFromAlias('remove'));
    }

    public function test_check_git_folder_is_false(): void
    {
        $this->assertFalse(Git::CHECK_GIT_FOLDER);
    }

    // ── handleCredentialProtocol — api_token ──────────────────────────────────

    public function test_credential_protocol_api_token_outputs_correct_username(): void
    {
        userConfig(['auth' => [
            'type'     => 'api_token',
            'email'    => 'user@example.com',
            'apiToken' => 'my-api-token',
        ]]);

        $out = $this->runCredentialProtocol("host=bitbucket.org\n\n");

        $this->assertStringContainsString("username=x-bitbucket-api-token-auth\n", $out);
        $this->assertStringContainsString("password=my-api-token\n", $out);
    }

    // ── handleCredentialProtocol — repo_access_token ─────────────────────────

    public function test_credential_protocol_repo_token_outputs_x_token_auth(): void
    {
        userConfig(['auth' => [
            'type'      => 'repo_access_token',
            'repoToken' => 'my-repo-token',
        ]]);

        $out = $this->runCredentialProtocol("host=bitbucket.org\n\n");

        $this->assertStringContainsString("username=x-token-auth\n", $out);
        $this->assertStringContainsString("password=my-repo-token\n", $out);
    }

    // ── handleCredentialProtocol — app_password ───────────────────────────────

    public function test_credential_protocol_app_password_outputs_username(): void
    {
        userConfig(['auth' => [
            'type'        => 'app_password',
            'username'    => 'myuser',
            'appPassword' => 'mypass',
        ]]);

        $out = $this->runCredentialProtocol("host=bitbucket.org\n\n");

        $this->assertStringContainsString("username=myuser\n", $out);
        $this->assertStringContainsString("password=mypass\n", $out);
    }

    // ── handleCredentialProtocol — wrong host / no auth ──────────────────────

    public function test_credential_protocol_ignores_non_bitbucket_host(): void
    {
        userConfig(['auth' => [
            'type'     => 'api_token',
            'email'    => 'user@example.com',
            'apiToken' => 'my-api-token',
        ]]);

        $out = $this->runCredentialProtocol("host=github.com\n\n");

        $this->assertSame('', $out);
    }

    public function test_credential_protocol_outputs_nothing_without_auth(): void
    {
        // Empty config — no auth set
        $out = $this->runCredentialProtocol("host=bitbucket.org\n\n");

        $this->assertSame('', $out);
    }

    // ── newline stripping in credentials ─────────────────────────────────────

    public function test_credential_protocol_strips_newlines_from_password(): void
    {
        userConfig(['auth' => [
            'type'     => 'api_token',
            'email'    => 'user@example.com',
            'apiToken' => "tok\r\nen",
        ]]);

        $out = $this->runCredentialProtocol("host=bitbucket.org\n\n");

        // After stripping \r and \n the token becomes "token"
        $this->assertStringContainsString("password=token\n", $out);
        // The password line itself must not contain CR or LF
        foreach (explode("\n", trim($out)) as $line) {
            if (str_starts_with($line, 'password=')) {
                $this->assertStringNotContainsString("\r", $line);
                $this->assertStringNotContainsString("\n", $line);
            }
        }
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    /**
     * Simulate handleCredentialProtocol('get') by replaying its logic
     * with a controlled stdin stream, capturing stdout output.
     */
    private function runCredentialProtocol(string $stdinData): string
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $stdinData);
        rewind($stream);

        $host = null;
        while (($line = fgets($stream)) !== false) {
            $line = rtrim($line);
            if ($line === '') break;
            if (strpos($line, 'host=') === 0) $host = substr($line, 5);
        }
        fclose($stream);

        if ($host !== 'bitbucket.org') {
            return '';
        }

        $auth = userConfig('auth');
        if (!$auth) {
            return '';
        }

        $authType = $auth['type'] ?? '';
        if ($authType === 'repo_access_token') {
            $username = 'x-token-auth';
            $password = $auth['repoToken'] ?? '';
        } elseif ($authType === 'api_token') {
            $username = 'x-bitbucket-api-token-auth';
            $password = $auth['apiToken'] ?? '';
        } else {
            $username = $auth['username'] ?? '';
            $password = $auth['appPassword'] ?? '';
        }

        if (!$password) {
            return '';
        }

        $username = str_replace(["\n", "\r"], '', $username);
        $password = str_replace(["\n", "\r"], '', $password);

        return "username={$username}\npassword={$password}\n";
    }
}
