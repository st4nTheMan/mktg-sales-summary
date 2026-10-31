<?php

require_once __DIR__ . '/connection.php';

function getDocumentColumnLabel($column)
{
    $labels = array(
        'STORE_NAME' => 'Store Name',
        'DOC_NO' => 'Invoice #',
        'TENDER_NAME' => 'Tender Name',
        'CREATED_DATETIME' => 'Date & Time',
        'CUST_ID' => 'Cust ID',
        'BA Card No.' => 'BA Card No.',
        'BA_CARD_NO' => 'BA Card No.',
        'BT_COMPANY_NAME' => 'Company Name',
        'BT_FIRST_NAME' => 'First Name',
        'BT_LAST_NAME' => 'Last Name',
        'Contact Number' => 'Contact Number',
        'CONTACT_NUMBER' => 'Contact Number',
        'PHONE_NO' => 'Contact Number',
        'TRANSACTION_TOTAL_AMT' => 'Total Amount',
        'Loyalty Points' => 'Loyalty Points',
        'LOYALTY_POINTS' => 'Loyalty Points',
        'LTY_BALANCE' => 'Loyalty Points',
    );

    return isset($labels[$column]) ? $labels[$column] : $column;
}

function formatDocumentRow(array $row, $includeSid = false)
{
    $formatted = array();

    foreach ($row as $key => $value) {
        // Keep a.sid available to the document table for invoice links, but
        // continue omitting it from exports.
        if (strcasecmp($key, 'sid') === 0 && !$includeSid) {
            continue;
        }

        if ($value === null) {
            $formatted[$key] = '';
            continue;
        }

        if (is_object($value) && $value instanceof OCI_Lob) {
            $formatted[$key] = $value->read($value->size());
            continue;
        }

        if (strcasecmp($key, 'TRANSACTION_TOTAL_AMT') === 0 && is_numeric($value)) {
            $formatted[$key] = number_format((float) $value, 2);
            continue;
        }

        if (in_array(strtoupper($key), array('LOYALTY POINTS', 'LOYALTY_POINTS', 'LTY_BALANCE')) && is_numeric($value)) {
            $formatted[$key] = number_format((float) $value, 2);
            continue;
        }

        $formatted[$key] = (string) $value;
    }

    return $formatted;
}

