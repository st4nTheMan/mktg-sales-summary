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

function normalizeStoreFilter($value)
{
    $value = trim((string) $value);
    $allowed = array('BHDM', 'BHDC', 'BHDMi');

    foreach ($allowed as $store) {
        if (strcasecmp($store, $value) === 0) {
            return $store;
        }
    }

    return '';
}

function buildStoreCustomerExclusionCondition($storeName, array $customerIds)
{
    if (empty($customerIds)) {
        return '1 = 1';
    }

    $storeName = str_replace("'", "''", $storeName);
    $customerIds = array_map('intval', $customerIds);
    $numericIds = implode(', ', $customerIds);
    $textIds = "'" . implode("', '", $customerIds) . "'";

    return "(TRIM(b.store_name) <> '" . $storeName . "' OR ((a.cust_id IS NULL OR (a.cust_id NOT IN (" . $numericIds . ") AND TRIM(TO_CHAR(a.cust_id)) NOT IN (" . $textIds . "))) AND (a.alternate_id1 IS NULL OR TRIM(TO_CHAR(a.alternate_id1)) NOT IN (" . $textIds . "))))";
}

function buildSalesSummaryFilters($dateFrom, $dateTo, $storeName = '')
{
    $storeName = normalizeStoreFilter($storeName);

    $conditions = array(
        'b.receipt_type IN (0)',
        'b.status = 4',
        buildStoreCustomerExclusionCondition('BHDM', array(6238, 911137)),
        buildStoreCustomerExclusionCondition('BHDC', array(911205, 911204)),
        buildStoreCustomerExclusionCondition('BHDMi', array(911324, 911305, 911320)),
        'b.store_name = :store_name',
        "b.tender_name IN ('Cash', 'Credit Card')",
    );
    $binds = array(
        ':store_name' => $storeName,
    );

    if ($dateFrom !== '') {
        $conditions[] = 'TRUNC(b.created_datetime) >= TO_DATE(:date_from, \'YYYY-MM-DD\')';
        $binds[':date_from'] = $dateFrom;
    }

    if ($dateTo !== '') {
        $conditions[] = 'TRUNC(b.created_datetime) <= TO_DATE(:date_to, \'YYYY-MM-DD\')';
        $binds[':date_to'] = $dateTo;
    }

    return array(
        'where_sql' => 'WHERE ' . implode("\n  AND ", $conditions),
        'binds' => $binds,
    );
}

function bindSalesSummaryParams($statement, array $binds)
{
    foreach ($binds as $name => $value) {
        oci_bind_by_name($statement, $name, $binds[$name]);
    }
}

function getSalesSummaryRows(array $config, $page = 1, $perPage = 31, $dateFrom = '', $dateTo = '', $storeName = '')
{
    $page = max(1, (int) $page);
    $perPage = max(1, (int) $perPage);
    $dateFrom = normalizeDateFilter($dateFrom);
    $dateTo = normalizeDateFilter($dateTo);
    $storeName = normalizeStoreFilter($storeName);

    if ($storeName === '') {
        return array(
            'rows' => array(),
            'totals' => null,
            'error' => '',
            'pagination' => buildPaginationData(1, $perPage, 0),
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'store_name' => '',
        );
    }

    if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
        $temp = $dateFrom;
        $dateFrom = $dateTo;
        $dateTo = $temp;
    }

    $filters = buildSalesSummaryFilters($dateFrom, $dateTo, $storeName);
    $whereSql = $filters['where_sql'];
    $binds = $filters['binds'];

    $emptyResult = array(
        'rows' => array(),
        'totals' => null,
        'error' => '',
        'pagination' => buildPaginationData($page, $perPage, 0),
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'store_name' => $storeName,
    );

    $database = getDatabaseConnection($config);

    if (!$database['connection']) {
        $emptyResult['error'] = $database['error'];
        return $emptyResult;
    }

    $connection = $database['connection'];

    $countSql = '
        SELECT COUNT(*) AS total
        FROM (
            SELECT TRUNC(b.created_datetime) AS sale_day
            FROM rps.document b
            LEFT JOIN rps.customer a ON b.bt_cuid = a.sid
            ' . $whereSql . '
            GROUP BY TRUNC(b.created_datetime)
        )
    ';

    $countStatement = oci_parse($connection, $countSql);

    if (!$countStatement) {
        $error = oci_error($connection);
        oci_close($connection);
        $emptyResult['error'] = isset($error['message']) ? $error['message'] : 'Unable to prepare count query.';
        return $emptyResult;
    }

    bindSalesSummaryParams($countStatement, $binds);

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
    $offset = ($page - 1) * $perPage;
    $maxRow = $offset + $perPage;

    $dataSql = " 
    SELECT
        sale_day,
        TO_CHAR(sale_day, 'FMMonth YYYY') AS \"MONTH\",
        TO_CHAR(sale_day, 'DD') AS \"DAY\",
        sales_with_ba AS \"SALES WITH BA\",
        tc_with_ba AS \"TC WITH BA\",
        ac_with_ba AS \"AC WITH BA\",
        sales_without_ba AS \"SALES W/O BA\",
        tc_without_ba AS \"TC W/O BA\",
        ac_without_ba AS \"AC W/O BA\"
    FROM (
        SELECT
            TRUNC(b.created_datetime) AS sale_day,

            SUM(
                CASE
                    WHEN a.alternate_id1 IS NOT NULL
                         AND a.alternate_id1 <> '0'
                    THEN b.transaction_total_amt
                    ELSE 0
                END
            ) AS sales_with_ba,

            COUNT(
                CASE
                    WHEN a.alternate_id1 IS NOT NULL
                         AND a.alternate_id1 <> '0'
                    THEN 1
                END
            ) AS tc_with_ba,

            ROUND(
                AVG(
                    CASE
                        WHEN a.alternate_id1 IS NOT NULL
                             AND a.alternate_id1 <> '0'
                        THEN b.transaction_total_amt
                    END
                ),
                2
            ) AS ac_with_ba,

            SUM(
                CASE
                    WHEN a.alternate_id1 IS NULL
                    THEN b.transaction_total_amt
                    ELSE 0
                END
            ) AS sales_without_ba,

            COUNT(
                CASE
                    WHEN a.alternate_id1 IS NULL
                    THEN 1
                END
            ) AS tc_without_ba,

            ROUND(
                AVG(
                    CASE
                        WHEN a.alternate_id1 IS NULL
                        THEN b.transaction_total_amt
                    END
                ),
                2
            ) AS ac_without_ba

        FROM rps.document b

        LEFT JOIN rps.customer a
            ON a.sid = b.bt_cuid

        $whereSql

        GROUP BY TRUNC(b.created_datetime)
    )
    ORDER BY sale_day
    OFFSET :offset ROWS
    FETCH NEXT (:max_row - :offset) ROWS ONLY
