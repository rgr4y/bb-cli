<?php

namespace BBCli\BBCli\Actions;

use BBCli\BBCli\Base;

/**
 * Git
 * Configure git credential helper for Bitbucket API token auth.
 *
 * Installs bb itself as a git credential helper at:
 *   ~/.local/share/bb-cli/git-credential-bb
 *
 * Re-run "bb git setup" after updating your token.
 *
 * @see https://support.atlassian.com/bitbucket-cloud/docs/using-api-tokens/
 */
class Git extends Base
{
    /**
     * Git default command.
     */
    const DEFAULT_METHOD = 'setup';

    /**
     * Skip git folder check — these are global commands.
     */
    const CHECK_GIT_FOLDER = false;

    /**
     * Git commands.
     */
    const AVAILABLE_COMMANDS = [
        'setup'  => 'setup',
        'remove' => 'remove',
    ];

    /**
     * Directory for bb-cli data files.
     */
    private function dataDir()
    {
        $base = getenv('XDG_DATA_HOME') ?: (getenv('HOME') . '/.local/share');
        return $base . '/bb-cli';
    }

    /**
     * Path to the installed credential helper binary.
     */
    private function helperPath()
    {
        return $this->dataDir() . '/git-credential-bb';
    }

    /**
     * Install git credential helper for Bitbucket.
     * Copies bb PHAR to ~/.local/share/bb-cli/git-credential-bb and registers it globally.
     *
     * @return void
     */
    public function setup()
    {
        $auth = userConfig('auth');

        if (!$auth) {
            o('No bb-cli auth configured. Run "bb auth" first.', 'red');
            exit(1);
        }

        $dataDir    = $this->dataDir();
        $helperPath = $this->helperPath();

        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0700, true);
        }

        // Copy bb itself as the credential helper — same PHAR, same PHP, no dependency issues.
        // bb detects it's being invoked as a git credential helper via argv[0].
        $selfPath = \Phar::running(false) ?: realpath($_SERVER['argv'][0]);

        if (!$selfPath || !file_exists($selfPath)) {
            o('Cannot locate bb binary to copy. Is it running from a PHAR?', 'red');
            exit(1);
        }

        if (!copy($selfPath, $helperPath)) {
            o('Failed to copy bb to: ' . $helperPath, 'red');
            exit(1);
        }

        chmod($helperPath, 0700);

        // Register scoped to bitbucket.org only
        exec('git config --global credential.https://bitbucket.org.helper ' . escapeshellarg($helperPath), $out, $rc);

        if ($rc !== 0) {
            o('Failed to register credential helper in git config.', 'red');
            exit(1);
        }

        o('Credential helper installed: ' . $helperPath, 'green');
        o('Scoped to: credential.https://bitbucket.org.helper', 'green');
        o('Git will now use your bb-cli token automatically for Bitbucket repos.', 'green');
        o('Run "bb git setup" again after updating your token.', 'gray');
    }

    /**
     * Remove the credential helper and unregister it from git config.
     *
     * @return void
     */
    public function remove()
    {
        $helperPath = $this->helperPath();

        exec('git config --global --unset credential.https://bitbucket.org.helper', $out, $rc);

        if (file_exists($helperPath)) {
            unlink($helperPath);
            o('Removed: ' . $helperPath, 'green');
        } else {
            o('Helper not found at: ' . $helperPath, 'yellow');
        }

        o('Credential helper unregistered.', 'green');
    }

    /**
     * Handle git credential protocol (called by git, not directly by users).
     * argv[0] will be "git-credential-bb" when invoked by git.
     *
     * Supports: get (returns credentials), store (no-op), erase (no-op)
     *
     * @param  string $operation get|store|erase
     * @return void
     */
    public static function handleCredentialProtocol($operation)
    {
        if ($operation !== 'get') {
            exit(0);
        }

        // Read the key=value pairs git sends on stdin
        $host = null;
        while (($line = fgets(STDIN)) !== false) {
            $line = rtrim($line);
            if ($line === '') {
                break;
            }
            if (strpos($line, 'host=') === 0) {
                $host = substr($line, 5);
            }
        }

        if ($host !== 'bitbucket.org') {
            exit(0);
        }

        $auth = userConfig('auth');

        if (!$auth) {
            exit(0);
        }

        if (($auth['type'] ?? '') === 'api_token') {
            $username = 'x-bitbucket-api-token-auth';
            $password = $auth['apiToken'] ?? '';
        } else {
            $username = $auth['username'] ?? '';
            $password = $auth['appPassword'] ?? '';
        }

        if (!$password) {
            exit(0);
        }

        echo "username={$username}\n";
        echo "password={$password}\n";
        exit(0);
    }
}
