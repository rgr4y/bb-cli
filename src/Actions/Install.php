<?php

namespace BBCli\BBCli\Actions;

use BBCli\BBCli\Base;

/**
 * Install
 * Installs bb into a directory on PATH.
 */
class Install extends Base
{
    const DEFAULT_METHOD = 'install';
    const CHECK_GIT_FOLDER = false;
    const AVAILABLE_COMMANDS = [
        'install' => 'install',
    ];

    /**
     * Ordered list of preferred install locations.
     * First one found on PATH wins; ~/.local/bin is the fallback (created if needed).
     */
    private function candidates(): array
    {
        $home = getenv('HOME');
        return [
            $home . '/.local/bin',
            $home . '/bin',
            '/usr/local/bin',
        ];
    }

    /**
     * Install bb into the first candidate directory that exists and is on PATH.
     * Falls back to ~/.local/bin, creating it and patching the shell rc if needed.
     *
     * @return void
     */
    public function install(): void
    {
        $selfPath = \Phar::running(false) ?: realpath($_SERVER['argv'][0]);

        if (!$selfPath || !file_exists($selfPath)) {
            o('Cannot locate bb binary. Are you running from a PHAR?', 'red');
            exit(1);
        }

        $pathDirs = explode(':', getenv('PATH') ?: '');
        $destDir  = null;

        foreach ($this->candidates() as $candidate) {
            if (is_dir($candidate) && in_array($candidate, $pathDirs) && is_writable($candidate)) {
                $destDir = $candidate;
                break;
            }
        }

        $addedToPath = false;

        if (!$destDir) {
            $destDir = getenv('HOME') . '/.local/bin';

            if (!is_dir($destDir)) {
                mkdir($destDir, 0755, true);
                o('Created: ' . $destDir, 'cyan');
            }

            $this->addToPath($destDir);
            $addedToPath = true;
        }

        $dest = $destDir . '/bb';

        if (!copy($selfPath, $dest)) {
            o('Failed to copy bb to: ' . $dest, 'red');
            exit(1);
        }

        chmod($dest, 0755);

        o('Installed: ' . $dest, 'green');

        if ($addedToPath) {
            o($destDir . ' was added to PATH in your shell rc.', 'yellow');
            o('Restart your shell or run: source ~/.zshrc (or ~/.bashrc)', 'yellow');
        }
    }

    /**
     * Append PATH export to the user's shell rc file.
     *
     * @param  string $dir
     * @return void
     */
    private function addToPath(string $dir): void
    {
        $shell  = basename(getenv('SHELL') ?: 'bash');
        $rcFile = getenv('HOME') . ($shell === 'zsh' ? '/.zshrc' : '/.bashrc');

        if (file_exists($rcFile) && strpos(file_get_contents($rcFile), $dir) !== false) {
            return;
        }

        $line = "\nexport PATH=\"{$dir}:\$PATH\" # added by bb-cli\n";

        file_put_contents($rcFile, $line, FILE_APPEND);
        o('Added to PATH in: ' . $rcFile, 'cyan');
    }
}
