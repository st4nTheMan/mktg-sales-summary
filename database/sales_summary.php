<?php

require_once __DIR__ . '/connection.php';

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
        'b.doc_no IS NOT NULL',
        'b.status = 4',
        buildStoreCustomerExclusionCondition('BHDM', array(6238, 911137, 911140, 911143)),
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
                    WHEN a.mark1 = 'BA'
                    THEN b.transaction_total_amt
                    ELSE 0
                END
            ) AS sales_with_ba,

            COUNT(
                CASE
                    WHEN a.mark1 = 'BA'
                    THEN 1
                END
            ) AS tc_with_ba,

            ROUND(
                AVG(
                    CASE
                        WHEN a.mark1 = 'BA'
                        THEN b.transaction_total_amt
                    END
                ),
                2
            ) AS ac_with_ba,

            SUM(
                CASE
                    WHEN a.mark1 IS NULL
                    THEN b.transaction_total_amt
                    ELSE 0
                END
            ) AS sales_without_ba,

            COUNT(
                CASE
                    WHEN a.mark1 IS NULL
                    THEN 1
                END
            ) AS tc_without_ba,

            ROUND(
                AVG(
                    CASE
                        WHEN a.mark1 IS NULL
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
                    WHEN a.mark1 = 'BA'
                         AND b.doc_no IS NOT NULL
                    THEN b.transaction_total_amt
                    ELSE 0
                END
            ) AS sales_with_ba,

            COUNT(
                CASE
                    WHEN a.mark1 = 'BA'
                         AND b.doc_no IS NOT NULL
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
                        WHEN a.mark1 = 'BA'
                         AND b.doc_no IS NOT NULL
                        THEN b.transaction_total_amt
                        ELSE 0
                    END
                ) AS sales_with_ba,

                COUNT(
                    CASE
                        WHEN a.mark1 = 'BA'
                         AND b.doc_no IS NOT NULL
                        THEN 1
                    END
                ) AS tc_with_ba,

                ROUND(
                    AVG(
                        CASE
                            WHEN a.mark1 = 'BA'
                         AND b.doc_no IS NOT NULL
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
                    WHEN a.mark1 = 'BA'
                         AND b.doc_no IS NOT NULL
                    THEN b.transaction_total_amt
                    ELSE 0
                END
            ) AS sales_with_ba,

            COUNT(
                CASE
                    WHEN a.mark1 = 'BA'
                         AND b.doc_no IS NOT NULL
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
