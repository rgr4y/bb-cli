<?php

use PHPUnit\Framework\TestCase;

class HelpersTest extends TestCase
{
    // ── array_get ────────────────────────────────────────────────────────────

    public function test_array_get_top_level_key(): void
    {
        $this->assertSame('bar', array_get(['foo' => 'bar'], 'foo'));
    }

    public function test_array_get_dot_notation(): void
    {
        $data = ['author' => ['nickname' => 'rob']];
        $this->assertSame('rob', array_get($data, 'author.nickname'));
    }

    public function test_array_get_missing_key_returns_default(): void
    {
        $this->assertNull(array_get([], 'nope'));
        $this->assertSame('x', array_get([], 'nope', 'x'));
    }

    public function test_array_get_null_key_returns_array(): void
    {
        $data = ['a' => 1];
        $this->assertSame($data, array_get($data, null));
    }

    // ── getRepoPath ───────────────────────────────────────────────────────────

    public function test_getRepoPath_owner_repo_format(): void
    {
        $GLOBALS['bb_cli_project_url'] = 'notarydash/app.notarydash.com';
        $this->assertSame('notarydash/app.notarydash.com', getRepoPath());
        unset($GLOBALS['bb_cli_project_url']);
    }

    public function test_getRepoPath_https_url(): void
    {
        $GLOBALS['bb_cli_project_url'] = 'https://bitbucket.org/myorg/myrepo';
        $this->assertSame('myorg/myrepo', getRepoPath());
        unset($GLOBALS['bb_cli_project_url']);
    }

    public function test_getRepoPath_https_url_with_git_suffix(): void
    {
        $GLOBALS['bb_cli_project_url'] = 'https://bitbucket.org/myorg/myrepo.git';
        $this->assertSame('myorg/myrepo', getRepoPath());
        unset($GLOBALS['bb_cli_project_url']);
    }

    public function test_getRepoPath_ssh_url(): void
    {
        $GLOBALS['bb_cli_project_url'] = 'git@bitbucket.org:myorg/myrepo.git';
        $this->assertSame('myorg/myrepo', getRepoPath());
        unset($GLOBALS['bb_cli_project_url']);
    }

    public function test_getRepoPath_invalid_format_throws(): void
    {
        $this->expectException(\Exception::class);
        $GLOBALS['bb_cli_project_url'] = 'not-a-valid-url-or-slug';
        try {
            getRepoPath();
        } finally {
            unset($GLOBALS['bb_cli_project_url']);
        }
    }
}
