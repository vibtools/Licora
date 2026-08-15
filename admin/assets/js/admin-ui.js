(function () {
    'use strict';

    document.documentElement.setAttribute('data-theme', 'light');
    document.documentElement.classList.remove('ui-dark');

    function ready(fn) {
        if (document.readyState !== 'loading') fn();
        else document.addEventListener('DOMContentLoaded', fn);
    }

    function toast(message, type) {
        if (!message) return;
        var wrap = document.getElementById('uiToastContainer');
        if (!wrap) {
            wrap = document.createElement('div');
            wrap.id = 'uiToastContainer';
            wrap.className = 'ui-toast-container';
            wrap.setAttribute('aria-live', 'polite');
            document.body.appendChild(wrap);
        }
        var item = document.createElement('div');
        item.className = 'ui-toast is-' + (type || 'info');
        item.setAttribute('role', type === 'danger' ? 'alert' : 'status');
        var icon = type === 'success' ? 'bi-check-circle' : (type === 'danger' ? 'bi-exclamation-circle' : 'bi-info-circle');
        item.innerHTML = '<i class="bi ' + icon + '" aria-hidden="true"></i><span class="ui-toast-message"></span><button type="button" class="ui-toast-close" aria-label="Dismiss"><i class="bi bi-x"></i></button>';
        item.querySelector('.ui-toast-message').textContent = message;
        var close = function () { if (item.parentNode) item.parentNode.removeChild(item); };
        item.querySelector('.ui-toast-close').addEventListener('click', close);
        wrap.appendChild(item);
        setTimeout(close, 3800);
    }

    function copyText(text, btn) {
        if (!text) return;
        var done = function () {
            if (btn) {
                var old = btn.innerHTML;
                btn.innerHTML = '<i class="bi bi-check2"></i>';
                setTimeout(function () { btn.innerHTML = old; }, 1000);
            }
            toast('Copied to clipboard', 'success');
        };
        var failed = function () { toast('Copy failed', 'danger'); };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done).catch(failed);
            return;
        }
        try {
            var area = document.createElement('textarea');
            area.value = text;
            area.setAttribute('readonly', '');
            area.style.position = 'fixed';
            area.style.opacity = '0';
            document.body.appendChild(area);
            area.select();
            var ok = document.execCommand('copy');
            document.body.removeChild(area);
            if (ok) done(); else failed();
        } catch (e) { failed(); }
    }

    function showLoader() {
        var overlay = document.getElementById('uiLoadingOverlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'uiLoadingOverlay';
            overlay.className = 'loading-overlay';
            overlay.innerHTML = '<div class="spinner-card"><div class="spinner-border text-primary" role="status"></div><span>Processing...</span></div>';
            document.body.appendChild(overlay);
        }
        overlay.classList.add('show');
    }

    function setupConfirmModal() {
        var modal = document.getElementById('uiConfirmModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'uiConfirmModal';
            modal.className = 'ui-confirm-backdrop';
            modal.hidden = true;
            modal.innerHTML = '<section class="ui-confirm-card" role="dialog" aria-modal="true" aria-labelledby="uiConfirmTitle"><div class="ui-confirm-head"><h2 class="ui-confirm-title" id="uiConfirmTitle"><i class="bi bi-exclamation-triangle text-warning" aria-hidden="true"></i>Confirm action</h2><button type="button" class="ui-icon-button" data-confirm-close aria-label="Close"><i class="bi bi-x"></i></button></div><div class="ui-confirm-body" id="uiConfirmText">Are you sure?</div><div class="ui-confirm-actions"><button type="button" class="btn btn-outline-secondary" data-confirm-cancel>Cancel</button><button type="button" id="uiConfirmProceed" class="btn btn-danger">Confirm</button></div></section>';
            document.body.appendChild(modal);
        }
        var text = document.getElementById('uiConfirmText');
        var proceed = document.getElementById('uiConfirmProceed');
        var target = null;
        var opener = null;
        function close() {
            modal.hidden = true;
            document.body.style.overflow = '';
            if (opener && typeof opener.focus === 'function') opener.focus();
            opener = null;
            target = null;
        }
        function open(element, message) {
            opener = element;
            target = element;
            text.textContent = message || element.getAttribute('data-confirm') || 'Are you sure?';
            modal.hidden = false;
            document.body.style.overflow = 'hidden';
            proceed.focus();
        }
        document.querySelectorAll('a[data-confirm]').forEach(function (link) {
            link.addEventListener('click', function (e) { e.preventDefault(); open(link); });
        });
        document.querySelectorAll('form[data-confirm]').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                if (form.dataset.confirmAccepted === '1') { delete form.dataset.confirmAccepted; return; }
                e.preventDefault();
                e.stopImmediatePropagation();
                open(form, form.getAttribute('data-confirm'));
            });
        });
        document.querySelectorAll('button[data-confirm]').forEach(function (button) {
            button.addEventListener('click', function (e) {
                e.preventDefault();
                open(button, button.getAttribute('data-confirm'));
            });
        });
        proceed.addEventListener('click', function () {
            if (!target) return close();
            var current = target;
            modal.hidden = true;
            document.body.style.overflow = '';
            target = null;
            if (current.tagName === 'A') {
                window.location.href = current.getAttribute('href') || '#';
                return;
            }
            if (current.tagName === 'FORM') {
                current.dataset.confirmAccepted = '1';
                if (typeof current.requestSubmit === 'function') current.requestSubmit(); else current.submit();
                return;
            }
            if (current.tagName === 'BUTTON') {
                var form = current.form;
                if (form) {
                    form.dataset.confirmAccepted = '1';
                    if (typeof form.requestSubmit === 'function') form.requestSubmit(current); else form.submit();
                } else {
                    current.click();
                }
            }
        });
        modal.querySelectorAll('[data-confirm-close],[data-confirm-cancel]').forEach(function (button) { button.addEventListener('click', close); });
        modal.addEventListener('click', function (e) { if (e.target === modal) close(); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.hidden) close(); });
    }

    function setupDatePickerBridge() {
        var picker = document.getElementById('license-valid-until');
        var hoursInput = document.querySelector('#license-create-single input[name="hours"]') || document.querySelector('input[name="hours"]');
        if (!picker || !hoursInput) return;
        picker.addEventListener('change', function () {
            if (!picker.value) return;
            var target = new Date(picker.value + 'T23:59:59');
            var diff = Math.ceil((target.getTime() - Date.now()) / 3600000);
            if (diff > 0) hoursInput.value = diff;
        });
        document.querySelectorAll('[data-hours-preset]').forEach(function (btn) {
            btn.addEventListener('click', function () { hoursInput.value = btn.getAttribute('data-hours-preset'); });
        });
    }

    function setupLicenseCreateMode() {
        var modal = document.getElementById('licenseCreateModal');
        if (!modal) return;
        modal.querySelectorAll('[data-license-create-mode]').forEach(function (button) {
            button.addEventListener('click', function () {
                var mode = button.getAttribute('data-license-create-mode');
                modal.querySelectorAll('[data-license-create-mode]').forEach(function (b) { b.classList.toggle('active', b === button); });
                modal.querySelectorAll('[data-license-create-panel]').forEach(function (panel) { panel.hidden = panel.getAttribute('data-license-create-panel') !== mode; });
            });
        });
        try {
            var params = new URLSearchParams(window.location.search);
            if (params.get('action') === 'create' && window.bootstrap && bootstrap.Modal) {
                new bootstrap.Modal(modal).show();
            }
        } catch (ignore) {}
    }

    function setupLicenseTable() {
        var table = document.getElementById('license-table');
        if (!table) return;
        var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr[data-status]'));
        var statusFilter = document.getElementById('license-status-filter');
        var dateFilter = document.getElementById('license-date-filter');
        var pageSizeSelect = document.getElementById('license-page-size');
        var pager = document.getElementById('license-pagination');
        var empty = document.getElementById('license-empty-state');
        var bulkBar = document.getElementById('license-bulk-bar');
        var selectedCount = document.getElementById('license-selected-count');
        var checkAll = document.getElementById('license-check-all');
        var count = document.getElementById('license-visible-count');
        var currentPage = 1;

        function matches(row) {
            var status = statusFilter ? statusFilter.value : '';
            var date = dateFilter ? dateFilter.value : '';
            if (status && row.getAttribute('data-status') !== status) return false;
            if (date && row.getAttribute('data-created') !== date) return false;
            return true;
        }
        function updateBulk() {
            var checked = table.querySelectorAll('tbody input[type="checkbox"]:checked').length;
            if (bulkBar) bulkBar.classList.toggle('is-visible', checked > 0);
            if (selectedCount) selectedCount.textContent = checked;
        }
        function render() {
            var pageSize = pageSizeSelect ? parseInt(pageSizeSelect.value, 10) : 10;
            var filtered = rows.filter(matches);
            var totalPages = Math.max(1, Math.ceil(filtered.length / pageSize));
            if (currentPage > totalPages) currentPage = totalPages;
            rows.forEach(function (row) { row.style.display = 'none'; });
            filtered.slice((currentPage - 1) * pageSize, currentPage * pageSize).forEach(function (row) { row.style.display = ''; });
            if (empty) empty.hidden = filtered.length > 0;
            if (count) count.textContent = filtered.length + ' of ' + rows.length + ' entries';
            if (pager) {
                pager.innerHTML = '';
                for (var i = 1; i <= totalPages; i++) {
                    var li = document.createElement('li');
                    li.className = 'page-item ' + (i === currentPage ? 'active' : '');
                    li.innerHTML = '<button type="button" class="page-link">' + i + '</button>';
                    (function (page) { li.querySelector('button').addEventListener('click', function () { currentPage = page; render(); }); })(i);
                    pager.appendChild(li);
                }
            }
            updateBulk();
        }
        [statusFilter, dateFilter, pageSizeSelect].forEach(function (control) { if (control) control.addEventListener('change', function () { currentPage = 1; render(); }); });
        if (checkAll) checkAll.addEventListener('change', function () {
            rows.forEach(function (row) {
                if (row.style.display !== 'none') {
                    var box = row.querySelector('input[type="checkbox"]');
                    if (box) box.checked = checkAll.checked;
                }
            });
            updateBulk();
        });
        table.querySelectorAll('tbody input[type="checkbox"]').forEach(function (box) { box.addEventListener('change', updateBulk); });
        render();
    }

    function setupFilterTable(table) {
        if (table.dataset.uiFilterReady === '1') return;
        table.dataset.uiFilterReady = '1';
        var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr[data-ui-search]'));
        var search = document.querySelector('[data-ui-table-search="' + table.id + '"]');
        var status = document.querySelector('[data-ui-table-status="' + table.id + '"]');
        var pageSize = document.querySelector('[data-ui-table-size="' + table.id + '"]');
        var pager = document.querySelector('[data-ui-pager-for="' + table.id + '"]');
        var count = document.querySelector('[data-ui-count-for="' + table.id + '"]');
        var currentPage = 1;
        function filteredRows() {
            var query = search ? search.value.trim().toLowerCase() : '';
            var state = status ? status.value : '';
            return rows.filter(function (row) {
                if (query && (row.getAttribute('data-ui-search') || '').toLowerCase().indexOf(query) === -1) return false;
                if (state && row.getAttribute('data-ui-status') !== state) return false;
                return true;
            });
        }
        function render() {
            var size = pageSize ? parseInt(pageSize.value, 10) : parseInt(table.getAttribute('data-ui-page-size') || '10', 10);
            if (!size || size < 1) size = 10;
            var filtered = filteredRows();
            var totalPages = Math.max(1, Math.ceil(filtered.length / size));
            if (currentPage > totalPages) currentPage = totalPages;
            rows.forEach(function (row) { row.style.display = 'none'; });
            filtered.slice((currentPage - 1) * size, currentPage * size).forEach(function (row) { row.style.display = ''; });
            if (count) count.textContent = filtered.length + ' of ' + rows.length + ' entries';
            if (pager) {
                pager.innerHTML = '';
                for (var i = 1; i <= totalPages; i++) {
                    var li = document.createElement('li');
                    li.className = 'page-item ' + (i === currentPage ? 'active' : '');
                    li.innerHTML = '<button type="button" class="page-link">' + i + '</button>';
                    (function (page) { li.querySelector('button').addEventListener('click', function () { currentPage = page; render(); }); })(i);
                    pager.appendChild(li);
                }
            }
        }
        if (search) search.addEventListener('input', function () { currentPage = 1; render(); });
        if (status) status.addEventListener('change', function () { currentPage = 1; render(); });
        if (pageSize) pageSize.addEventListener('change', function () { currentPage = 1; render(); });
        render();
    }

    function setupGenericPagination() {
        document.querySelectorAll('table[data-ui-paginate="true"]').forEach(function (table) {
            if (table.querySelector('tbody tr[data-ui-search]')) { setupFilterTable(table); return; }
            if (table.dataset.uiReady === '1') return;
            table.dataset.uiReady = '1';
            var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr'));
            if (!rows.length) return;
            var pageSize = parseInt(table.getAttribute('data-ui-page-size') || '10', 10);
            var pager = document.querySelector('[data-ui-pager-for="' + table.id + '"]');
            if (!pager) return;
            var currentPage = 1;
            function render() {
                var totalPages = Math.max(1, Math.ceil(rows.length / pageSize));
                rows.forEach(function (row, idx) { row.style.display = (idx >= (currentPage - 1) * pageSize && idx < currentPage * pageSize) ? '' : 'none'; });
                pager.innerHTML = '';
                for (var i = 1; i <= totalPages; i++) {
                    var li = document.createElement('li');
                    li.className = 'page-item ' + (i === currentPage ? 'active' : '');
                    li.innerHTML = '<button type="button" class="page-link">' + i + '</button>';
                    (function (page) { li.querySelector('button').addEventListener('click', function () { currentPage = page; render(); }); })(i);
                    pager.appendChild(li);
                }
            }
            render();
        });
    }

    function setupActionMenus() {
        function position(details) {
            var summary = details.querySelector('summary');
            var panel = details.querySelector('.ui-action-menu-panel');
            if (!summary || !panel) return;
            if (!details.open) { panel.style.removeProperty('left'); panel.style.removeProperty('right'); panel.style.removeProperty('top'); return; }
            var rect = summary.getBoundingClientRect();
            var width = Math.max(panel.offsetWidth || 150, 150);
            var left = Math.min(Math.max(8, rect.right - width), Math.max(8, window.innerWidth - width - 8));
            panel.style.left = left + 'px';
            panel.style.right = 'auto';
            panel.style.top = Math.min(rect.bottom + 4, Math.max(8, window.innerHeight - (panel.offsetHeight || 120) - 8)) + 'px';
        }
        document.querySelectorAll('details.ui-action-menu').forEach(function (details) {
            details.addEventListener('toggle', function () {
                if (details.open) document.querySelectorAll('details.ui-action-menu[open]').forEach(function (other) { if (other !== details) other.removeAttribute('open'); });
                position(details);
            });
        });
        document.addEventListener('click', function (e) {
            document.querySelectorAll('details.ui-action-menu[open]').forEach(function (details) {
                if (!details.contains(e.target) && !details.querySelector('.ui-action-menu-panel').contains(e.target)) details.removeAttribute('open');
            });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') document.querySelectorAll('details.ui-action-menu[open]').forEach(function (details) { details.removeAttribute('open'); });
        });
        window.addEventListener('resize', function () { document.querySelectorAll('details.ui-action-menu[open]').forEach(position); });
        document.addEventListener('scroll', function () { document.querySelectorAll('details.ui-action-menu[open]').forEach(position); }, true);
    }

    function setupAlertsAsToasts() {
        document.querySelectorAll('.alert.alert-success, .alert.alert-danger').forEach(function (alert) {
            var text = alert.textContent.replace(/×/g, '').trim();
            if (text) toast(text, alert.classList.contains('alert-success') ? 'success' : 'danger');
        });
    }

    ready(function () {
        document.body.classList.add('admin-ui');
        document.querySelectorAll('form').forEach(function (form) {
            form.addEventListener('submit', function () {
                if (form.hasAttribute('data-confirm') && form.dataset.confirmAccepted !== '1') return;
                if (!form.hasAttribute('data-no-spinner')) showLoader();
                form.classList.add('was-validated');
            });
        });
        document.querySelectorAll('[data-copy]').forEach(function (btn) {
            btn.addEventListener('click', function () { copyText(btn.getAttribute('data-copy') || '', btn); });
        });
        setupConfirmModal();
        setupDatePickerBridge();
        setupLicenseCreateMode();
        setupLicenseTable();
        setupGenericPagination();
        setupActionMenus();
        setupAlertsAsToasts();
    });

    window.LicoraUI = { toast: toast, copyText: copyText };
})();
