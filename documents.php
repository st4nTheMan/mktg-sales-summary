<?php

require_once __DIR__ . '/database/connection.php';

$databaseConfig = require __DIR__ . '/config/database.php';

$currentPage = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$storeName = isset($_GET['store_name']) ? trim($_GET['store_name']) : '';
$dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

$perPage = 50;

$documentResult = getDocumentRows($databaseConfig, $currentPage, $perPage, $search, $storeName, $dateFrom, $dateTo);

require __DIR__ . '/app/views/documents-table.php';
