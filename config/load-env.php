<?php

$environmentFile = dirname(__DIR__) . '/.env';

if (!is_readable($environmentFile)) {
    return;
}

$environmentLines = file($environmentFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

foreach ($environmentLines as $environmentLine) {
    $environmentLine = trim($environmentLine);

    if ($environmentLine === '' || strpos($environmentLine, '#') === 0 || strpos($environmentLine, '=') === false) {
        continue;
    }

    list($name, $value) = explode('=', $environmentLine, 2);
    $name = trim($name);
    $value = trim($value);

    if ($name === '' || getenv($name) !== false) {
        continue;
    }

    if (strlen($value) >= 2 && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
        $value = substr($value, 1, -1);
    }

    putenv($name . '=' . $value);
    $_ENV[$name] = $value;
}
