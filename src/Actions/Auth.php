<?php

namespace BBCli\BBCli\Actions;

use BBCli\BBCli\Base;

/**
 * Authentication
 * All commands for auth.
 *
 * @see https://bb-cli.github.io/authentication
 */
class Auth extends Base
{
    /**
     * Authentication default command.
     */
    const DEFAULT_METHOD = 'saveApiToken';

    /**
     * Checks the repo .git folder.
     */
    const CHECK_GIT_FOLDER = false;

    /**
     * Authentication commans.
     */
    const AVAILABLE_COMMANDS = [
        'saveApiToken'  => 'token',
        'saveRepoToken' => 'repo-token',
        'saveLoginInfo' => 'save',
        'composerAuth'  => 'composer-auth',
        'show'          => 'show',
    ];

    /**
     * It saves your user information in the config folder.
     * This is used in project (BB-CLI) process.
     *
     * @return void
     */
    public function saveLoginInfo()
    {
        o('App passwords are being deprecated. Use "bb auth token" instead.', 'yellow');
        o('https://support.atlassian.com/bitbucket-cloud/docs/app-passwords/', 'green');

        $existing    = userConfig('auth');
        $defaultUser = ($existing && isset($existing['username'])) ? $existing['username'] : '';
        $hasPass     = ($existing && isset($existing['appPassword'])) && $existing['appPassword'] !== '';

        $username    = getUserInput('Username: ', $defaultUser);
        $appPassword = getUserInput('App password' . ($hasPass ? ' (leave empty to keep existing): ' : ': '), '');
        if ($appPassword === '' && $hasPass) {
            $appPassword = $existing['appPassword'];
        }

        $saveToFile = userConfig([
            'auth' => [
                'type' => 'app_password',
                'username' => $username,
                'appPassword' => $appPassword,
            ],
        ]);

        if ($saveToFile !== false) {
            o('Auth info saved.', 'green');
        } else {
            o('Cannot save file to: '.config('userConfigFilePath'), 'red');
        }
    }

    /**
     * Saves API token credentials (email + token).
     * API tokens are the long-term replacement for app passwords.
     *
     * @return void
     */
    public function saveApiToken()
    {
        o('This action requires a Bitbucket API token:', 'yellow');
        o('Create one at: Profile Settings > Security > API Tokens', 'yellow');
        o('https://support.atlassian.com/bitbucket-cloud/docs/api-tokens/', 'green');

        $existing     = userConfig('auth');
        $defaultEmail = ($existing && isset($existing['email'])) ? $existing['email'] : '';
        $hasToken     = ($existing && isset($existing['apiToken'])) && $existing['apiToken'] !== '';

        $email    = getUserInput('Atlassian account email: ', $defaultEmail);
        $apiToken = getUserInput('API token' . ($hasToken ? ' (leave empty to keep existing): ' : ': '), '');
        if ($apiToken === '' && $hasToken) {
            $apiToken = $existing['apiToken'];
        }

        $saveToFile = userConfig([
            'auth' => [
                'type' => 'api_token',
                'email' => $email,
                'apiToken' => $apiToken,
            ],
        ]);

        if ($saveToFile === false) {
            o('Cannot save file to: '.config('userConfigFilePath'), 'red');
            exit(1);
        }

        o('Verifying credentials...', 'cyan');

        try {
            $user = $this->makeRequest('GET', '/user', [], false);
            o('Authenticated as: '.$user['display_name'].' ('.$user['account_id'].')', 'green');
            o('Auth info saved.', 'green');
        } catch (\Exception $e) {
            o('Credential verification failed: '.$e->getMessage(), 'red');
            $saveAnyway = getUserInput('Save anyway? [y/N]: ');
            if (strtolower(trim($saveAnyway)) !== 'y') {
                userConfig(['auth' => null]);
                o('Auth info discarded.', 'yellow');
                exit(1);
            }
            o('Auth info saved (unverified).', 'yellow');
        }
    }