";

    $binds[':offset'] = $offset;
    $binds[':max_row'] = $maxRow;

    $dataStatement = oci_parse($connection, $dataSql);

    if (!$dataStatement) {
        $error = oci_error($connection);
        oci_close($connection);
        $emptyResult['error'] = isset($error['message']) ? $error['message'] : 'Unable to prepare data query.';
        $emptyResult['pagination'] = buildPaginationData($page, $perPage, $totalRows);
        return $emptyResult;
    }

    bindSalesSummaryParams($dataStatement, $binds);

    if (!oci_execute($dataStatement)) {
        $error = oci_error($dataStatement);
        oci_free_statement($dataStatement);
        oci_close($connection);
        $emptyResult['error'] = isset($error['message']) ? $error['message'] : 'Unable to run data query.';
        $emptyResult['pagination'] = buildPaginationData($page, $perPage, $totalRows);
        return $emptyResult;
    }

    $rows = array();

    while ($row = oci_fetch_assoc($dataStatement)) {
        unset($row['SALE_DAY'], $row['RN']);
        $rows[] = formatSalesSummaryRow($row);
    }

    oci_free_statement($dataStatement);

    $totals = null;

    $totalsSql = "
        SELECT
            SUM(
                CASE
                    WHEN a.alternate_id1 IS NOT NULL
                         AND a.alternate_id1 <> '0'
                    THEN b.transaction_total_amt
                    ELSE 0
                END
            ) AS sales_with_ba,

            COUNT(
                CASE
                    WHEN a.alternate_id1 IS NOT NULL
                         AND a.alternate_id1 <> '0'
                    THEN 1
                END
            ) AS tc_with_ba,

            SUM(
                CASE
                    WHEN a.alternate_id1 IS NULL
                    THEN b.transaction_total_amt
                    ELSE 0
                END
            ) AS sales_without_ba,

            COUNT(
                CASE
                    WHEN a.alternate_id1 IS NULL
                    THEN 1
                END
            ) AS tc_without_ba

        FROM rps.document b

        LEFT JOIN rps.customer a
            ON a.sid = b.bt_cuid

        $whereSql
    ";

    $totalsBinds = $binds;
    unset($totalsBinds[':offset'], $totalsBinds[':max_row']);

    $totalsStatement = oci_parse($connection, $totalsSql);

    if ($totalsStatement) {
        bindSalesSummaryParams($totalsStatement, $totalsBinds);
        if (oci_execute($totalsStatement)) {
            $totalsRow = oci_fetch_assoc($totalsStatement);
            if ($totalsRow) {
                $sWithBa = (float) (isset($totalsRow['SALES_WITH_BA']) ? $totalsRow['SALES_WITH_BA'] : 0);
                $tcWithBa = (int) (isset($totalsRow['TC_WITH_BA']) ? $totalsRow['TC_WITH_BA'] : 0);
                $acWithBa = $tcWithBa > 0 ? $sWithBa / $tcWithBa : 0;

                $sWoBa = (float) (isset($totalsRow['SALES_WITHOUT_BA']) ? $totalsRow['SALES_WITHOUT_BA'] : 0);
                $tcWoBa = (int) (isset($totalsRow['TC_WITHOUT_BA']) ? $totalsRow['TC_WITHOUT_BA'] : 0);
                $acWoBa = $tcWoBa > 0 ? $sWoBa / $tcWoBa : 0;

                $totals = array(
                    'SALES WITH BA' => number_format($sWithBa, 2),
                    'TC WITH BA' => number_format($tcWithBa, 0),
                    'AC WITH BA' => number_format($acWithBa, 2),
                    'SALES W/O BA' => number_format($sWoBa, 2),
                    'TC W/O BA' => number_format($tcWoBa, 0),
                    'AC W/O BA' => number_format($acWoBa, 2),
                );
            }
            oci_free_statement($totalsStatement);
        }
    }

    oci_close($connection);

    return array(
        'rows' => $rows,
        'totals' => $totals,
        'error' => '',
        'pagination' => buildPaginationData($page, $perPage, $totalRows),
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'store_name' => $storeName,
    );
}

