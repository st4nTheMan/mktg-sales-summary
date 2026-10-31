<?php

function getDatabaseConnection(array $config)
{
    if (empty($config['username']) || empty($config['password']) || empty($config['tns'])) {
        return array(
            'connection' => null,
            'error' => 'Oracle database settings are missing. Configure ORACLE_USERNAME, ORACLE_PASSWORD, and ORACLE_TNS.',
        );
    }

    if (!function_exists('oci_connect')) {
        return array(
            'connection' => null,
            'error' => 'The PHP OCI8 extension is not installed or not enabled.',
        );
    }

    $connection = @oci_connect(
        $config['username'],
        $config['password'],
        $config['tns'],
        $config['charset']
    );

    if (!$connection) {
        $error = oci_error();
        $message = isset($error['message']) ? $error['message'] : 'Unable to connect to Oracle.';

        return array(
            'connection' => null,
            'error' => $message,
        );
    }

    return array(
        'connection' => $connection,
        'error' => '',
    );
}

function normalizeDateFilter($value)
{
    $value = trim((string) $value);

    if ($value === '') {
        return '';
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);

    if (!$date || $date->format('Y-m-d') !== $value) {
        return '';
    }

    return $value;
}

function buildPaginationData($page, $perPage, $totalRows)
{
    $totalPages = $totalRows > 0 ? (int) ceil($totalRows / $perPage) : 1;

    return array(
        'current_page' => $page,
        'per_page' => $perPage,
        'total_rows' => $totalRows,
        'total_pages' => max(1, $totalPages),
        'start_row' => $totalRows > 0 ? (($page - 1) * $perPage) + 1 : 0,
        'end_row' => min($page * $perPage, $totalRows),
    );
}


