<?php
$pageTitle = 'Marketing Sales Summary';
require __DIR__ . '/components/header.php';
?>
        <main class="page-shell">
            <?php
            require_once __DIR__ . '/../helpers/pagination.php';

            $pagination = $salesSummaryResult['pagination'];
            $currentPage = $pagination['current_page'];
            $totalPages = $pagination['total_pages'];
            $dateFrom = $salesSummaryResult['date_from'];
            $dateTo = $salesSummaryResult['date_to'];
            $storeName = isset($salesSummaryResult['store_name']) ? $salesSummaryResult['store_name'] : '';

            $startPage = max(1, $currentPage - 2);
            $endPage = min($totalPages, $currentPage + 2);

            ?>

            <div class="page-header d-flex align-items-center justify-content-between">
                <div>
                    <p class="eyebrow mb-2">Marketing Report</p>
                    <h1 class="h3 mb-1">Sales Summary</h1>
                    <p class="text-body-secondary mb-0"><?php echo htmlentities($storeName !== '' ? $storeName . ' daily sales with and without Advantage Card' : 'Daily sales with and without Advantage Card', ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="documents.php" class="btn btn-outline-primary d-inline-flex align-items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-receipt" viewBox="0 0 16 16">
                            <path d="M1.92.556a.5.5 0 0 1 .58.042l1.378 1.148 1.379-1.148a.5.5 0 0 1 .632 0l1.378 1.148 1.379-1.148a.5.5 0 0 1 .632 0l1.378 1.148 1.379-1.148a.5.5 0 0 1 .58-.042l.625.313A.5.5 0 0 1 16 1.5v13a.5.5 0 0 1-.724.447l-.625-.313-1.379 1.148a.5.5 0 0 1-.632 0l-1.378-1.148-1.379 1.148a.5.5 0 0 1-.632 0l-1.378-1.148-1.379 1.148a.5.5 0 0 1-.632 0l-1.378-1.148-1.379 1.148a.5.5 0 0 1-.632 0l-1.378-1.148L1.724 15.947A.5.5 0 0 1 1 15.5v-13a.5.5 0 0 1 .276-.447l.625-.313zM2 2.5v11.666l.872-.727a.5.5 0 0 1 .632 0l1.379 1.148 1.378-1.148a.5.5 0 0 1 .632 0l1.379 1.148 1.378-1.148a.5.5 0 0 1 .632 0l1.379 1.148 1.378-1.148a.5.5 0 0 1 .632 0l.872.727V2.5l-.872.727a.5.5 0 0 1-.632 0L11.5 2.079 10.121 3.227a.5.5 0 0 1-.632 0L8.11 2.079 6.731 3.227a.5.5 0 0 1-.632 0L4.721 2.079 3.342 3.227a.5.5 0 0 1-.632 0L2 2.5z"/>
                            <path d="M5.5 4a.5.5 0 0 0 0 1h5a.5.5 0 0 0 0-1h-5zm0 2a.5.5 0 0 0 0 1h5a.5.5 0 0 0 0-1h-5zm0 2a.5.5 0 0 0 0 1h5a.5.5 0 0 0 0-1h-5zm0 2a.5.5 0 0 0 0 1h3a.5.5 0 0 0 0-1h-3z"/>
                        </svg>
                        <span>BA Card Transactions</span>
                    </a>
                    <span class="badge rounded-pill text-bg-primary px-3 py-2" data-total-rows-badge><?php echo number_format($pagination['total_rows']); ?> days</span>
                </div>
            </div>

            <form class="filter-card" method="get" action="index.php" data-filter-form data-api-url="api/sales-summary.php">
                <input type="hidden" name="page" value="1">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label for="store_name" class="form-label">Store</label>
                        <select class="form-select" id="store_name" name="store_name" data-store-name required>
                            <option value="" <?php echo $storeName === '' ? 'selected' : ''; ?>>-- Select Store --</option>
                            <option value="BHDM" <?php echo $storeName === 'BHDM' ? 'selected' : ''; ?>>BHDM</option>
                            <option value="BHDC" <?php echo $storeName === 'BHDC' ? 'selected' : ''; ?>>BHDC</option>
                            <option value="BHDMi" <?php echo $storeName === 'BHDMi' ? 'selected' : ''; ?>>BHDMi</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="date_from" class="form-label">Start Date</label>
                        <input
                            type="date"
                            class="form-control"
                            id="date_from"
                            name="date_from"
                            value="<?php echo htmlentities($dateFrom, ENT_QUOTES, 'UTF-8'); ?>"
                            data-date-from
                        >
                    </div>
                    <div class="col-md-3">
                        <label for="date_to" class="form-label">End Date</label>
                        <input
                            type="date"
                            class="form-control"
                            id="date_to"
                            name="date_to"
                            value="<?php echo htmlentities($dateTo, ENT_QUOTES, 'UTF-8'); ?>"
                            data-date-to
                        >
                    </div>
                    <div class="col-md-3">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1">Apply Filter</button>
                            <a class="btn btn-outline-secondary <?php echo ($dateFrom === '' && $dateTo === '' && $storeName === '') ? 'd-none' : ''; ?>" href="index.php" data-clear-filters>Clear</a>
                        </div>
                    </div>
                </div>
            </form>

            <div data-results-container>
                <?php if ($storeName === ''): ?>
                    <div class="alert alert-info" role="alert">
                        Please select a store and click <strong>Apply Filter</strong> to view the sales summary.
                    </div>
                <?php elseif ($salesSummaryResult['error']): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo htmlentities($salesSummaryResult['error'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php elseif (empty($salesSummaryResult['rows'])): ?>
                    <div class="alert alert-info" role="alert">
                        No records found for the selected criteria.
                    </div>
                <?php else: ?>
                    <section class="table-card">
                        <div class="table-card-header d-flex align-items-center justify-content-between">
                            <div>
                                <h2 class="h6 mb-0">Daily Sales Breakdown</h2>
                                <span class="text-body-secondary">
                                    Showing <?php echo number_format($pagination['start_row']); ?>-<?php echo number_format($pagination['end_row']); ?>
                                    of <?php echo number_format($pagination['total_rows']); ?>
                                    <?php if ($dateFrom !== '' || $dateTo !== ''): ?>
                                        <?php if ($dateFrom !== '' && $dateTo !== ''): ?>
                                            from <?php echo htmlentities($dateFrom, ENT_QUOTES, 'UTF-8'); ?> to <?php echo htmlentities($dateTo, ENT_QUOTES, 'UTF-8'); ?>
                                        <?php elseif ($dateFrom !== ''): ?>
                                            from <?php echo htmlentities($dateFrom, ENT_QUOTES, 'UTF-8'); ?>
                                        <?php else: ?>
                                            until <?php echo htmlentities($dateTo, ENT_QUOTES, 'UTF-8'); ?>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-success dropdown-toggle d-inline-flex align-items-center gap-1" type="button" id="exportDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-download" viewBox="0 0 16 16">
                                        <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/>
                                        <path d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z"/>
                                    </svg>
                                    Export Table
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="exportDropdown">
                                    <li>
                                        <a class="dropdown-item d-flex align-items-center gap-2" href="api/export-sales-summary.php?<?php echo http_build_query(array('format' => 'xlsx', 'store_name' => $storeName, 'date_from' => $dateFrom, 'date_to' => $dateTo)); ?>" data-export-xlsx download>
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-earmark-excel text-success" viewBox="0 0 16 16">
                                                <path d="M5.884 6.68a.5.5 0 1 0-.768.64L7.349 10l-2.233 2.68a.5.5 0 0 0 .768.64L8 10.781l2.116 2.54a.5.5 0 0 0 .768-.641L8.651 10l2.233-2.68a.5.5 0 0 0-.768-.64L8 9.219l-2.116-2.54z"/>
                                                <path d="M14 14V4.5L9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2zM9.5 3A1.5 1.5 0 0 0 11 4.5h2V14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h5.5v2z"/>
                                            </svg>
                                            Excel Document (.xlsx)
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item d-flex align-items-center gap-2" href="api/export-sales-summary.php?<?php echo http_build_query(array('format' => 'csv', 'store_name' => $storeName, 'date_from' => $dateFrom, 'date_to' => $dateTo)); ?>" data-export-csv download>
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-earmark-text text-primary" viewBox="0 0 16 16">
                                                <path d="M5.5 7a.5.5 0 0 0 0 1h5a.5.5 0 0 0 0-1h-5zM5 9.5a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5zm0 2a.5.5 0 0 1 .5-.5h2a.5.5 0 0 1 0 1h-2a.5.5 0 0 1-.5-.5z"/>
                                                <path d="M9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V4.5L9.5 0zm0 1v2A1.5 1.5 0 0 0 11 4.5h2V14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h5.5z"/>
                                            </svg>
                                            CSV Document (.csv)
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <?php if ($totalPages > 1): ?>
                            <?php renderPaginationBar($currentPage, $totalPages, $startPage, $endPage, array('store_name' => $storeName, 'date_from' => $dateFrom, 'date_to' => $dateTo), 'Sales summary pagination'); ?>
                        <?php endif; ?>

                        <div class="table-scroll">
                            <table class="table table-hover align-middle table-sm" id="salesSummaryTable">
                                <thead>
                                    <tr>
                                        <?php foreach (array_keys($salesSummaryResult['rows'][0]) as $column): ?>
                                            <th scope="col" class="<?php echo isAmountColumn($column) ? 'text-end' : ''; ?>">
                                                <?php echo htmlentities($column, ENT_QUOTES, 'UTF-8'); ?>
                                            </th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($salesSummaryResult['rows'] as $row): ?>
                                        <tr>
                                            <?php foreach ($row as $column => $value): ?>
                                                <td class="<?php echo isAmountColumn($column) ? 'text-end font-monospace' : ''; ?>">
                                                    <?php echo htmlentities((string) $value, ENT_QUOTES, 'UTF-8'); ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <?php if (!empty($salesSummaryResult['totals'])): ?>
                                    <?php $totals = $salesSummaryResult['totals']; ?>
                                    <tfoot>
                                        <tr class="fw-bold">
                                            <td class="text-uppercase">Total</td>
                                            <td></td>
                                            <td class="text-end font-monospace"><?php echo htmlentities($totals['SALES WITH BA'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="text-end font-monospace"><?php echo htmlentities($totals['TC WITH BA'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="text-end font-monospace"><?php echo htmlentities($totals['AC WITH BA'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="text-end font-monospace"><?php echo htmlentities($totals['SALES W/O BA'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="text-end font-monospace"><?php echo htmlentities($totals['TC W/O BA'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="text-end font-monospace"><?php echo htmlentities($totals['AC W/O BA'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        </tr>
                                    </tfoot>
                                <?php endif; ?>
                            </table>
                        </div>

                        <?php if ($totalPages > 1): ?>
                            <?php renderPaginationBar($currentPage, $totalPages, $startPage, $endPage, array('store_name' => $storeName, 'date_from' => $dateFrom, 'date_to' => $dateTo), 'Sales summary pagination'); ?>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>
            </div>
        </main>

        <?php
        $modalId = 'exportLoadingModal';
        $modalTitle = 'Exporting table';
        $modalSubtitle = 'Please wait while your document is being generated...';
        require __DIR__ . '/components/loading-modal.php';

        $modalId = 'filterLoadingModal';
        $modalTitle = 'Filtering records';
        $modalSubtitle = 'Please wait while fetching records...';
        $titleId = 'loadingModalTitle';
        $subtitleId = 'loadingModalSubtitle';
        require __DIR__ . '/components/loading-modal.php';
        ?>

        <?php
        $pageScripts = array('public/js/app.js?v=20261020');
        require __DIR__ . '/components/footer.php';
        ?>
