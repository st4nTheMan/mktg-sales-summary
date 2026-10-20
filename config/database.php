<?php

require_once __DIR__ . '/load-env.php';

// Read deployment-specific Oracle settings from the process environment.
return array(
    'driver' => 'oracle',
    'username' => getenv('ORACLE_USERNAME') ?: '',
    'password' => getenv('ORACLE_PASSWORD') ?: '',
    'charset' => getenv('ORACLE_CHARSET') ?: 'AL32UTF8',
    'tns' => getenv('ORACLE_TNS') ?: '',
);
