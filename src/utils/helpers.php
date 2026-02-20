<?php

if (!function_exists('array_get')) {
    function array_get(mixed $array, mixed $key, mixed $default = null): mixed
    {
        if (is_null($key)) {
            return $array;
        }

        if (isset($array[$key])) {
            return $array[$key];
        }

        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }

            $array = $array[$segment];
        }

        return $array;
    }
}

if (!function_exists('getRepoPath')) {
    function getRepoPath(): string
    {
        // Check if project URL is provided via --project argument
        if (isset($GLOBALS['bb_cli_project_url'])) {
            $projectUrl = $GLOBALS['bb_cli_project_url'];

            // If it's just owner/repo format (no URL), return as-is
            if (preg_match('#^[a-zA-Z0-9_][a-zA-Z0-9_.-]*[a-zA-Z0-9_]/[a-zA-Z0-9_][a-zA-Z0-9_.-]*[a-zA-Z0-9_]$#', $projectUrl)) {
                return $projectUrl;
            }

            // Parse bitbucket URL to get owner/repo
            $patterns = [
                '#https?://bitbucket\.org/(.+?)/?(?:\.git)?/?$#',
                '#git@bitbucket\.org:(.+?)\.git$#'
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $projectUrl, $matches)) {
                    return rtrim($matches[1], '/');
                }
            }

            throw new \Exception('Invalid repository format. Expected: "owner/repo" or "https://bitbucket.org/owner/repo"');
        }

        // Default behavior: get from local git config
        $remoteOrigin = trim(exec('git config --get remote.origin.url'));
        preg_match('#.*bitbucket\.org[:,/](.+?)\.git#', $remoteOrigin, $matches);

        if (!$matches) {
            throw new \Exception('No Bitbucket remote found. Run from a repo with a bitbucket.org origin, or pass --project owner/repo.');
        }

        return $matches[1];
    }
}

if (!function_exists('ansi')) {
    /**
     * Returns the ANSI 256-color palette used by the help renderer in bin/bb.
     * Keys: R, bold, dim, cyan, blue, slate, white, gray, red, yellow, green.
     *
     * @return array<string,string>
     */
    function ansi(): array
    {
        // Palette based on Bitbucket's UI colors:
        // cyan/teal  = Bitbucket icon + active nav  (#00C7E6 → 38;5;45)
        // blue       = Atlassian brand blue          (#2684FF → 38;5;75)
        // navy       = sidebar background accent     (#1C2B41 → dim white)
        // slate      = inactive nav text             (#9FADBC → 38;5;110)
        // white      = primary text                  (#FFFFFF → 38;5;255)
        // red        = destructive actions           (#FF5630 → 38;5;203)
        // yellow     = warnings / args               (#FFAB00 → 38;5;214)
        // green      = success                       (#36B37E → 38;5;78)
        return [
            'R'      => "\033[0m",
            'bold'   => "\033[1m",
            'dim'    => "\033[2m",
            'cyan'   => "\033[38;5;45m",   // Bitbucket icon teal / active nav
            'blue'   => "\033[38;5;75m",   // Atlassian brand blue
            'slate'  => "\033[38;5;110m",  // inactive nav / secondary text
            'white'  => "\033[38;5;255m",  // primary text
            'gray'   => "\033[38;5;244m",  // muted text / hints
            'red'    => "\033[38;5;203m",  // errors / destructive
            'yellow' => "\033[38;5;214m",  // warnings / args
            'green'  => "\033[38;5;78m",   // success
        ];
    }
}

if (!function_exists('o')) {
    function o(mixed $data, string $color = 'white', string $prefix = '', string $end = "\033[0m".PHP_EOL): void
    {
        // JSON mode: accumulate structured data, dump at shutdown
        if (!empty($GLOBALS['BB_JSON_MODE'])) {
            if (is_array($data)) {
                $GLOBALS['BB_JSON_OUTPUT'][] = $data;
            }
            // scalar strings (status messages like "OK.", "Approved.") are intentionally
            // skipped in JSON mode — they carry no structured value for machine consumers
            return;
        }

        // Basic SGR colors used for action output (o() calls in Action classes)
        $colors = [
            'nocolor' => "\033[0m",
            'red'     => "\033[0;31m",
            'green'   => "\033[0;32m",
            'yellow'  => "\033[0;33m",
            'blue'    => "\033[0;34m",
            'magenta' => "\033[0;35m",
            'cyan'    => "\033[0;36m",
            'white'   => "\033[0;37m",
            'gray'    => "\033[0;90m",
        ];

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    o($value, $color, $prefix, $end);
                } else {
                    if (!is_int($key)) {
                        o(ucfirst($key).': ', 'cyan', $prefix, $colors['nocolor']);
                        o($value, 'yellow', '');
                    } else {
                        o($value, $color, '');
                    }
                }
            }
        } else {
            echo $colors[$color].$prefix.$data;
            echo $end;
        }
    }
}

if (!function_exists('getUserInput')) {
    function getUserInput(string $question, ?string $default = null): string
    {
        if (is_null($default)) {
            $default = '';
        }

        $prompt = $default !== '' ? $question.' ['.$default.']: ' : $question.' ';
        $input = readline($prompt);

        if ($input === false) {
            // Ctrl+D / EOF — don't silently accept the default, abort.
            fwrite(STDERR, PHP_EOL . 'Input cancelled.' . PHP_EOL);
            exit(1);
        }

        if ($input === '') {
            return $default;
        }

        return $input;
    }
}