function getDocumentRows(array $config, $page = 1, $perPage = 50, $search = '', $storeName = '', $dateFrom = '', $dateTo = '')
{
    $page = max(1, (int) $page);
    $perPage = max(1, (int) $perPage);
    $search = trim((string) $search);
    $storeName = trim((string) $storeName);
    $dateFrom = normalizeDateFilter($dateFrom);
    $dateTo = normalizeDateFilter($dateTo);

    $isFiltered = ($search !== '' || $storeName !== '' || $dateFrom !== '' || $dateTo !== '');

    if (!$isFiltered) {
        return array(
            'rows' => array(),
            'error' => '',
            'pagination' => buildPaginationData(1, $perPage, 0),
            'search' => '',
            'store_name' => '',
            'date_from' => '',
            'date_to' => '',
            'is_filtered' => false,
        );
    }

    $emptyResult = array(
        'rows' => array(),
        'error' => '',
        'pagination' => buildPaginationData($page, $perPage, 0),
        'search' => $search,
        'store_name' => $storeName,
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'is_filtered' => true,
    );

    $database = getDatabaseConnection($config);

    if (!$database['connection']) {
        $emptyResult['error'] = $database['error'];
        return $emptyResult;
    }

    $connection = $database['connection'];

    $conditions = array(
        "b.mark1 LIKE '%BA%'",
        "a.doc_no IS NOT NULL",
        "a.tender_name IN ('Cash', 'Credit Card')",
    );
    $binds = array();

    if ($storeName !== '') {
        $conditions[] = 'TRIM(a.store_name) = :store_name';
        $binds[':store_name'] = $storeName;
    }

    if ($dateFrom !== '') {
        $conditions[] = 'TRUNC(a.created_datetime) >= TO_DATE(:date_from, \'YYYY-MM-DD\')';
        $binds[':date_from'] = $dateFrom;
    }

    if ($dateTo !== '') {
        $conditions[] = 'TRUNC(a.created_datetime) <= TO_DATE(:date_to, \'YYYY-MM-DD\')';
        $binds[':date_to'] = $dateTo;
    }

    if ($search !== '') {
        $conditions[] = '(
            TO_CHAR(a.doc_no) LIKE :search
            OR TO_CHAR(b.cust_id) LIKE :search
            OR UPPER(b.alternate_id1) LIKE UPPER(:search)
            OR UPPER(a.store_name) LIKE UPPER(:search)
            OR UPPER(a.bt_first_name) LIKE UPPER(:search)
            OR UPPER(a.bt_last_name) LIKE UPPER(:search)
            OR UPPER(a.bt_company_name) LIKE UPPER(:search)
            OR UPPER(c.phone_no) LIKE UPPER(:search)
        )';
        $binds[':search'] = '%' . $search . '%';
    }

    $whereSql = !empty($conditions) ? 'WHERE ' . implode("\n  AND ", $conditions) : '';

    $countSql = "
        SELECT COUNT(*) AS total
        FROM (
            SELECT DISTINCT a.sid
            FROM rps.document a
            LEFT JOIN rps.customer b ON a.bt_id = b.cust_id
            LEFT JOIN rps.customer_phone c ON c.cust_sid = b.sid
            LEFT JOIN rps.lty_cust_central d ON d.cust_sid = b.sid
            $whereSql
        )
    ";

    $countStatement = oci_parse($connection, $countSql);

    if (!$countStatement) {
        $error = oci_error($connection);
        oci_close($connection);
        $emptyResult['error'] = isset($error['message']) ? $error['message'] : 'Unable to prepare count query.';
        return $emptyResult;
    }

    foreach ($binds as $name => $value) {
        oci_bind_by_name($countStatement, $name, $binds[$name]);
    }

    if (!oci_execute($countStatement)) {
        $error = oci_error($countStatement);
        oci_free_statement($countStatement);
        oci_close($connection);
        $emptyResult['error'] = isset($error['message']) ? $error['message'] : 'Unable to run count query.';
        return $emptyResult;
    }

    $countRow = oci_fetch_assoc($countStatement);
    $totalRows = isset($countRow['TOTAL']) ? (int) $countRow['TOTAL'] : 0;
    oci_free_statement($countStatement);

    $totalPages = max(1, (int) ceil($totalRows / $perPage));
    $page = min($page, $totalPages);
    $offset = (int) (($page - 1) * $perPage);
    $perPageInt = (int) $perPage;

    $dataSql = "
        SELECT DISTINCT
            a.sid,
            a.store_name,
            a.doc_no,
            a.tender_name,
            TO_CHAR(a.created_datetime, 'YYYY-MM-DD HH:MI:SS') as created_datetime,
            b.cust_id,
            b.alternate_id1 AS \"BA Card No.\",
            a.bt_company_name,
            a.bt_first_name,
            a.bt_last_name,
            c.phone_no AS \"Contact Number\",
            a.transaction_total_amt,
            d.lty_balance AS \"Loyalty Points\"
        FROM
            rps.document a
        LEFT JOIN
            rps.customer b ON a.bt_id = b.cust_id
        LEFT JOIN
            rps.customer_phone c ON c.cust_sid = b.sid
        LEFT JOIN
            rps.lty_cust_central d ON d.cust_sid = b.sid
        $whereSql
        ORDER BY 
            created_datetime DESC,
            a.sid DESC
        OFFSET {$offset} ROWS
        FETCH NEXT {$perPageInt} ROWS ONLY
    ";

    $dataStatement = oci_parse($connection, $dataSql);

    if (!$dataStatement) {
        $error = oci_error($connection);
        oci_close($connection);
        $emptyResult['error'] = isset($error['message']) ? $error['message'] : 'Unable to prepare document query.';
        $emptyResult['pagination'] = buildPaginationData($page, $perPage, $totalRows);
        return $emptyResult;
    }

    foreach ($binds as $name => $value) {
        oci_bind_by_name($dataStatement, $name, $binds[$name]);
    }

    if (!oci_execute($dataStatement)) {
        $error = oci_error($dataStatement);
        oci_free_statement($dataStatement);
        oci_close($connection);
        $emptyResult['error'] = isset($error['message']) ? $error['message'] : 'Unable to run document query.';
        $emptyResult['pagination'] = buildPaginationData($page, $perPage, $totalRows);
        return $emptyResult;
    }

    $rows = array();

    while ($row = oci_fetch_assoc($dataStatement)) {
        $rows[] = formatDocumentRow($row, true);
    }

    oci_free_statement($dataStatement);
    oci_close($connection);

    return array(
        'rows' => $rows,
        'error' => '',
        'pagination' => buildPaginationData($page, $perPage, $totalRows),
        'search' => $search,
        'store_name' => $storeName,
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'is_filtered' => true,
    );
}

