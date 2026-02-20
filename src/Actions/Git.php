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
    private function dataDir(): string
    {
        $base = getenv('XDG_DATA_HOME') ?: (getenv('HOME') . '/.local/share');
        return $base . '/bb-cli';
    }

    /**
     * Path to the installed credential helper binary.
     */
    private function helperPath(): string
    {
        return $this->dataDir() . '/git-credential-bb';
    }

    /**
     * Install git credential helper for Bitbucket.
     * Copies bb PHAR to ~/.local/share/bb-cli/git-credential-bb and registers it globally.
     *
     * @return void
     */
    public function setup(): void
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

        // Set user.name globally only if not already configured
        $existingName = trim(shell_exec('git config --global user.name 2>/dev/null') ?: '');
        if (!$existingName) {
            $auth = userConfig('auth');
            $default = ($auth['type'] ?? '') === 'api_token'
                ? ($auth['email'] ?? '')
                : ($auth['username'] ?? '');
            o('git config --global user.name is not set — required for commit authorship.', 'yellow');
            $name = getUserInput('Your name: ', $default);
            if ($name) {
                exec('git config --global user.name ' . escapeshellarg($name));
            }
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
    public function remove(): void
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
    public static function handleCredentialProtocol(string $operation): void
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

        $authType = $auth['type'] ?? '';

        if ($authType === 'repo_access_token') {
            // Bitbucket repo access tokens use a static username per the git HTTPS protocol spec.
            $username = 'x-token-auth';
            $password = $auth['repoToken'] ?? '';
        } elseif ($authType === 'api_token') {
            // Bitbucket API tokens use a static username per the git HTTPS protocol spec.
            // See: https://support.atlassian.com/bitbucket-cloud/docs/api-tokens/
            $username = 'x-bitbucket-api-token-auth';
            $password = $auth['apiToken'] ?? '';
        } else {
            $username = $auth['username'] ?? '';
            $password = $auth['appPassword'] ?? '';
        }

        if (!$password) {
            exit(0);
        }

        // Strip newlines from credentials — they would break the line-based git credential protocol
        // (each field must be exactly "key=value\n"). This is intentional protocol compliance, not sanitization.
        $username = str_replace(["\n", "\r"], '', $username);
        $password = str_replace(["\n", "\r"], '', $password);

        echo "username={$username}\n";
        echo "password={$password}\n";
        exit(0);
    }
}
