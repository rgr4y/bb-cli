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
        'saveApiToken' => 'token',
        'saveLoginInfo' => 'save',
        'show' => 'show',
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
        $defaultPass = ($existing && isset($existing['appPassword'])) ? $existing['appPassword'] : '';

        $username    = getUserInput('Username: ', $defaultUser);
        $appPassword = getUserInput('App password: ', $defaultPass);

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

        $existing = userConfig('auth');
        $defaultEmail = ($existing && isset($existing['email'])) ? $existing['email'] : '';
        $defaultToken = ($existing && isset($existing['apiToken'])) ? $existing['apiToken'] : '';

        $email    = getUserInput('Atlassian account email: ', $defaultEmail);
        $apiToken = getUserInput('API token: ', $defaultToken);

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
     * Shows config information (user detail).
     *
     * @return void
     */
    public function show()
    {
        $authInfo = userConfig('auth');

        if (!$authInfo) {
            o('You have to configure auth info to use this command.', 'red');
            o('Run "bb auth" first.', 'yellow');
            exit(1);
        }

        // Mask the secret value before displaying
        if (isset($authInfo['appPassword'])) {
            $authInfo['appPassword'] = str_repeat('*', 8);
        }
        if (isset($authInfo['apiToken'])) {
            $authInfo['apiToken'] = str_repeat('*', 8);
        }

        o($authInfo);
    }
}
