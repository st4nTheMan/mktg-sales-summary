<?php
$pageTitle = 'Document Transactions - Marketing Report';
require __DIR__ . '/components/header.php';
?>
        <main class="page-shell">
            <?php
            require_once __DIR__ . '/../helpers/pagination.php';

            $pagination = $documentResult['pagination'];
            $currentPage = $pagination['current_page'];
            $totalPages = $pagination['total_pages'];
            $search = $documentResult['search'];
            $storeName = $documentResult['store_name'];
            $dateFrom = $documentResult['date_from'];
            $dateTo = $documentResult['date_to'];

            $startPage = max(1, $currentPage - 2);
            $endPage = min($totalPages, $currentPage + 2);

            ?>

            <div class="page-header d-flex align-items-center justify-content-between">
                <div>
                    <p class="eyebrow mb-2">Marketing Report</p>
                    <h1 class="h3 mb-1">BA Card Transactions</h1>
                    <p class="text-body-secondary mb-0">Detailed list of POS documents and customer details</p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="index.php" class="btn btn-outline-primary d-inline-flex align-items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-left" viewBox="0 0 16 16">
                            <path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8z"/>
                        </svg>
                        <span>Sales Summary</span>
                    </a>
                    <span class="badge rounded-pill text-bg-primary px-3 py-2"><?php echo number_format($pagination['total_rows']); ?> records</span>
                </div>
            </div>

            <form class="filter-card" method="get" action="" id="documentFilterForm">
                <input type="hidden" name="page" value="1">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label for="search" class="form-label">Search</label>
                        <input
                            type="text"
                            class="form-control"
                            id="search"
                            name="search"
                            placeholder="Doc No, Customer ID, Name..."
                            value="<?php echo htmlentities($search, ENT_QUOTES, 'UTF-8'); ?>"
                        >
                    </div>
                    <div class="col-md-3">
                        <label for="store_name" class="form-label">Store</label>
                        <select class="form-select" id="store_name" name="store_name">
                            <option value="" <?php echo $storeName === '' ? 'selected' : ''; ?>>All Stores</option>
                            <option value="BHDM" <?php echo $storeName === 'BHDM' ? 'selected' : ''; ?>>BHDM</option>
                            <option value="BHDC" <?php echo $storeName === 'BHDC' ? 'selected' : ''; ?>>BHDC</option>
                            <option value="BHDMi" <?php echo $storeName === 'BHDMi' ? 'selected' : ''; ?>>BHDMi</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="date_from" class="form-label">Start Date</label>
                        <input
                            type="date"
                            class="form-control"
                            id="date_from"
                            name="date_from"
                            value="<?php echo htmlentities($dateFrom, ENT_QUOTES, 'UTF-8'); ?>"
                        >
                    </div>
                    <div class="col-md-2">
                        <label for="date_to" class="form-label">End Date</label>
                        <input
                            type="date"
                            class="form-control"
                            id="date_to"
                            name="date_to"
                            value="<?php echo htmlentities($dateTo, ENT_QUOTES, 'UTF-8'); ?>"
                        >
                    </div>
                    <div class="col-md-2">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1">Apply Filter</button>
                            <a class="btn btn-outline-secondary <?php echo (!$documentResult['is_filtered']) ? 'd-none' : ''; ?>" href="?">Clear</a>
                        </div>
                    </div>
                </div>
            </form>

            <?php if (!$documentResult['is_filtered']): ?>
                <div class="alert alert-info" role="alert">
                    Please apply a filter and click <strong>Apply Filter</strong> to view document transactions.
                </div>
            <?php elseif (!empty($documentResult['error'])): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo htmlentities($documentResult['error'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php elseif (empty($documentResult['rows'])): ?>
                <div class="alert alert-info" role="alert">
                    No document records found for the selected criteria.
                </div>
            <?php else: ?>
                <section class="table-card">
                    <div class="table-card-header d-flex align-items-center justify-content-between">
                        <div>
                            <h2 class="h6 mb-0">Document List</h2>
                            <span class="text-body-secondary">
                                Showing <?php echo number_format($pagination['start_row']); ?>-<?php echo number_format($pagination['end_row']); ?>
                                of <?php echo number_format($pagination['total_rows']); ?>
                                <?php if ($search !== ''): ?>
                                    matching "<?php echo htmlentities($search, ENT_QUOTES, 'UTF-8'); ?>"
                                <?php endif; ?>
                                <?php if ($storeName !== ''): ?>
                                    in <?php echo htmlentities($storeName, ENT_QUOTES, 'UTF-8'); ?>
                                <?php endif; ?>
                                <?php if ($dateFrom !== '' && $dateTo !== ''): ?>
                                    from <?php echo htmlentities($dateFrom, ENT_QUOTES, 'UTF-8'); ?> to <?php echo htmlentities($dateTo, ENT_QUOTES, 'UTF-8'); ?>
                                <?php elseif ($dateFrom !== ''): ?>
                                    from <?php echo htmlentities($dateFrom, ENT_QUOTES, 'UTF-8'); ?>
                                <?php elseif ($dateTo !== ''): ?>
                                    until <?php echo htmlentities($dateTo, ENT_QUOTES, 'UTF-8'); ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-success dropdown-toggle d-inline-flex align-items-center gap-1" type="button" id="exportDocDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-download" viewBox="0 0 16 16">
                                    <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/>
                                    <path d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z"/>
                                </svg>
                                Export Table
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="exportDocDropdown">
                                <li>
                                    <a class="dropdown-item d-flex align-items-center gap-2" href="api/export-documents.php?<?php echo http_build_query(array('format' => 'xlsx', 'search' => $search, 'store_name' => $storeName, 'date_from' => $dateFrom, 'date_to' => $dateTo)); ?>" download>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-earmark-excel text-success" viewBox="0 0 16 16">
                                            <path d="M5.884 6.68a.5.5 0 1 0-.768.64L7.349 10l-2.233 2.68a.5.5 0 0 0 .768.64L8 10.781l2.116 2.54a.5.5 0 0 0 .768-.641L8.651 10l2.233-2.68a.5.5 0 0 0-.768-.64L8 9.219l-2.116-2.54z"/>
                                            <path d="M14 14V4.5L9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2zM9.5 3A1.5 1.5 0 0 0 11 4.5h2V14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h5.5v2z"/>
                                        </svg>
                                        Excel Document (.xlsx)
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item d-flex align-items-center gap-2" href="api/export-documents.php?<?php echo http_build_query(array('format' => 'csv', 'search' => $search, 'store_name' => $storeName, 'date_from' => $dateFrom, 'date_to' => $dateTo)); ?>" download>
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

                    <?php if ($pagination['total_rows'] > 0): ?>
                        <?php renderPaginationBar($currentPage, $totalPages, $startPage, $endPage, array('search' => $search, 'store_name' => $storeName, 'date_from' => $dateFrom, 'date_to' => $dateTo), 'Document pagination'); ?>
                    <?php endif; ?>

                    <div class="table-scroll">
                        <table class="table table-hover align-middle table-sm">
                            <thead>
                                <tr>
                                    <?php foreach (array_keys($documentResult['rows'][0]) as $column): ?>
                                        <?php if (strcasecmp($column, 'sid') === 0) continue; ?>
                                        <th scope="col" class="<?php echo (strcasecmp($column, 'TRANSACTION_TOTAL_AMT') === 0 || in_array(strtoupper($column), array('LOYALTY POINTS', 'LOYALTY_POINTS', 'LTY_BALANCE'))) ? 'text-end' : ''; ?>">
                                            <?php echo htmlentities(getDocumentColumnLabel($column), ENT_QUOTES, 'UTF-8'); ?>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($documentResult['rows'] as $row): ?>
                                    <tr>
                                        <?php foreach ($row as $column => $value): ?>
                                            <?php if (strcasecmp($column, 'sid') === 0) continue; ?>
                                            <?php if (strcasecmp($column, 'TRANSACTION_TOTAL_AMT') === 0 || in_array(strtoupper($column), array('LOYALTY POINTS', 'LOYALTY_POINTS', 'LTY_BALANCE'))): ?>
                                                <td class="text-end font-monospace"><?php echo htmlentities((string) $value, ENT_QUOTES, 'UTF-8'); ?></td>
                                            <?php elseif (strcasecmp($column, 'DOC_NO') === 0): ?>
                                                <td class="font-monospace"><a href="http://localhost/prism-inquiryy/hwoc/invoice.php?id=<?php echo rawurlencode((string) $row['SID']); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlentities((string) $value, ENT_QUOTES, 'UTF-8'); ?></a></td>
                                            <?php elseif (in_array(strtoupper($column), array('CUST_ID', 'BA CARD NO.', 'BA_CARD_NO', 'CREATED_DATETIME', 'CONTACT NUMBER', 'PHONE_NO'))): ?>
                                                <td class="font-monospace"><?php echo htmlentities((string) $value, ENT_QUOTES, 'UTF-8'); ?></td>
                                            <?php else: ?>
                                                <td><?php echo htmlentities((string) $value, ENT_QUOTES, 'UTF-8'); ?></td>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($pagination['total_rows'] > 0): ?>
                        <?php renderPaginationBar($currentPage, $totalPages, $startPage, $endPage, array('search' => $search, 'store_name' => $storeName, 'date_from' => $dateFrom, 'date_to' => $dateTo), 'Document pagination'); ?>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </main>

        <?php
        $modalId = 'filterLoadingModal';
        $modalTitle = 'Filtering records';
        $modalSubtitle = 'Please wait while fetching records...';
        $titleId = 'loadingModalTitle';
        $subtitleId = 'loadingModalSubtitle';
        require __DIR__ . '/components/loading-modal.php';
        ?>

        <?php
        $pageScripts = array();
        require __DIR__ . '/components/footer.php';
        ?>
