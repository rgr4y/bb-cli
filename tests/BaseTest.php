<?php

use PHPUnit\Framework\TestCase;
use BBCli\BBCli\Actions\Pr;
use BBCli\BBCli\Actions\Branch;
use BBCli\BBCli\Actions\Auth;

class BaseTest extends TestCase
{
    // ── getMethodNameFromAlias ────────────────────────────────────────────────

    public function test_alias_resolves_primary_command(): void
    {
        $pr = new Pr();
        $this->assertSame('list', $pr->getMethodNameFromAlias('list'));
    }

    public function test_alias_resolves_short_alias(): void
    {
        $pr = new Pr();
        $this->assertSame('list', $pr->getMethodNameFromAlias('l'));
    }

    public function test_alias_resolves_view_show(): void
    {
        $pr = new Pr();
        $this->assertSame('view', $pr->getMethodNameFromAlias('show'));
    }

    public function test_alias_resolves_decline_close(): void
    {
        $pr = new Pr();
        $this->assertSame('decline', $pr->getMethodNameFromAlias('close'));
    }

    public function test_alias_resolves_commits_checks(): void
    {
        $pr = new Pr();
        $this->assertSame('commits', $pr->getMethodNameFromAlias('checks'));
    }

    public function test_unknown_alias_returns_false(): void
    {
        $pr = new Pr();
        $this->assertFalse($pr->getMethodNameFromAlias('doesnotexist'));
    }

    // ── listCommandsForAutocomplete ───────────────────────────────────────────

    public function test_autocomplete_output_contains_primary_commands(): void
    {
        $pr = new Pr();
        ob_start();
        $pr->listCommandsForAutocomplete();
        $output = ob_get_clean();

        $commands = explode(' ', $output);
        $this->assertContains('list', $commands);
        $this->assertContains('view', $commands);
        $this->assertContains('merge', $commands);
        $this->assertContains('create', $commands);
    }

    public function test_autocomplete_excludes_aliases(): void
    {
        $pr = new Pr();
        ob_start();
        $pr->listCommandsForAutocomplete();
        $output = ob_get_clean();

        $this->assertStringNotContainsString('show', $output);
        $this->assertStringNotContainsString('close', $output);
        $this->assertStringNotContainsString(' l ', $output);
    }

    // ── AVAILABLE_COMMANDS sanity ─────────────────────────────────────────────

    public function test_all_pr_aliases_are_unique(): void
    {
        $seen = [];
        foreach (Pr::AVAILABLE_COMMANDS as $aliases) {
            foreach (array_map('trim', explode(',', $aliases)) as $alias) {
                $this->assertNotContains($alias, $seen, "Duplicate alias: {$alias}");
                $seen[] = $alias;
            }
        }
    }

    public function test_pr_default_method_exists(): void
    {
        $this->assertTrue(method_exists(Pr::class, Pr::DEFAULT_METHOD));
    }
}
