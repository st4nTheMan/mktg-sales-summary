<?php

require_once __DIR__ . '/../database/sales_summary.php';
require_once __DIR__ . '/../database/xlsx_writer.php';

$databaseConfig = require __DIR__ . '/../config/database.php';

$dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$storeName = isset($_GET['store_name']) ? trim($_GET['store_name']) : '';
$format = isset($_GET['format']) ? strtolower(trim($_GET['format'])) : 'csv';

$storeNameNormalized = normalizeStoreFilter($storeName);

if ($storeNameNormalized === '') {
    http_response_code(400);
    echo 'Error: Store name is required for export.';
    exit;
}

$exportResult = getAllSalesSummaryRows($databaseConfig, $dateFrom, $dateTo, $storeNameNormalized);

if (!empty($exportResult['error'])) {
    http_response_code(500);
    echo 'Error: ' . $exportResult['error'];
    exit;
}

$filenameParts = array('Sales_Summary', $storeNameNormalized);
if ($dateFrom !== '') {
    $filenameParts[] = 'from_' . $dateFrom;
}
if ($dateTo !== '') {
    $filenameParts[] = 'to_' . $dateTo;
}
$baseFilename = implode('_', $filenameParts);

$headers = !empty($exportResult['rows']) ? array_keys($exportResult['rows'][0]) : array('MONTH', 'DAY', 'SALES WITH BA', 'TC WITH BA', 'AC WITH BA', 'SALES W/O BA', 'TC W/O BA', 'AC W/O BA');

if ($format === 'xlsx') {
    SimpleXLSXWriter::output($baseFilename . '.xlsx', $headers, $exportResult['rows'], $exportResult['totals']);
    exit;
}

// CSV Export
$filename = $baseFilename . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// Write UTF-8 BOM for Excel compatibility
fprintf($output, "\xEF\xBB\xBF");

if (!empty($exportResult['rows'])) {
    // Header row
    fputcsv($output, $headers);

    // Data rows
    foreach ($exportResult['rows'] as $row) {
        fputcsv($output, array_values($row));
    }

    // Summary totals row
    if (!empty($exportResult['totals'])) {
        $totalsRow = array(
            'TOTAL',
            '',
            $exportResult['totals']['SALES WITH BA'],
            $exportResult['totals']['TC WITH BA'],
            $exportResult['totals']['AC WITH BA'],
            $exportResult['totals']['SALES W/O BA'],
            $exportResult['totals']['TC W/O BA'],
            $exportResult['totals']['AC W/O BA'],
        );
        fputcsv($output, $totalsRow);
    }
} else {
    fputcsv($output, array('No records found for the selected criteria.'));
}

fclose($output);
exit;

