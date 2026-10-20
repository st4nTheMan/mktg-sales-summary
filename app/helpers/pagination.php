<?php

function buildPaginationUrl($page, array $filters)
{
    $params = array('page' => $page);

    foreach ($filters as $name => $value) {
        if ($value !== '') {
            $params[$name] = $value;
        }
    }

    return '?' . http_build_query($params);
}

function renderPaginationBar($currentPage, $totalPages, $startPage, $endPage, array $filters, $ariaLabel)
{
    ?>
    <div class="pagination-bar">
        <div class="text-body-secondary small">
            Page <?php echo number_format($currentPage); ?> of <?php echo number_format($totalPages); ?>
        </div>

        <nav aria-label="<?php echo htmlentities($ariaLabel, ENT_QUOTES, 'UTF-8'); ?>">
            <ul class="pagination mb-0">
                <li class="page-item <?php echo $currentPage <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="<?php echo htmlentities(buildPaginationUrl(max(1, $currentPage - 1), $filters), ENT_QUOTES, 'UTF-8'); ?>">Previous</a>
                </li>

                <?php for ($page = $startPage; $page <= $endPage; $page++): ?>
                    <li class="page-item <?php echo $page === $currentPage ? 'active' : ''; ?>">
                        <a class="page-link" href="<?php echo htmlentities(buildPaginationUrl($page, $filters), ENT_QUOTES, 'UTF-8'); ?>"><?php echo $page; ?></a>
                    </li>
                <?php endfor; ?>

                <li class="page-item <?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="<?php echo htmlentities(buildPaginationUrl(min($totalPages, $currentPage + 1), $filters), ENT_QUOTES, 'UTF-8'); ?>">Next</a>
                </li>
            </ul>
        </nav>
    </div>
    <?php
}
