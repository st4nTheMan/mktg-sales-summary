<?php

$modalId = isset($modalId) ? $modalId : 'loadingModal';
$modalTitle = isset($modalTitle) ? $modalTitle : 'Loading';
$modalSubtitle = isset($modalSubtitle) ? $modalSubtitle : 'Please wait...';
$titleId = isset($titleId) ? $titleId : '';
$subtitleId = isset($subtitleId) ? $subtitleId : '';

?>
<div
    class="modal fade"
    id="<?php echo htmlentities($modalId, ENT_QUOTES, 'UTF-8'); ?>"
    tabindex="-1"
    data-bs-backdrop="static"
    data-bs-keyboard="false"
    aria-hidden="true"
>
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content text-center p-4 border-0 shadow-lg" style="border-radius: 16px;">
            <div class="spinner-border text-primary mx-auto mb-3" role="status" style="width: 3.2rem; height: 3.2rem; border-width: 0.25em;">
                <span class="visually-hidden">Loading...</span>
            </div>
            <h5 class="fw-bold text-dark mb-1"<?php echo $titleId !== '' ? ' id="' . htmlentities($titleId, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>><?php echo htmlentities($modalTitle, ENT_QUOTES, 'UTF-8'); ?></h5>
            <p class="text-secondary small mb-0"<?php echo $subtitleId !== '' ? ' id="' . htmlentities($subtitleId, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>><?php echo htmlentities($modalSubtitle, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.loadingModalBehaviorInitialized) {
        return;
    }
    window.loadingModalBehaviorInitialized = true;

    var modalEl = document.getElementById('filterLoadingModal');
    if (!modalEl) {
        return;
    }

    var titleEl = document.getElementById('loadingModalTitle');
    var subtitleEl = document.getElementById('loadingModalSubtitle');
    var loadingModal = window.bootstrap && window.bootstrap.Modal
        ? window.bootstrap.Modal.getOrCreateInstance(modalEl)
        : null;
    var filterForm = document.getElementById('documentFilterForm') || document.querySelector('[data-filter-form]');
    var isDocumentPage = Boolean(document.getElementById('documentFilterForm'));

    function showModal() {
        if (loadingModal) {
            loadingModal.show();
        } else {
            modalEl.classList.add('show');
            modalEl.style.display = 'block';
        }
    }

    function setModalText(title, subtitle) {
        if (titleEl) titleEl.textContent = title;
        if (subtitleEl) subtitleEl.textContent = subtitle;
    }

    function hideModal() {
        if (loadingModal) {
            loadingModal.hide();
        } else {
            modalEl.classList.remove('show');
            modalEl.style.display = 'none';
        }
    }

    window.AppLoadingModal = {
        show: showModal,
        hide: hideModal
    };

    if (filterForm && isDocumentPage) {
        filterForm.addEventListener('submit', function () {
            setModalText('Filtering records', 'Please wait while fetching records...');
            showModal();
        });
    }

    if (isDocumentPage) {
        document.addEventListener('click', function (event) {
            var link = event.target.closest('.pagination .page-link');
            if (link && !link.closest('.page-item').classList.contains('disabled')) {
                setModalText('Loading page', 'Please wait while fetching records...');
                showModal();
            }
        });

        document.addEventListener('click', function (event) {
            var exportBtn = event.target.closest('.dropdown-menu a[download]');
            if (exportBtn) {
                setModalText('Exporting table', 'Please wait while your document is being generated...');
                showModal();
                window.setTimeout(hideModal, 2500);
            }
        });
    }
});
</script>
