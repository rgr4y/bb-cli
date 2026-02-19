<?php

require_once __DIR__.'/../src/utils/helpers.php';
require_once __DIR__.'/../src/Base.php';
foreach (glob(__DIR__.'/../src/Actions/*.php') as $file) {
    require_once $file;
}

// Stub config()/userConfig() so tests don't need a real config file on disk
if (!function_exists('config')) {
    function config($key, $default = null) {
        return $default;
    }
}

if (!function_exists('userConfig')) {
    function userConfig($key, $default = null) {
        return $default;
    }
}
