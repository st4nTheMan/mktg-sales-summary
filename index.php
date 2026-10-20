<?php

require_once __DIR__ . '/database/connection.php';

$databaseConfig = require __DIR__ . '/config/database.php';

$currentPage = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$storeName = isset($_GET['store_name']) ? trim($_GET['store_name']) : '';

$salesSummaryResult = getSalesSummaryRows($databaseConfig, $currentPage, 31, $dateFrom, $dateTo, $storeName);

require __DIR__ . '/app/views/sales-summary-table.php';
