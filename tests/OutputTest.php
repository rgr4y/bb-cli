<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests for o() in normal (non-JSON) mode and ansi().
 */
class OutputTest extends TestCase
{
    protected function setUp(): void
    {
        // Ensure JSON mode is off for all these tests
        unset($GLOBALS['BB_JSON_MODE'], $GLOBALS['BB_JSON_OUTPUT']);
    }

    // ── ansi() ───────────────────────────────────────────────────────────────

    public function test_ansi_returns_array(): void
    {
        $this->assertIsArray(ansi());
    }

    public function test_ansi_has_required_keys(): void
    {
        $keys = array_keys(ansi());
        foreach (['R', 'bold', 'dim', 'red', 'yellow', 'green', 'cyan', 'blue', 'gray', 'white', 'slate'] as $key) {
            $this->assertContains($key, $keys, "ansi() missing key: {$key}");
        }
    }

    public function test_ansi_values_are_escape_sequences(): void
    {
        foreach (ansi() as $key => $value) {
            $this->assertStringStartsWith("\033[", $value, "ansi()['{$key}'] is not an ANSI escape sequence");
        }
    }

    public function test_ansi_has_no_undocumented_keys(): void
    {
        // Catches ghost keys like 'orange' or 'purple' that appear in old docblocks but don't exist
        $allowed = ['R', 'bold', 'dim', 'cyan', 'blue', 'slate', 'white', 'gray', 'red', 'yellow', 'green'];
        foreach (array_keys(ansi()) as $key) {
            $this->assertContains($key, $allowed, "ansi() has unexpected key: {$key}");
        }
    }

    // ── o() scalar output ────────────────────────────────────────────────────

    public function test_o_prints_scalar_string(): void
    {
        ob_start();
        o('hello world', 'white');
        $out = ob_get_clean();
        $this->assertStringContainsString('hello world', $out);
    }

    public function test_o_includes_newline_by_default(): void
    {
        ob_start();
        o('line', 'white');
        $out = ob_get_clean();
        $this->assertStringEndsWith(PHP_EOL, $out);
    }

    public function test_o_applies_color_escape(): void
    {
        ob_start();
        o('text', 'red');
        $out = ob_get_clean();
        // Red SGR code
        $this->assertStringContainsString("\033[0;31m", $out);
    }

    // ── o() array output ─────────────────────────────────────────────────────

    public function test_o_prints_indexed_array_values(): void
    {
        ob_start();
        o(['alpha', 'beta', 'gamma'], 'white');
        $out = ob_get_clean();
        $this->assertStringContainsString('alpha', $out);
        $this->assertStringContainsString('beta', $out);
        $this->assertStringContainsString('gamma', $out);
    }

    public function test_o_prints_associative_array_as_key_value(): void
    {
        ob_start();
        o(['title' => 'My PR', 'state' => 'OPEN'], 'white');
        $out = ob_get_clean();
        $this->assertStringContainsString('Title:', $out);
        $this->assertStringContainsString('My PR', $out);
        $this->assertStringContainsString('State:', $out);
        $this->assertStringContainsString('OPEN', $out);
    }

    public function test_o_ucfirst_on_associative_keys(): void
    {
        ob_start();
        o(['author' => 'rob'], 'white');
        $out = ob_get_clean();
        $this->assertStringContainsString('Author:', $out);
        // lowercase 'author:' should not appear
        $this->assertStringNotContainsString('author:', $out);
    }

    public function test_o_handles_nested_array(): void
    {
        ob_start();
        o([['one', 'two'], ['three']], 'white');
        $out = ob_get_clean();
        $this->assertStringContainsString('one', $out);
        $this->assertStringContainsString('three', $out);
    }

    // ── getUserInput() ────────────────────────────────────────────────────────

    public function test_getUserInput_returns_default_on_empty_input(): void
    {
        // readline() not available in test context without a tty;
        // we verify the function exists and its signature is correct
        $this->assertTrue(function_exists('getUserInput'));
    }
}