    /**
     * Saves repository access token credentials.
     * Repo access tokens are scoped to a single repository.
     *
     * @return void
     */
    public function saveRepoToken()
    {
        o('This action requires a Bitbucket repository access token:', 'yellow');
        o('Create one at: Repository Settings > Security > Access tokens', 'yellow');
        o('Repo access tokens are scoped to a single repository.', 'yellow');

        $existing  = userConfig('auth');
        $hasToken  = ($existing && isset($existing['repoToken'])) && $existing['repoToken'] !== '';

        $repoToken = getUserInput('Repo access token' . ($hasToken ? ' (leave empty to keep existing): ' : ': '), '');
        if ($repoToken === '' && $hasToken) {
            $repoToken = $existing['repoToken'];
        }

        $saveToFile = userConfig([
            'auth' => [
                'type'      => 'repo_access_token',
                'repoToken' => $repoToken,
            ],
        ]);

        if ($saveToFile === false) {
            o('Cannot save file to: '.config('userConfigFilePath'), 'red');
            exit(1);
        }

        o('Verifying repository access...', 'cyan');

        try {
            $repoPath   = $this->getRepoPath();
            $repository = $this->makeRequest('GET', '/repositories/' . $repoPath, [], false);
            $repoName   = $repository['full_name'] ?? $repoPath;
            o('Repository access token verified for: ' . $repoName, 'green');
            o('Auth info saved.', 'green');
        } catch (\Exception $e) {
            o('Credential verification failed: '.$e->getMessage(), 'red');
            $saveAnyway = getUserInput('Save anyway? [y/N]: ');
            if (strtolower(trim($saveAnyway)) !== 'y') {
                userConfig(['auth' => null]);
                o('Auth info discarded.', 'yellow');
                exit(1);
            }
            o('Auth info saved (unverified).', 'yellow');
        }
    }

    /**
     * Generate a Composer auth.json for pulling private Bitbucket packages.
     * Prints JSON to stdout, and optionally writes it to a file.
     *
     * @param  string|null $outputPath  Path to write auth.json (optional)
     * @return void
     */
    public function composerAuth($outputPath = null)
    {
        $auth = userConfig('auth');

        if (!$auth) {
            o('Not authenticated. Run "bb auth token" first.', 'red');
            exit(1);
        }

        $authType = $auth['type'] ?? '';

        if ($authType === 'api_token') {
            $username = 'x-bitbucket-api-token-auth';
            $password = $auth['apiToken'] ?? '';
        } elseif ($authType === 'app_password') {
            $username = $auth['username'] ?? '';
            $password = $auth['appPassword'] ?? '';
        } else {
            o('Repo access tokens are scoped to a single repository and cannot be used for Composer.', 'red');
            o('Run "bb auth token" to configure an account-wide API token.', 'yellow');
            exit(1);
        }

        if (!$username || !$password) {
            o('Incomplete credentials. Run "bb auth token" to reconfigure.', 'red');
            exit(1);
        }

        $json = json_encode([
            'http-basic' => [
                'bitbucket.org' => [
                    'username' => $username,
                    'password' => $password,
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

        if ($outputPath) {
            file_put_contents($outputPath, $json);
            chmod($outputPath, 0600);
            o('Composer auth.json written to: ' . $outputPath, 'green');
            o('Add to .gitignore if not already there.', 'yellow');
        } else {
            echo $json;
        }
    }

    /**
     * Shows config information (user detail).
     *
     * @return void
     */
    public function show()
    {
        $authInfo = userConfig('auth');

        if (!$authInfo) {
            o('Not authenticated. Run "bb auth token" first.', 'red');
            exit(1);
        }

        // Mask the secret value before displaying
        foreach (['appPassword', 'apiToken', 'repoToken'] as $field) {
            if (isset($authInfo[$field])) {
                $authInfo[$field] = str_repeat('*', 8);
            }
        }

        o($authInfo);
    }
}