function isAmountColumn($column)
{
    $upper = strtoupper((string) $column);

    return strpos($upper, 'SALES') !== false
        || strpos($upper, 'AC ') !== false
        || strpos($upper, 'TC ') !== false;
}

function formatSalesSummaryRow(array $row)
{
    $formatted = array();

    foreach ($row as $key => $value) {
        if ($value === null) {
            $formatted[$key] = '';
            continue;
        }

        if (is_object($value) && $value instanceof OCI_Lob) {
            $formatted[$key] = $value->read($value->size());
            continue;
        }

        if (isAmountColumn($key) && is_numeric($value)) {
            $decimals = stripos($key, 'TC ') !== false ? 0 : 2;
            $formatted[$key] = number_format((float) $value, $decimals);
            continue;
        }

        $formatted[$key] = (string) $value;
    }

    return $formatted;
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

function getAllSalesSummaryRows(array $config, $dateFrom = '', $dateTo = '', $storeName = '')
{
    $dateFrom = normalizeDateFilter($dateFrom);
    $dateTo = normalizeDateFilter($dateTo);
    $storeName = normalizeStoreFilter($storeName);

    if ($storeName === '') {
        return array(
            'rows' => array(),
            'totals' => null,
            'error' => 'Store name is required for export.',
        );
    }

    if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
        $temp = $dateFrom;
        $dateFrom = $dateTo;
        $dateTo = $temp;
    }

    $filters = buildSalesSummaryFilters($dateFrom, $dateTo, $storeName);
    $whereSql = $filters['where_sql'];
    $binds = $filters['binds'];

    $database = getDatabaseConnection($config);

    if (!$database['connection']) {
        return array(
            'rows' => array(),
            'totals' => null,
            'error' => $database['error'],
        );
    }

    $connection = $database['connection'];

    $dataSql = "
        SELECT
            sale_day,
            TO_CHAR(sale_day, 'FMMonth YYYY') AS \"MONTH\",
            TO_CHAR(sale_day, 'DD') AS \"DAY\",
            sales_with_ba AS \"SALES WITH BA\",
            tc_with_ba AS \"TC WITH BA\",
            ac_with_ba AS \"AC WITH BA\",
            sales_without_ba AS \"SALES W/O BA\",
            tc_without_ba AS \"TC W/O BA\",
            ac_without_ba AS \"AC W/O BA\"
        FROM (
            SELECT
                TRUNC(b.created_datetime) AS sale_day,

                SUM(
                    CASE
                        WHEN a.alternate_id1 IS NOT NULL
                             AND a.alternate_id1 <> '0'
                        THEN b.transaction_total_amt
                        ELSE 0
                    END
                ) AS sales_with_ba,

                COUNT(
                    CASE
                        WHEN a.alternate_id1 IS NOT NULL
                             AND a.alternate_id1 <> '0'
                        THEN 1
                    END
                ) AS tc_with_ba,

                ROUND(
                    AVG(
                        CASE
                            WHEN a.alternate_id1 IS NOT NULL
                                 AND a.alternate_id1 <> '0'
                            THEN b.transaction_total_amt
                        END
                    ),
                    2
                ) AS ac_with_ba,

                SUM(
                    CASE
                        WHEN a.alternate_id1 IS NULL
                        THEN b.transaction_total_amt
                        ELSE 0
                    END
                ) AS sales_without_ba,

                COUNT(
                    CASE
                        WHEN a.alternate_id1 IS NULL
                        THEN 1
                    END
                ) AS tc_without_ba,

                ROUND(
                    AVG(
                        CASE
                            WHEN a.alternate_id1 IS NULL
                            THEN b.transaction_total_amt
                        END
                    ),
                    2
                ) AS ac_without_ba

            FROM rps.document b

            LEFT JOIN rps.customer a
                ON a.sid = b.bt_cuid

            $whereSql

            GROUP BY TRUNC(b.created_datetime)
        )
        ORDER BY sale_day
    ";

    $dataStatement = oci_parse($connection, $dataSql);

    if (!$dataStatement) {
        $error = oci_error($connection);
        oci_close($connection);
        return array(
            'rows' => array(),
            'totals' => null,
            'error' => isset($error['message']) ? $error['message'] : 'Unable to prepare export query.',
        );
    }

    bindSalesSummaryParams($dataStatement, $binds);

    if (!oci_execute($dataStatement)) {
        $error = oci_error($dataStatement);
        oci_free_statement($dataStatement);
        oci_close($connection);
        return array(
            'rows' => array(),
            'totals' => null,
            'error' => isset($error['message']) ? $error['message'] : 'Unable to run export query.',
        );
    }

    $rows = array();

    while ($row = oci_fetch_assoc($dataStatement)) {
        unset($row['SALE_DAY'], $row['RN']);
        $rows[] = formatSalesSummaryRow($row);
    }

    oci_free_statement($dataStatement);

    $totals = null;

    $totalsSql = "
        SELECT
            SUM(
                CASE
                    WHEN a.alternate_id1 IS NOT NULL
                         AND a.alternate_id1 <> '0'
                    THEN b.transaction_total_amt
                    ELSE 0
                END
            ) AS sales_with_ba,

            COUNT(
                CASE
                    WHEN a.alternate_id1 IS NOT NULL
                         AND a.alternate_id1 <> '0'
                    THEN 1
                END
            ) AS tc_with_ba,

            SUM(
                CASE
                    WHEN a.alternate_id1 IS NULL
                    THEN b.transaction_total_amt
                    ELSE 0
                END
            ) AS sales_without_ba,

            COUNT(
                CASE
                    WHEN a.alternate_id1 IS NULL
                    THEN 1
                END
            ) AS tc_without_ba

        FROM rps.document b

        LEFT JOIN rps.customer a
            ON a.sid = b.bt_cuid

        $whereSql
    ";

    $totalsStatement = oci_parse($connection, $totalsSql);

    if ($totalsStatement) {
        bindSalesSummaryParams($totalsStatement, $binds);
        if (oci_execute($totalsStatement)) {
            $totalsRow = oci_fetch_assoc($totalsStatement);
            if ($totalsRow) {
                $sWithBa = (float) (isset($totalsRow['SALES_WITH_BA']) ? $totalsRow['SALES_WITH_BA'] : 0);
                $tcWithBa = (int) (isset($totalsRow['TC_WITH_BA']) ? $totalsRow['TC_WITH_BA'] : 0);
                $acWithBa = $tcWithBa > 0 ? $sWithBa / $tcWithBa : 0;

                $sWoBa = (float) (isset($totalsRow['SALES_WITHOUT_BA']) ? $totalsRow['SALES_WITHOUT_BA'] : 0);
                $tcWoBa = (int) (isset($totalsRow['TC_WITHOUT_BA']) ? $totalsRow['TC_WITHOUT_BA'] : 0);
                $acWoBa = $tcWoBa > 0 ? $sWoBa / $tcWoBa : 0;

                $totals = array(
                    'SALES WITH BA' => number_format($sWithBa, 2),
                    'TC WITH BA' => number_format($tcWithBa, 0),
                    'AC WITH BA' => number_format($acWithBa, 2),
                    'SALES W/O BA' => number_format($sWoBa, 2),
                    'TC W/O BA' => number_format($tcWoBa, 0),
                    'AC W/O BA' => number_format($acWoBa, 2),
                );
            }
            oci_free_statement($totalsStatement);
        }
    }

    oci_close($connection);

    return array(
        'rows' => $rows,
        'totals' => $totals,
        'error' => '',
    );
}

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

function formatDocumentRow(array $row)
{
    $formatted = array();

    foreach ($row as $key => $value) {
        // Exclude a.sid as requested
        if (strcasecmp($key, 'sid') === 0) {
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
        "c.primary_flag = '1'",
        "a.doc_no IS NOT NULL",
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
        $rows[] = formatDocumentRow($row);
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
        "c.primary_flag = '1'",
        "a.doc_no IS NOT NULL",
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


