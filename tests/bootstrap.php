<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once dirname(__DIR__) . '/vendor/autoload.php';

// A stale JARDIS_DOTENV_VARS from the surrounding process would mark keys as "published by this
// library" that this run never published, which would silently disable the precedence rule.
putenv(\JardisSupport\DotEnv\Handler\ReadAmbientValue::MARKER_KEY);
unset(
    $_ENV[\JardisSupport\DotEnv\Handler\ReadAmbientValue::MARKER_KEY],
    $_SERVER[\JardisSupport\DotEnv\Handler\ReadAmbientValue::MARKER_KEY]
);
