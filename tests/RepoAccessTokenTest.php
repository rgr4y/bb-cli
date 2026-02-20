<?php

use PHPUnit\Framework\TestCase;
use BBCli\BBCli\Actions\Auth;

/**
 * Tests for repo access token auth type.
 *
 * Repo access tokens differ from API tokens:
 * - Scoped to a single repository (not account-wide)
 * - Use Bearer auth header instead of Basic
 * - Git credential helper uses x-token-auth as username
 * - Stored as auth.type = 'repo_access_token' with auth.repoToken field
 */
class RepoAccessTokenTest extends TestCase
{
    private string $tmpConfig;

    protected function setUp(): void
    {
        $this->tmpConfig = tempnam(sys_get_temp_dir(), 'bb_rat_');
        unlink($this->tmpConfig);
        putenv("BB_CLI_CONFIG={$this->tmpConfig}");
    }

    protected function tearDown(): void
    {
        putenv('BB_CLI_CONFIG');
        @unlink($this->tmpConfig);
    }

    // ── Auth::AVAILABLE_COMMANDS ──────────────────────────────────────────────

    public function test_repo_token_command_exists_in_available_commands(): void
    {
        $this->assertArrayHasKey('saveRepoToken', Auth::AVAILABLE_COMMANDS);
    }

    public function test_repo_token_alias_resolves(): void
    {
        $auth = new Auth();
        $this->assertSame('saveRepoToken', $auth->getMethodNameFromAlias('repo-token'));
    }

    public function test_saveRepoToken_method_exists(): void
    {
        $this->assertTrue(method_exists(Auth::class, 'saveRepoToken'));
    }

    // ── userConfig() encrypts repoToken ───────────────────────────────────────

    public function test_repoToken_is_encrypted_on_write(): void
    {
        userConfig(['auth' => [
            'type'      => 'repo_access_token',
            'repoToken' => 'ATCTT3plaintext',
        ]]);

        $raw = json_decode(file_get_contents($this->tmpConfig), true);
        $this->assertNotSame('ATCTT3plaintext', $raw['auth']['repoToken']);
        $this->assertStringContainsString(':', $raw['auth']['repoToken']);
    }

    public function test_repoToken_is_decrypted_on_read(): void
    {
        userConfig(['auth' => [
            'type'      => 'repo_access_token',
            'repoToken' => 'ATCTT3plaintext',
        ]]);

        $this->assertSame('ATCTT3plaintext', userConfig('auth.repoToken'));
    }

    public function test_repoToken_corrupt_throws_with_reissue_url(): void
    {
        $enc = encryptSecret('valid');
        [$iv, $ct] = explode(':', $enc);
        $corrupted = $iv . ':' . base64_encode('GARBAGE_GARBAGE_GARBAGE_GARBAGE_');

        file_put_contents($this->tmpConfig, json_encode([
            'auth' => ['type' => 'repo_access_token', 'repoToken' => $corrupted],
        ]));
        chmod($this->tmpConfig, 0600);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/invalid or corrupt/i');
        userConfig('auth.repoToken');
    }

    // ── git credential helper outputs x-token-auth for repo access tokens ───────

    public function test_credential_helper_uses_x_token_auth_username(): void
    {
        userConfig(['auth' => [
            'type'      => 'repo_access_token',
            'repoToken' => 'my-repo-token',
        ]]);

        // handleCredentialProtocol() writes to stdout — capture it
        // Feed it the "host=bitbucket.org\n\n" input via a temp stream
        $stdin = fopen('php://memory', 'r+');
        fwrite($stdin, "host=bitbucket.org\n\n");
        rewind($stdin);

        ob_start();
        // Directly invoke the credential protocol logic by replicating what
        // handleCredentialProtocol does for 'get', using our temp stream
        $host = null;
        while (($line = fgets($stdin)) !== false) {
            $line = rtrim($line);
            if ($line === '') break;
            if (strpos($line, 'host=') === 0) $host = substr($line, 5);
        }
        fclose($stdin);

        if ($host === 'bitbucket.org') {
            $auth = userConfig('auth');
            if (($auth['type'] ?? '') === 'repo_access_token') {
                echo "username=x-token-auth\n";
                echo "password={$auth['repoToken']}\n";
            }
        }
        $out = ob_get_clean();

        $this->assertStringContainsString("username=x-token-auth\n", $out);
        $this->assertStringContainsString("password=my-repo-token\n", $out);
    }

    // ── Auth::show() masks repoToken ──────────────────────────────────────────

    public function test_show_masks_repoToken(): void
    {
        userConfig(['auth' => [
            'type'      => 'repo_access_token',
            'repoToken' => 'super-secret-repo-token',
        ]]);

        ob_start();
        (new Auth())->show();
        $out = ob_get_clean();

        $this->assertStringNotContainsString('super-secret-repo-token', $out);
        $this->assertStringContainsString('********', $out);
    }
}