if (!function_exists('machineKey')) {
    /**
     * Derives a 32-byte AES key from the current user UID + machine UUID.
     * Binding to UID+machine means the encrypted config is unreadable on a
     * different machine or under a different user — without requiring any
     * password prompt.
     */
    function machineKey(): string
    {
        static $key = null;
        if ($key !== null) {
            return $key;
        }

        $uid = function_exists('posix_getuid') ? posix_getuid() : getenv('UID');

        // macOS: Hardware UUID via system_profiler
        // Linux: /etc/machine-id (systemd) or /var/lib/dbus/machine-id (dbus fallback)
        $machineId = trim(shell_exec('cat /etc/machine-id 2>/dev/null')
            ?: shell_exec('cat /var/lib/dbus/machine-id 2>/dev/null')
            ?: shell_exec("system_profiler SPHardwareDataType 2>/dev/null | awk '/Hardware UUID/ {print $3}'")
            ?: 'fallback-no-machine-id');

        $key = hash('sha256', "bb-cli:{$uid}:{$machineId}", true);
        return $key;
    }
}

if (!function_exists('encryptSecret')) {
    /**
     * Encrypts a secret string with AES-256-CBC using the machine key.
     * Returns a string in the format "base64(iv):base64(ciphertext)".
     */
    function encryptSecret(string $plaintext): string
    {
        $iv = openssl_random_pseudo_bytes(16);
        if ($iv === false) {
            throw new \RuntimeException('Failed to generate IV for secret encryption.');
        }
        $enc = openssl_encrypt($plaintext, 'AES-256-CBC', machineKey(), OPENSSL_RAW_DATA, $iv);
        if ($enc === false) {
            throw new \RuntimeException('Failed to encrypt secret.');
        }
        return base64_encode($iv) . ':' . base64_encode($enc);
    }
}

if (!function_exists('decryptSecret')) {
    /**
     * Decrypts a secret previously encrypted with encryptSecret().
     * Returns null if the value is not encrypted (plaintext migration path)
     * or throws on decryption failure (wrong machine/corrupted).
     *
     * @throws \Exception on decryption failure
     */
    function decryptSecret(string $value): ?string
    {
        // Not encrypted — plaintext value (legacy or test)
        if (strpos($value, ':') === false || substr_count($value, ':') !== 1) {
            return null;
        }

        [$ivB64, $encB64] = explode(':', $value, 2);
        $iv  = base64_decode($ivB64,  true);
        $enc = base64_decode($encB64, true);

        if ($iv === false || $enc === false || strlen($iv) !== 16) {
            return null; // not our format — treat as plaintext
        }

        $plain = openssl_decrypt($enc, 'AES-256-CBC', machineKey(), OPENSSL_RAW_DATA, $iv);

        if ($plain === false) {
            throw new \Exception(
                "Stored credential is invalid or corrupt. Please re-enter it by running \"bb auth\".",
                1
            );
        }

        return $plain;
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        $appConfig = include __DIR__.'/../../config/app.php';

        return array_get($appConfig, $key, $default);
    }
}

if (!function_exists('userConfig')) {
    function userConfig(mixed $key, mixed $default = null): mixed
    {
        $userConfigFilePath = config('userConfigFilePath');

        $dir = dirname($userConfigFilePath);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0700, true) && !is_dir($dir)) {
                throw new \RuntimeException(sprintf('Directory "%s" could not be created.', $dir));
            }
        }

        if (!file_exists($userConfigFilePath)) {
            file_put_contents($userConfigFilePath, '{}');
            chmod($userConfigFilePath, 0600);
        }

        $config = json_decode(file_get_contents($userConfigFilePath), true) ?? [];

        if (is_null($key)) {
            // Decrypt secrets for full-config reads too
            foreach (['apiToken', 'appPassword', 'repoToken'] as $field) {
                if (!empty($config['auth'][$field])) {
                    $plain = decryptSecret($config['auth'][$field]);
                    if ($plain !== null) {
                        $config['auth'][$field] = $plain;
                    }
                }
            }
            return $config;
        }

        if (is_array($key)) {
            $arrayKey = key($key);
            $value    = $key[$arrayKey];

            // Encrypt secrets before writing
            if ($arrayKey === 'auth' && is_array($value)) {
                foreach (['apiToken', 'appPassword', 'repoToken'] as $field) {
                    if (!empty($value[$field])) {
                        $value[$field] = encryptSecret($value[$field]);
                    }
                }
            }

            $config[$arrayKey] = $value;

            $result = file_put_contents(
                $userConfigFilePath,
                json_encode($config, JSON_PRETTY_PRINT)
            );
            chmod($userConfigFilePath, 0600);
            return $result;
        }

        // Decrypt secrets on read
        if (isset($config['auth'])) {
            foreach (['apiToken', 'appPassword', 'repoToken'] as $field) {
                if (!empty($config['auth'][$field])) {
                    $plain = decryptSecret($config['auth'][$field]);
                    if ($plain !== null) {
                        $config['auth'][$field] = $plain;
                    }
                    // null = not encrypted (plaintext) — leave as-is
                }
            }
        }

        return array_get($config, $key, $default);
    }
}
