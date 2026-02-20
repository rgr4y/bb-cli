<?php

if (!function_exists('array_get')) {
    function array_get($array, $key, $default = null)
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
    function getRepoPath()
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
     * Keys: R, bold, dim, red, orange, yellow, green, teal, blue, purple, gray, white.
     *
     * @return array<string,string>
     */
    function ansi()
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
    function o($data, $color = 'white', $prefix = '', $end = "\033[0m".PHP_EOL)
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
    function getUserInput($question, $default = null)
    {
        if (is_null($default)) {
            $default = '';
        }

        $prompt = $default !== '' ? $question.' ['.$default.']: ' : $question.' ';
        $input = readline($prompt);

        if ($input === false || $input === '') {
            return $default;
        }

        return $input;
    }
}

if (!function_exists('config')) {
    function config($key, $default = null)
    {
        $appConfig = include __DIR__.'/../../config/app.php';

        return array_get($appConfig, $key, $default);
    }
}

if (!function_exists('userConfig')) {
    function userConfig($key, $default = null)
    {
        $userConfigFilePath = config('userConfigFilePath');

        $dir = dirname($userConfigFilePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        if (!file_exists($userConfigFilePath)) {
            file_put_contents($userConfigFilePath, '{}');
            chmod($userConfigFilePath, 0600);
        }

        $config = json_decode(file_get_contents($userConfigFilePath), true) ?? [];

        if (is_null($key)) {
            return $config;
        }

        if (is_array($key)) {
            $arrayKey = key($key);
            $config[$arrayKey] = $key[$arrayKey];

            $result = file_put_contents(
                $userConfigFilePath,
                json_encode($config, JSON_PRETTY_PRINT)
            );
            chmod($userConfigFilePath, 0600);
            return $result;
        }

        return array_get($config, $key, $default);
    }
}
