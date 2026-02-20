<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests for config() and userConfig() using real files — no stubs.
 */
class ConfigTest extends TestCase
{
    private string $tmpConfig;

    protected function setUp(): void
    {
        // Point userConfigFilePath at a temp file so we never touch ~/.local/share/bb-cli
        $this->tmpConfig = tempnam(sys_get_temp_dir(), 'bb_test_');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpConfig)) {
            unlink($this->tmpConfig);
        }
    }

    // ── config() ─────────────────────────────────────────────────────────────

    public function test_config_returns_userConfigFilePath(): void
    {
        $path = config('userConfigFilePath');
        $this->assertIsString($path);
        $this->assertStringEndsWith('config.json', $path);
    }

    public function test_config_returns_default_for_missing_key(): void
    {
        $this->assertSame('fallback', config('nonexistent.key', 'fallback'));
    }

    public function test_config_returns_null_default_for_missing_key(): void
    {
        $this->assertNull(config('nonexistent.key'));
    }

    // ── userConfig() read ─────────────────────────────────────────────────────

    private function writeConfig(array $data): void
    {
        file_put_contents($this->tmpConfig, json_encode($data, JSON_PRETTY_PRINT));
    }

    private function callUserConfig(mixed $key, mixed $default = null): mixed
    {
        putenv("BB_CLI_CONFIG={$this->tmpConfig}");
        try {
            return userConfig($key, $default);
        } finally {
            putenv('BB_CLI_CONFIG');
        }
    }

    public function test_userConfig_reads_top_level_key(): void
    {
        $this->writeConfig(['auth' => ['type' => 'api_token', 'email' => 'test@example.com']]);
        $auth = $this->callUserConfig('auth');
        $this->assertIsArray($auth);
        $this->assertSame('api_token', $auth['type']);
    }

    public function test_userConfig_reads_dot_notation_key(): void
    {
        $this->writeConfig(['auth' => ['type' => 'api_token', 'email' => 'test@example.com']]);
        $this->assertSame('api_token', $this->callUserConfig('auth.type'));
    }

    public function test_userConfig_returns_default_for_missing_key(): void
    {
        $this->writeConfig(['auth' => ['type' => 'api_token']]);
        $this->assertSame('default_val', $this->callUserConfig('auth.nonexistent', 'default_val'));
    }

    public function test_userConfig_returns_null_for_missing_key_no_default(): void
    {
        $this->writeConfig(['auth' => ['type' => 'api_token']]);
        $this->assertNull($this->callUserConfig('auth.nonexistent'));
    }

    public function test_userConfig_null_key_returns_full_config(): void
    {
        $data = ['auth' => ['type' => 'api_token', 'email' => 'test@example.com']];
        $this->writeConfig($data);
        $result = $this->callUserConfig(null);
        $this->assertSame($data, $result);
    }

    public function test_userConfig_creates_file_if_missing(): void
    {
        // tmpConfig is created by tempnam — delete it so userConfig sees a missing file
        unlink($this->tmpConfig);
        putenv("BB_CLI_CONFIG={$this->tmpConfig}");
        try {
            $result = userConfig('auth');
            $this->assertNull($result);
            $this->assertFileExists($this->tmpConfig);
        } finally {
            putenv('BB_CLI_CONFIG');
        }
    }

    public function test_userConfig_file_has_restricted_permissions(): void
    {
        unlink($this->tmpConfig);
        putenv("BB_CLI_CONFIG={$this->tmpConfig}");
        try {
            userConfig('auth'); // triggers file creation
            $perms = fileperms($this->tmpConfig) & 0777;
            $this->assertSame(0600, $perms, sprintf('Expected 0600, got %04o', $perms));
        } finally {
            putenv('BB_CLI_CONFIG');
        }
    }

    // ── userConfig() write ────────────────────────────────────────────────────

    public function test_userConfig_array_key_writes_to_file(): void
    {
        $this->writeConfig(['auth' => ['type' => 'api_token']]);
        putenv("BB_CLI_CONFIG={$this->tmpConfig}");
        try {
            userConfig(['auth' => ['type' => 'app_password', 'username' => 'rob']]);
            $written = json_decode(file_get_contents($this->tmpConfig), true);
            $this->assertSame('app_password', $written['auth']['type']);
            $this->assertSame('rob', $written['auth']['username']);
        } finally {
            putenv('BB_CLI_CONFIG');
        }
    }
}
