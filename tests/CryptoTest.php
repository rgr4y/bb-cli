<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests for machineKey(), encryptSecret(), decryptSecret(), and the
 * transparent encrypt-on-write / decrypt-on-read behaviour in userConfig().
 */
class CryptoTest extends TestCase
{
    // ── machineKey() ──────────────────────────────────────────────────────────

    public function test_machineKey_returns_32_bytes(): void
    {
        $key = machineKey();
        $this->assertSame(32, strlen($key));
    }

    public function test_machineKey_is_deterministic(): void
    {
        $this->assertSame(machineKey(), machineKey());
    }

    // ── encryptSecret / decryptSecret round-trip ──────────────────────────────

    public function test_encrypt_produces_iv_colon_ciphertext_format(): void
    {
        $enc = encryptSecret('my-secret-token');
        $this->assertStringContainsString(':', $enc);
        $parts = explode(':', $enc);
        $this->assertCount(2, $parts);
        // Both parts are valid base64
        $this->assertNotFalse(base64_decode($parts[0], true));
        $this->assertNotFalse(base64_decode($parts[1], true));
    }

    public function test_encrypt_does_not_store_plaintext(): void
    {
        $enc = encryptSecret('super-secret');
        $this->assertStringNotContainsString('super-secret', $enc);
    }

    public function test_decrypt_round_trip(): void
    {
        $original = 'ATATT3xFfGF0supersecrettoken';
        $enc      = encryptSecret($original);
        $this->assertSame($original, decryptSecret($enc));
    }

    public function test_decrypt_returns_null_for_plaintext(): void
    {
        // A plain API token has no ':' separator in the right place
        $this->assertNull(decryptSecret('plaintexttoken'));
    }

    public function test_decrypt_returns_null_for_invalid_base64(): void
    {
        $this->assertNull(decryptSecret('not-base64!!!:also-not-base64!!!'));
    }

    public function test_decrypt_throws_on_corrupted_ciphertext(): void
    {
        $enc = encryptSecret('real-secret');
        [$iv, $ct] = explode(':', $enc);
        // Corrupt the ciphertext
        $corrupted = $iv . ':' . base64_encode('GARBAGE_GARBAGE_GARBAGE_GARBAGE_');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/invalid or corrupt/i');
        decryptSecret($corrupted);
    }

    public function test_each_encryption_produces_unique_ciphertext(): void
    {
        // Random IV means the same plaintext encrypts differently each time
        $a = encryptSecret('same-value');
        $b = encryptSecret('same-value');
        $this->assertNotSame($a, $b);
        // But both decrypt to the same thing
        $this->assertSame('same-value', decryptSecret($a));
        $this->assertSame('same-value', decryptSecret($b));
    }

    // ── userConfig() transparent encrypt/decrypt ──────────────────────────────

    private string $tmpConfig;

    protected function setUp(): void
    {
        $this->tmpConfig = tempnam(sys_get_temp_dir(), 'bb_crypto_');
        unlink($this->tmpConfig);
        putenv("BB_CLI_CONFIG={$this->tmpConfig}");
    }

    protected function tearDown(): void
    {
        putenv('BB_CLI_CONFIG');
        @unlink($this->tmpConfig);
    }

    public function test_userConfig_encrypts_apiToken_on_write(): void
    {
        userConfig(['auth' => [
            'type'     => 'api_token',
            'email'    => 'test@example.com',
            'apiToken' => 'plaintext-token',
        ]]);

        $raw = json_decode(file_get_contents($this->tmpConfig), true);
        $this->assertNotSame('plaintext-token', $raw['auth']['apiToken']);
        $this->assertStringContainsString(':', $raw['auth']['apiToken']);
    }

    public function test_userConfig_decrypts_apiToken_on_read(): void
    {
        userConfig(['auth' => [
            'type'     => 'api_token',
            'email'    => 'test@example.com',
            'apiToken' => 'plaintext-token',
        ]]);

        $this->assertSame('plaintext-token', userConfig('auth.apiToken'));
    }

    public function test_userConfig_plaintext_token_reads_as_is(): void
    {
        // Simulate a legacy config with unencrypted token
        file_put_contents($this->tmpConfig, json_encode([
            'auth' => ['type' => 'api_token', 'email' => 'x@x.com', 'apiToken' => 'legacytoken'],
        ]));
        chmod($this->tmpConfig, 0600);

        $this->assertSame('legacytoken', userConfig('auth.apiToken'));
    }

    public function test_userConfig_corrupt_token_throws(): void
    {
        // Write a value that looks encrypted (has iv:ct format) but won't decrypt
        $enc = encryptSecret('valid-token');
        [$iv, $ct] = explode(':', $enc);
        $corrupted = $iv . ':' . base64_encode('GARBAGE_GARBAGE_GARBAGE_GARBAGE_');

        file_put_contents($this->tmpConfig, json_encode([
            'auth' => ['type' => 'api_token', 'email' => 'x@x.com', 'apiToken' => $corrupted],
        ]));
        chmod($this->tmpConfig, 0600);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/invalid or corrupt/i');
        userConfig('auth.apiToken');
    }
}
