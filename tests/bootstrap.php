<?php

require_once __DIR__.'/../src/utils/helpers.php';
require_once __DIR__.'/../src/Base.php';
foreach (glob(__DIR__.'/../src/Actions/*.php') as $file) {
    require_once $file;
}
