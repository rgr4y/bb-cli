<?php

use PHPUnit\Framework\TestCase;
use BBCli\BBCli\Actions\Auth;

/**
 * Tests for Auth action — command registration, show() masking,
 * and config state after auth operations.
 */
class AuthActionTest extends TestCase
{
    private string $tmpConfig;

    protected function setUp(): void
    {
        $this->tmpConfig = tempnam(sys_get_temp_dir(), 'bb_auth_');
        unlink($this->tmpConfig);
        putenv("BB_CLI_CONFIG={$this->tmpConfig}");
    }

    protected function tearDown(): void
    {
        putenv('BB_CLI_CONFIG');
        @unlink($this->tmpConfig);
    }

    // ── AVAILABLE_COMMANDS ────────────────────────────────────────────────────

    public function test_token_alias_resolves_to_saveApiToken(): void
    {
        $auth = new Auth();
        $this->assertSame('saveApiToken', $auth->getMethodNameFromAlias('token'));
    }

    public function test_repo_token_alias_resolves_to_saveRepoToken(): void
    {
        $auth = new Auth();
        $this->assertSame('saveRepoToken', $auth->getMethodNameFromAlias('repo-token'));
    }

    public function test_save_alias_resolves_to_saveLoginInfo(): void
    {
        $auth = new Auth();
        $this->assertSame('saveLoginInfo', $auth->getMethodNameFromAlias('save'));
    }

    public function test_show_alias_resolves(): void
    {
        $auth = new Auth();
        $this->assertSame('show', $auth->getMethodNameFromAlias('show'));
    }

    public function test_check_git_folder_is_false(): void
    {
        $this->assertFalse(Auth::CHECK_GIT_FOLDER);
    }

    public function test_default_method_is_saveApiToken(): void
    {
        $this->assertSame('saveApiToken', Auth::DEFAULT_METHOD);
    }

    // ── show() — masking ──────────────────────────────────────────────────────

    public function test_show_masks_apiToken(): void
    {
        userConfig(['auth' => [
            'type'     => 'api_token',
            'email'    => 'user@example.com',
            'apiToken' => 'super-secret-token',
        ]]);

        ob_start();
        (new Auth())->show();
        $out = ob_get_clean();

        $this->assertStringNotContainsString('super-secret-token', $out);
        $this->assertStringContainsString('********', $out);
        $this->assertStringContainsString('user@example.com', $out);
    }

    public function test_show_masks_appPassword(): void
    {
        userConfig(['auth' => [
            'type'        => 'app_password',
            'username'    => 'myuser',
            'appPassword' => 'hunter2',
        ]]);

        ob_start();
        (new Auth())->show();
        $out = ob_get_clean();

        $this->assertStringNotContainsString('hunter2', $out);
        $this->assertStringContainsString('********', $out);
        $this->assertStringContainsString('myuser', $out);
    }

    public function test_show_masks_repoToken(): void
    {
        userConfig(['auth' => [
            'type'      => 'repo_access_token',
            'repoToken' => 'secret-repo-token',
        ]]);

        ob_start();
        (new Auth())->show();
        $out = ob_get_clean();

        $this->assertStringNotContainsString('secret-repo-token', $out);
        $this->assertStringContainsString('********', $out);
    }

    // ── config state after writing auth ───────────────────────────────────────

    public function test_api_token_config_type_is_set(): void
    {
        userConfig(['auth' => [
            'type'     => 'api_token',
            'email'    => 'user@example.com',
            'apiToken' => 'tok123',
        ]]);

        $this->assertSame('api_token', userConfig('auth.type'));
        $this->assertSame('user@example.com', userConfig('auth.email'));
        $this->assertSame('tok123', userConfig('auth.apiToken'));
    }

    public function test_repo_access_token_config_type_is_set(): void
    {
        userConfig(['auth' => [
            'type'      => 'repo_access_token',
            'repoToken' => 'repo-tok',
        ]]);

        $this->assertSame('repo_access_token', userConfig('auth.type'));
        $this->assertSame('repo-tok', userConfig('auth.repoToken'));
    }

    public function test_app_password_config_type_is_set(): void
    {
        userConfig(['auth' => [
            'type'        => 'app_password',
            'username'    => 'bob',
            'appPassword' => 'pass123',
        ]]);

        $this->assertSame('app_password', userConfig('auth.type'));
        $this->assertSame('bob', userConfig('auth.username'));
        $this->assertSame('pass123', userConfig('auth.appPassword'));
    }
}
