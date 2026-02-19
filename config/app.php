<?php

$home    = getenv('HOME');
$newPath = $home.'/.local/share/bb-cli/config.json';
$oldPath = $home.'/.bitbucket-rest-cli-config.json';

// Migrate legacy config to new location on first use
if (!file_exists($newPath) && file_exists($oldPath)) {
    $dir = dirname($newPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }
    copy($oldPath, $newPath);
}

return [

    /**
     * User config file path.
     * Stored at ~/.local/share/bb-cli/config.json.
     * Migrates automatically from ~/.bitbucket-rest-cli-config.json on first run.
     */
    'userConfigFilePath' => $newPath,

];
