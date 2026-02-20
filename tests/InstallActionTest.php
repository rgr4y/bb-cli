<?php

use PHPUnit\Framework\TestCase;
use BBCli\BBCli\Actions\Install;

/**
 * Tests for Install action — directory selection, writability check,
 * PATH detection, and addToPath deduplication.
 */
class InstallActionTest extends TestCase
{
    private string $tmpConfig;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpConfig = tempnam(sys_get_temp_dir(), 'bb_install_');
        unlink($this->tmpConfig);
        putenv("BB_CLI_CONFIG={$this->tmpConfig}");

        $this->tmpDir = sys_get_temp_dir() . '/bb_install_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        putenv('BB_CLI_CONFIG');
        @unlink($this->tmpConfig);
        $this->rmdirRecursive($this->tmpDir);
    }

    // ── AVAILABLE_COMMANDS ────────────────────────────────────────────────────

    public function test_install_command_exists(): void
    {
        $this->assertArrayHasKey('install', Install::AVAILABLE_COMMANDS);
    }

    public function test_install_alias_resolves(): void
    {
        $install = new Install();
        $this->assertSame('install', $install->getMethodNameFromAlias('install'));
    }

    public function test_check_git_folder_is_false(): void
    {
        $this->assertFalse(Install::CHECK_GIT_FOLDER);
    }

    public function test_default_method_is_install(): void
    {
        $this->assertSame('install', Install::DEFAULT_METHOD);
    }

    // ── addToPath deduplication ───────────────────────────────────────────────

    public function test_addToPath_appends_export_to_rc_file(): void
    {
        $rcFile = $this->tmpDir . '/.bashrc';
        file_put_contents($rcFile, '# existing content' . PHP_EOL);

        $this->invokeAddToPath('/some/new/dir', $rcFile);

        $contents = file_get_contents($rcFile);
        $this->assertStringContainsString('export PATH="/some/new/dir:$PATH"', $contents);
        $this->assertStringContainsString('# added by bb-cli', $contents);
    }

    public function test_addToPath_does_not_duplicate_if_dir_already_present(): void
    {
        $rcFile = $this->tmpDir . '/.bashrc';
        file_put_contents($rcFile, 'export PATH="/some/dir:$PATH" # added by bb-cli' . PHP_EOL);

        $this->invokeAddToPath('/some/dir', $rcFile);

        // Should only appear once
        $contents = file_get_contents($rcFile);
        $this->assertSame(1, substr_count($contents, '/some/dir'));
    }

    public function test_addToPath_creates_rc_file_if_missing(): void
    {
        // Use a fresh home dir with no existing rc file
        $home = $this->tmpDir . '/fresh_home';
        mkdir($home, 0755);
        $rcFile = $home . '/.bashrc';
        $this->assertFileDoesNotExist($rcFile);

        putenv('SHELL=/bin/bash');
        putenv('HOME=' . $home);

        $install = new Install();
        $ref     = new ReflectionMethod($install, 'addToPath');
        $ref->setAccessible(true);

        ob_start();
        $ref->invoke($install, '/my/bin');
        ob_end_clean();

        $this->assertFileExists($rcFile);
        $this->assertStringContainsString('/my/bin', file_get_contents($rcFile));

        putenv('HOME');
        putenv('SHELL');
    }

    // ── install directory writability ─────────────────────────────────────────

    public function test_writable_candidate_on_path_is_selected(): void
    {
        $dir = $this->tmpDir . '/bin';
        mkdir($dir, 0755);

        // Inject a fake PATH and HOME so Install picks our dir
        putenv("PATH={$dir}:/usr/bin");
        putenv("HOME={$this->tmpDir}");

        // Write a dummy "self" binary
        $selfPath = $this->tmpDir . '/bb_self';
        file_put_contents($selfPath, '#!/usr/bin/env php' . PHP_EOL . '<?php echo "bb";');
        chmod($selfPath, 0755);

        // Patch argv[0] to point at our dummy self
        $_SERVER['argv'][0] = $selfPath;

        ob_start();
        (new Install())->install();
        $out = ob_get_clean();

        $this->assertFileExists($dir . '/bb');
        $this->assertStringContainsString('Installed:', $out);

        putenv('PATH');
        putenv('HOME');
    }

    public function test_install_falls_back_to_local_bin_when_no_candidate_on_path(): void
    {
        $home = $this->tmpDir . '/home';
        mkdir($home, 0755);
        putenv("HOME={$home}");
        putenv('PATH=/nonexistent/path1:/nonexistent/path2');

        $selfPath = $this->tmpDir . '/bb_self';
        file_put_contents($selfPath, '#!/usr/bin/env php' . PHP_EOL . '<?php echo "bb";');
        chmod($selfPath, 0755);
        $_SERVER['argv'][0] = $selfPath;

        ob_start();
        (new Install())->install();
        $out = ob_get_clean();

        $this->assertFileExists($home . '/.local/bin/bb');
        $this->assertStringContainsString('Installed:', $out);

        putenv('PATH');
        putenv('HOME');
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    /**
     * Invoke the private addToPath() method via reflection.
     */
    private function invokeAddToPath(string $dir, string $rcFile): void
    {
        // Temporarily override HOME and SHELL so addToPath uses our rc file
        $origHome  = getenv('HOME');
        $origShell = getenv('SHELL');

        putenv('SHELL=/bin/bash');
        putenv('HOME=' . dirname($rcFile));

        // Rename rc file to .bashrc in a temp home
        $tmpHome = dirname($rcFile);
        $target  = $tmpHome . '/.bashrc';
        if ($rcFile !== $target && file_exists($rcFile)) {
            rename($rcFile, $target);
            $rcFile = $target;
        }

        $install = new Install();
        $ref     = new ReflectionMethod($install, 'addToPath');
        $ref->setAccessible(true);

        ob_start();
        $ref->invoke($install, $dir);
        ob_end_clean();

        putenv('HOME=' . ($origHome ?: ''));
        putenv('SHELL=' . ($origShell ?: ''));
    }

    private function rmdirRecursive(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->rmdirRecursive($path) : unlink($path);
        }
        rmdir($dir);
    }
}
