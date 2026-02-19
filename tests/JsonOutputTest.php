<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests for --json mode: BB_JSON_MODE global, o() accumulation, and output validity.
 */
class JsonOutputTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset JSON globals before each test
        $GLOBALS['BB_JSON_MODE']   = true;
        $GLOBALS['BB_JSON_OUTPUT'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['BB_JSON_MODE'], $GLOBALS['BB_JSON_OUTPUT']);
    }

    // ── o() accumulation in JSON mode ────────────────────────────────────────

    public function test_o_accumulates_array_in_json_mode(): void
    {
        o(['id' => 1, 'title' => 'Test PR']);
        $this->assertCount(1, $GLOBALS['BB_JSON_OUTPUT']);
        $this->assertSame(['id' => 1, 'title' => 'Test PR'], $GLOBALS['BB_JSON_OUTPUT'][0]);
    }

    public function test_o_accumulates_multiple_arrays(): void
    {
        o(['id' => 1]);
        o(['id' => 2]);
        $this->assertCount(2, $GLOBALS['BB_JSON_OUTPUT']);
    }

    public function test_o_skips_scalars_in_json_mode(): void
    {
        o('OK.', 'green');
        o('Approved.', 'green');
        $this->assertCount(0, $GLOBALS['BB_JSON_OUTPUT']);
    }

    public function test_o_does_not_print_in_json_mode(): void
    {
        ob_start();
        o(['id' => 42]);
        $printed = ob_get_clean();
        $this->assertSame('', $printed);
    }

    // ── JSON validity ─────────────────────────────────────────────────────────

    public function test_single_result_unwraps_to_object(): void
    {
        $GLOBALS['BB_JSON_OUTPUT'] = [['id' => 1, 'title' => 'PR']];
        $out = $this->encodeOutput();

        $decoded = json_decode($out, true);
        $this->assertSame(JSON_ERROR_NONE, json_last_error(), 'Output is not valid JSON');
        // Single result should be an object, not [[...]]
        $this->assertArrayHasKey('id', $decoded);
    }

    public function test_multiple_results_encode_as_array(): void
    {
        $GLOBALS['BB_JSON_OUTPUT'] = [['id' => 1], ['id' => 2]];
        $out = $this->encodeOutput();

        $decoded = json_decode($out, true);
        $this->assertSame(JSON_ERROR_NONE, json_last_error(), 'Output is not valid JSON');
        $this->assertIsArray($decoded);
        $this->assertCount(2, $decoded);
    }

    public function test_empty_output_encodes_as_empty_array(): void
    {
        $GLOBALS['BB_JSON_OUTPUT'] = [];
        $out = $this->encodeOutput();

        $decoded = json_decode($out, true);
        $this->assertSame(JSON_ERROR_NONE, json_last_error(), 'Output is not valid JSON');
        $this->assertSame([], $decoded);
    }

    public function test_unicode_and_slashes_are_not_escaped(): void
    {
        $GLOBALS['BB_JSON_OUTPUT'] = [['link' => 'https://bitbucket.org/org/repo', 'title' => 'Héllo']];
        $out = $this->encodeOutput();

        $this->assertStringContainsString('https://bitbucket.org/org/repo', $out);
        $this->assertStringContainsString('Héllo', $out);
    }

    // ── Normal mode unaffected ────────────────────────────────────────────────

    public function test_o_prints_normally_when_json_mode_off(): void
    {
        unset($GLOBALS['BB_JSON_MODE']);

        ob_start();
        o('hello', 'white');
        $out = ob_get_clean();

        $this->assertStringContainsString('hello', $out);
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    /**
     * Replicates the shutdown function logic from bin/bb.
     */
    private function encodeOutput(): string
    {
        $out = $GLOBALS['BB_JSON_OUTPUT'];
        if (count($out) === 1) {
            $out = $out[0];
        }
        return json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
    }
}
