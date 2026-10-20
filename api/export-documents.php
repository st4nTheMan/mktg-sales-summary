<?php

require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../database/xlsx_writer.php';

$databaseConfig = require __DIR__ . '/../config/database.php';

$format = isset($_GET['format']) ? strtolower(trim($_GET['format'])) : 'xlsx';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$storeName = isset($_GET['store_name']) ? trim($_GET['store_name']) : '';
$dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

$exportResult = getAllDocumentRows($databaseConfig, $search, $storeName, $dateFrom, $dateTo, 20000);

if (!empty($exportResult['error'])) {
    http_response_code(400);
    echo 'Error: ' . $exportResult['error'];
    exit;
}

$filenameParts = array('Document_Transactions');
if ($storeName !== '') {
    $filenameParts[] = $storeName;
}
if ($dateFrom !== '') {
    $filenameParts[] = 'from_' . $dateFrom;
}
if ($dateTo !== '') {
    $filenameParts[] = 'to_' . $dateTo;
}
$baseFilename = implode('_', $filenameParts);

$headers = array(
    'Store Name',
    'Doc No',
    'Tender Name',
    'Created Datetime',
    'Cust ID',
    'BA Card No.',
    'Company Name',
    'First Name',
    'Last Name',
    'Contact Number',
    'Total Amount',
    'Loyalty Points',
);

if ($format === 'xlsx') {
    SimpleXLSXWriter::output($baseFilename . '.xlsx', $headers, $exportResult['rows']);
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

fputcsv($output, $headers);

foreach ($exportResult['rows'] as $row) {
    fputcsv($output, array_values($row));
}

fclose($output);
exit;
