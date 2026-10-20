document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('[data-filter-form]');
    const storeNameInput = document.querySelector('[data-store-name]');
    const dateFromInput = document.querySelector('[data-date-from]');
    const dateToInput = document.querySelector('[data-date-to]');
    const clearButton = document.querySelector('[data-clear-filters]');
    const resultsContainer = document.querySelector('[data-results-container]');
    const totalRowsBadge = document.querySelector('[data-total-rows-badge]');
    if (!form || !dateFromInput || !dateToInput || !resultsContainer || !totalRowsBadge) {
        return;
    }

    const apiUrl = form.getAttribute('data-api-url');
    let activeRequest = null;

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        loadSalesSummary(1);
    });

    if (clearButton) {
        clearButton.addEventListener('click', function (event) {
            event.preventDefault();
            if (storeNameInput) {
                storeNameInput.value = '';
            }
            dateFromInput.value = '';
            dateToInput.value = '';
            loadSalesSummary(1, false);
        });
    }

    resultsContainer.addEventListener('click', function (event) {
        const link = event.target.closest('.pagination .page-link');

        if (!link || link.closest('.page-item').classList.contains('disabled')) {
            return;
        }

        event.preventDefault();

        const page = new URL(link.href, window.location.href).searchParams.get('page') || '1';
        loadSalesSummary(page);
    });

    document.addEventListener('click', function (event) {
        const exportBtn = event.target.closest('[data-export-xlsx], [data-export-csv]');
        if (!exportBtn) {
            return;
        }

        event.preventDefault();
        const url = exportBtn.getAttribute('href');
        if (!url) {
            return;
        }

        showExportLoading();

        fetch(url)
            .then(function (response) {
                if (!response.ok) {
                    return response.text().then(function (text) {
                        throw new Error(text || 'Unable to generate export file.');
                    });
                }

                const disposition = response.headers.get('Content-Disposition');
                let filename = 'sales_summary_export';
                if (disposition && disposition.indexOf('filename=') !== -1) {
                    const matches = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/.exec(disposition);
                    if (matches != null && matches[1]) {
                        filename = matches[1].replace(/['"]/g, '');
                    }
                }

                return response.blob().then(function (blob) {
                    return { blob: blob, filename: filename };
                });
            })
            .then(function (resData) {
                const blobUrl = URL.createObjectURL(resData.blob);
                const a = document.createElement('a');
                a.href = blobUrl;
                a.download = resData.filename;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                setTimeout(function () {
                    URL.revokeObjectURL(blobUrl);
                }, 150);
            })
            .catch(function (err) {
                alert(err.message || 'An error occurred while exporting.');
            })
            .finally(function () {
                hideExportLoading();
            });
    });

    let exportLoadingStartTime = 0;
    let exportModalInstance = null;

    function showExportLoading() {
        exportLoadingStartTime = Date.now();
        const modalEl = document.getElementById('exportLoadingModal');

        if (modalEl) {
            if (window.bootstrap && window.bootstrap.Modal) {
                exportModalInstance = window.bootstrap.Modal.getOrCreateInstance(modalEl);
                exportModalInstance.show();
            } else {
                modalEl.classList.add('show');
                modalEl.style.display = 'block';
            }
        }
    }

    function hideExportLoading() {
        const elapsedTime = Date.now() - exportLoadingStartTime;
        const minDuration = 800;
        const remainingTime = Math.max(0, minDuration - elapsedTime);

        setTimeout(function () {
            const modalEl = document.getElementById('exportLoadingModal');
            if (exportModalInstance) {
                exportModalInstance.hide();
            } else if (modalEl && window.bootstrap && window.bootstrap.Modal) {
                const inst = window.bootstrap.Modal.getInstance(modalEl);
                if (inst) {
                    inst.hide();
                }
            } else if (modalEl) {
                modalEl.classList.remove('show');
                modalEl.style.display = 'none';
            }
        }, remainingTime);
    }

    function loadSalesSummary(page, shouldShowLoading) {
        shouldShowLoading = shouldShowLoading !== false;
        const params = new URLSearchParams();
        params.set('page', page);

        if (storeNameInput && storeNameInput.value.trim() !== '') {
            params.set('store_name', storeNameInput.value.trim());
        }

        if (dateFromInput.value.trim() !== '') {
            params.set('date_from', dateFromInput.value.trim());
        }

        if (dateToInput.value.trim() !== '') {
            params.set('date_to', dateToInput.value.trim());
        }

        if (activeRequest) {
            activeRequest.abort();
        }

        activeRequest = new AbortController();
        const requestController = activeRequest;
        resultsContainer.classList.add('is-loading');
        if (shouldShowLoading) {
            showFilterLoading();
        } else {
            hideFilterLoading();
        }

        fetch(apiUrl + '?' + params.toString(), {
            headers: {
                Accept: 'application/json',
            },
            signal: activeRequest.signal,
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Unable to load sales summary.');
                }

                return response.json();
            })
            .then(function (data) {
                renderResults(data);
                updateBrowserUrl(data.pagination.current_page, data.date_from, data.date_to, data.store_name);
                updateClearButton(data.date_from, data.date_to, data.store_name);
            })
            .catch(function (error) {
                if (error.name === 'AbortError') {
                    return;
                }

                resultsContainer.innerHTML = renderError(error.message);
            })
            .finally(function () {
                if (activeRequest === requestController) {
                    resultsContainer.classList.remove('is-loading');
                    if (shouldShowLoading) {
                        hideFilterLoading();
                    }
                }
            });
    }

    function showFilterLoading() {
        if (window.AppLoadingModal) {
            window.AppLoadingModal.show();
            return;
        }

        const modalEl = document.getElementById('filterLoadingModal');
        if (modalEl && window.bootstrap && window.bootstrap.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
        } else if (modalEl) {
            modalEl.classList.add('show');
            modalEl.style.display = 'block';
        }
    }

    function hideFilterLoading() {
        const modalEl = document.getElementById('filterLoadingModal');
        if (modalEl && window.bootstrap && window.bootstrap.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        } else if (modalEl) {
            modalEl.classList.remove('show');
            modalEl.style.display = 'none';
        } else if (window.AppLoadingModal) {
            window.AppLoadingModal.hide();
        }
    }

    function renderResults(data) {
        const pagination = data.pagination;
        const rows = data.rows || [];

        totalRowsBadge.textContent = formatNumber(pagination.total_rows) + ' days';

        if (!data.store_name) {
            resultsContainer.innerHTML =
                '<div class="alert alert-info" role="alert">Please select a store and click <strong>Apply Filter</strong> to view the sales summary.</div>';
            return;
        }

        if (data.error) {
            resultsContainer.innerHTML = renderError(data.error);
            return;
        }

        if (rows.length === 0) {
            resultsContainer.innerHTML =
                '<div class="alert alert-info" role="alert">No records found for the selected criteria.</div>';
            return;
        }

        const columns = Object.keys(rows[0]);
        const startPage = Math.max(1, pagination.current_page - 2);
        const endPage = Math.min(pagination.total_pages, pagination.current_page + 2);
        const rangeLabel = buildRangeLabel(data.date_from, data.date_to);

        const exportXlsxParams = new URLSearchParams({ format: 'xlsx' });
        const exportCsvParams = new URLSearchParams({ format: 'csv' });
        if (data.store_name) {
            exportXlsxParams.set('store_name', data.store_name);
            exportCsvParams.set('store_name', data.store_name);
        }
        if (data.date_from) {
            exportXlsxParams.set('date_from', data.date_from);
            exportCsvParams.set('date_from', data.date_from);
        }
        if (data.date_to) {
            exportXlsxParams.set('date_to', data.date_to);
            exportCsvParams.set('date_to', data.date_to);
        }
        const exportXlsxUrl = 'api/export-sales-summary.php?' + exportXlsxParams.toString();
        const exportCsvUrl = 'api/export-sales-summary.php?' + exportCsvParams.toString();

        resultsContainer.innerHTML = `
            <section class="table-card">
                <div class="table-card-header d-flex align-items-center justify-content-between">
                    <div>
                        <h2 class="h6 mb-0">Daily Sales Breakdown</h2>
                        <span class="text-body-secondary">
                            Showing ${formatNumber(pagination.start_row)}-${formatNumber(pagination.end_row)}
                            of ${formatNumber(pagination.total_rows)}
                            ${rangeLabel}
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
                                <a class="dropdown-item d-flex align-items-center gap-2" href="${escapeHtml(exportXlsxUrl)}" data-export-xlsx download>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-earmark-excel text-success" viewBox="0 0 16 16">
                                        <path d="M5.884 6.68a.5.5 0 1 0-.768.64L7.349 10l-2.233 2.68a.5.5 0 0 0 .768.64L8 10.781l2.116 2.54a.5.5 0 0 0 .768-.641L8.651 10l2.233-2.68a.5.5 0 0 0-.768-.64L8 9.219l-2.116-2.54z"/>
                                        <path d="M14 14V4.5L9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2zM9.5 3A1.5 1.5 0 0 0 11 4.5h2V14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h5.5v2z"/>
                                    </svg>
                                    Excel Document (.xlsx)
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center gap-2" href="${escapeHtml(exportCsvUrl)}" data-export-csv download>
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

                ${pagination.total_pages > 1 ? renderPaginationBar(pagination.current_page, pagination.total_pages, startPage, endPage, data.date_from, data.date_to, data.store_name) : ''}

                <div class="table-scroll">
                    <table class="table table-hover align-middle table-sm" id="salesSummaryTable">
                        <thead>
                            <tr>
                                ${columns
                                    .map(function (column) {
                                        return `<th scope="col" class="${isAmountColumn(column) ? 'text-end' : ''}">${escapeHtml(column)}</th>`;
                                    })
                                    .join('')}
                            </tr>
                        </thead>
                        <tbody>
                            ${rows
                                .map(function (row) {
                                    return `
                                        <tr>
                                            ${columns
                                                .map(function (column) {
                                                    return `<td class="${isAmountColumn(column) ? 'text-end font-monospace' : ''}">${escapeHtml(row[column])}</td>`;
                                                })
                                                .join('')}
                                        </tr>
                                    `;
                                })
                                .join('')}
                        </tbody>
                        ${
                            data.totals
                                ? `
                            <tfoot>
                                <tr class="fw-bold">
                                    <td class="text-uppercase">Total</td>
                                    <td></td>
                                    <td class="text-end font-monospace">${escapeHtml(data.totals['SALES WITH BA'])}</td>
                                    <td class="text-end font-monospace">${escapeHtml(data.totals['TC WITH BA'])}</td>
                                    <td class="text-end font-monospace">${escapeHtml(data.totals['AC WITH BA'])}</td>
                                    <td class="text-end font-monospace">${escapeHtml(data.totals['SALES W/O BA'])}</td>
                                    <td class="text-end font-monospace">${escapeHtml(data.totals['TC W/O BA'])}</td>
                                    <td class="text-end font-monospace">${escapeHtml(data.totals['AC W/O BA'])}</td>
                                </tr>
                            </tfoot>
                        `
                                : ''
                        }
                    </table>
                </div>

                ${pagination.total_pages > 1 ? renderPaginationBar(pagination.current_page, pagination.total_pages, startPage, endPage, data.date_from, data.date_to, data.store_name) : ''}
            </section>
        `;
    }

    function renderPaginationBar(currentPage, totalPages, startPage, endPage, dateFrom, dateTo, storeName) {
        let pageLinks = '';

        for (let page = startPage; page <= endPage; page++) {
            pageLinks += `
                <li class="page-item ${page === currentPage ? 'active' : ''}">
                    <a class="page-link" href="${escapeHtml(buildPageUrl(page, dateFrom, dateTo, storeName))}">${page}</a>
                </li>
            `;
        }

        return `
            <div class="pagination-bar">
                <div class="text-body-secondary small">
                    Page ${formatNumber(currentPage)} of ${formatNumber(totalPages)}
                </div>

                <nav aria-label="Sales summary pagination">
                    <ul class="pagination mb-0">
                        <li class="page-item ${currentPage <= 1 ? 'disabled' : ''}">
                            <a class="page-link" href="${escapeHtml(buildPageUrl(Math.max(1, currentPage - 1), dateFrom, dateTo, storeName))}">Previous</a>
                        </li>
                        ${pageLinks}
                        <li class="page-item ${currentPage >= totalPages ? 'disabled' : ''}">
                            <a class="page-link" href="${escapeHtml(buildPageUrl(Math.min(totalPages, currentPage + 1), dateFrom, dateTo, storeName))}">Next</a>
                        </li>
                    </ul>
                </nav>
            </div>
        `;
    }

    function buildRangeLabel(dateFrom, dateTo) {
        if (!dateFrom && !dateTo) {
            return '';
        }

        if (dateFrom && dateTo) {
            return `from ${escapeHtml(dateFrom)} to ${escapeHtml(dateTo)}`;
        }

        if (dateFrom) {
            return `from ${escapeHtml(dateFrom)}`;
        }

        return `until ${escapeHtml(dateTo)}`;
    }

    function renderError(message) {
        return `<div class="alert alert-danger" role="alert">${escapeHtml(message)}</div>`;
    }

    function buildPageUrl(page, dateFrom, dateTo, storeName) {
        const params = new URLSearchParams();
        params.set('page', page);

        if (storeName) {
            params.set('store_name', storeName);
        }

        if (dateFrom) {
            params.set('date_from', dateFrom);
        }

        if (dateTo) {
            params.set('date_to', dateTo);
        }

        return '?' + params.toString();
    }

    function updateBrowserUrl(page, dateFrom, dateTo, storeName) {
        window.history.replaceState({}, '', buildPageUrl(page, dateFrom, dateTo, storeName));
    }

    function updateClearButton(dateFrom, dateTo, storeName) {
        if (!clearButton) {
            return;
        }

        clearButton.classList.toggle('d-none', !dateFrom && !dateTo && !storeName);
    }

    function isAmountColumn(column) {
        const upper = String(column).toUpperCase();
        return upper.indexOf('SALES') !== -1 || upper.indexOf('AC ') !== -1 || upper.indexOf('TC ') !== -1;
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatNumber(value) {
        return Number(value || 0).toLocaleString();
    }
});