function getAllDocumentRows(array $config, $search = '', $storeName = '', $dateFrom = '', $dateTo = '', $maxRows = 10000)
{
    $search = trim((string) $search);
    $storeName = trim((string) $storeName);
    $dateFrom = normalizeDateFilter($dateFrom);
    $dateTo = normalizeDateFilter($dateTo);
    $maxRows = max(1, (int) $maxRows);

    $isFiltered = ($search !== '' || $storeName !== '' || $dateFrom !== '' || $dateTo !== '');

    if (!$isFiltered) {
        return array(
            'rows' => array(),
            'error' => 'Please apply a filter before exporting.',
        );
    }

    $database = getDatabaseConnection($config);

    if (!$database['connection']) {
        return array(
            'rows' => array(),
            'error' => $database['error'],
        );
    }

    $connection = $database['connection'];

    $conditions = array(
        "b.mark1 LIKE '%BA%'",
        "a.doc_no IS NOT NULL",
        "a.tender_name IN ('Cash', 'Credit Card')",
    );
    $binds = array();

    if ($storeName !== '') {
        $conditions[] = 'TRIM(a.store_name) = :store_name';
        $binds[':store_name'] = $storeName;
    }

    if ($dateFrom !== '') {
        $conditions[] = 'TRUNC(a.created_datetime) >= TO_DATE(:date_from, \'YYYY-MM-DD\')';
        $binds[':date_from'] = $dateFrom;
    }

    if ($dateTo !== '') {
        $conditions[] = 'TRUNC(a.created_datetime) <= TO_DATE(:date_to, \'YYYY-MM-DD\')';
        $binds[':date_to'] = $dateTo;
    }

    if ($search !== '') {
        $conditions[] = '(
            TO_CHAR(a.doc_no) LIKE :search
            OR TO_CHAR(b.cust_id) LIKE :search
            OR UPPER(b.alternate_id1) LIKE UPPER(:search)
            OR UPPER(a.store_name) LIKE UPPER(:search)
            OR UPPER(a.bt_first_name) LIKE UPPER(:search)
            OR UPPER(a.bt_last_name) LIKE UPPER(:search)
            OR UPPER(a.bt_company_name) LIKE UPPER(:search)
            OR UPPER(c.phone_no) LIKE UPPER(:search)
        )';
        $binds[':search'] = '%' . $search . '%';
    }

    $whereSql = !empty($conditions) ? 'WHERE ' . implode("\n  AND ", $conditions) : '';

    $dataSql = "
        SELECT DISTINCT
            a.sid,
            a.store_name,
            a.doc_no,
            a.tender_name,
            TO_CHAR(a.created_datetime, 'YYYY-MM-DD HH:MI:SS') as created_datetime,
            b.cust_id,
            b.alternate_id1 AS \"BA Card No.\",
            a.bt_company_name,
            a.bt_first_name,
            a.bt_last_name,
            c.phone_no AS \"Contact Number\",
            a.transaction_total_amt,
            d.lty_balance AS \"Loyalty Points\"
        FROM
            rps.document a
        LEFT JOIN
            rps.customer b ON a.bt_id = b.cust_id
        LEFT JOIN
            rps.customer_phone c ON c.cust_sid = b.sid
        LEFT JOIN
            rps.lty_cust_central d ON d.cust_sid = b.sid
        $whereSql
        ORDER BY 
            created_datetime DESC
        FETCH NEXT :max_rows ROWS ONLY
    ";

    $dataStatement = oci_parse($connection, $dataSql);

    if (!$dataStatement) {
        $error = oci_error($connection);
        oci_close($connection);
        return array(
            'rows' => array(),
            'error' => isset($error['message']) ? $error['message'] : 'Unable to prepare export query.',
        );
    }

    foreach ($binds as $name => $value) {
        oci_bind_by_name($dataStatement, $name, $binds[$name]);
    }

    oci_bind_by_name($dataStatement, ':max_rows', $maxRows);

    if (!oci_execute($dataStatement)) {
        $error = oci_error($dataStatement);
        oci_free_statement($dataStatement);
        oci_close($connection);
        return array(
            'rows' => array(),
            'error' => isset($error['message']) ? $error['message'] : 'Unable to run export query.',
        );
    }

    $rows = array();

    while ($row = oci_fetch_assoc($dataStatement)) {
        $rows[] = formatDocumentRow($row);
    }

    oci_free_statement($dataStatement);
    oci_close($connection);

    return array(
        'rows' => $rows,
        'error' => '',
    );
}



