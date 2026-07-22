(function () {
    'use strict';

    document.documentElement.classList.toggle('ui-dark', localStorage.getItem('license-ui-theme') === 'dark');

    function ready(fn) {
        if (document.readyState !== 'loading') fn();
        else document.addEventListener('DOMContentLoaded', fn);
    }

    ready(function () {
        document.body.classList.add('admin-ui');
        setupNavbarFallback();

        var toggle = document.getElementById('uiThemeToggle');
        if (toggle) {
            var icon = toggle.querySelector('i');
            var sync = function () {
                var dark = document.documentElement.classList.contains('ui-dark');
                if (icon) icon.className = dark ? 'bi bi-sun' : 'bi bi-moon-stars';
                toggle.setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode');
            };
            sync();
            toggle.addEventListener('click', function () {
                document.documentElement.classList.toggle('ui-dark');
                localStorage.setItem('license-ui-theme', document.documentElement.classList.contains('ui-dark') ? 'dark' : 'light');
                sync();
            });
        }

        document.querySelectorAll('form').forEach(function (form) {
            form.addEventListener('submit', function () {
                if (!form.hasAttribute('data-no-spinner')) showLoader();
                form.classList.add('was-validated');
            });
        });

        document.querySelectorAll('[data-copy]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var text = btn.getAttribute('data-copy') || '';
                copyText(text, btn);
            });
        });

        setupConfirmModal();
        setupLicenseTable();
        setupDatePickerBridge();
        setupAlertsAsToasts();
    });


    function setupNavbarFallback() {
        var toggler = document.querySelector('.admin-nav-toggler[data-bs-target="#navbarNav"]');
        var menu = document.getElementById('navbarNav');
        if (!toggler || !menu) return;

        // Safety net for Bootstrap collapse when utility CSS conflicts with `.collapse`.
        toggler.addEventListener('click', function () {
            setTimeout(function () {
                if (window.bootstrap && bootstrap.Collapse) return;
                var isOpen = menu.classList.toggle('show');
                toggler.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            }, 0);
        });

        menu.querySelectorAll('a.nav-link, .dropdown-item').forEach(function (link) {
            link.addEventListener('click', function () {
                if (window.innerWidth < 992 && menu.classList.contains('show') && window.bootstrap && bootstrap.Collapse) {
                    bootstrap.Collapse.getOrCreateInstance(menu, { toggle: false }).hide();
                }
            });
        });
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

    function copyText(text, btn) {
        if (!text) return;
        navigator.clipboard.writeText(text).then(function () {
            var old = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-check2"></i>';
            btn.classList.add('btn-success');
            setTimeout(function () { btn.innerHTML = old; btn.classList.remove('btn-success'); }, 1200);
            toast('Copied to clipboard', 'success');
        }).catch(function () {
            toast('Copy failed', 'danger');
        });
    }

    function setupAlertsAsToasts() {
        document.querySelectorAll('.alert.alert-success, .alert.alert-danger').forEach(function (alert) {
            var text = alert.textContent.trim();
            if (text) toast(text, alert.classList.contains('alert-success') ? 'success' : 'danger');
        });
    }

    function toast(message, type) {
        var wrap = document.getElementById('uiToastContainer');
        if (!wrap) {
            wrap = document.createElement('div');
            wrap.id = 'uiToastContainer';
            wrap.className = 'toast-container position-fixed top-0 end-0 p-3';
            document.body.appendChild(wrap);
        }
        var el = document.createElement('div');
        el.className = 'toast align-items-center text-white bg-' + (type || 'primary') + ' border-0';
        el.setAttribute('role', 'alert');
        el.innerHTML = '<div class="d-flex"><div class="toast-body"></div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
        el.querySelector('.toast-body').textContent = message;
        wrap.appendChild(el);
        if (window.bootstrap && bootstrap.Toast) new bootstrap.Toast(el, { delay: 3500 }).show();
    }

    function setupConfirmModal() {
        var modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.id = 'uiConfirmModal';
        modal.tabIndex = -1;
        modal.innerHTML = '<div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"><i class="bi bi-exclamation-triangle text-warning"></i> Confirm action</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><p id="uiConfirmText" class="mb-0">Are you sure?</p></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><a href="#" id="uiConfirmProceed" class="btn btn-danger">Confirm</a></div></div></div>';
        document.body.appendChild(modal);
        var instance = window.bootstrap && bootstrap.Modal ? new bootstrap.Modal(modal) : null;
        document.querySelectorAll('a[data-confirm]').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                document.getElementById('uiConfirmText').textContent = link.getAttribute('data-confirm') || 'Are you sure?';
                document.getElementById('uiConfirmProceed').setAttribute('href', link.getAttribute('href'));
                if (instance) instance.show(); else if (confirm(document.getElementById('uiConfirmText').textContent)) location.href = link.href;
            });
        });
    }

    function setupDatePickerBridge() {
        var picker = document.getElementById('license-valid-until');
        var hoursInput = document.querySelector('input[name="hours"]');
        if (!picker || !hoursInput) return;
        picker.addEventListener('change', function () {
            if (!picker.value) return;
            var target = new Date(picker.value + 'T23:59:59');
            var diff = Math.ceil((target.getTime() - Date.now()) / 3600000);
            if (diff > 0) hoursInput.value = diff;
        });
        document.querySelectorAll('[data-hours-preset]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                hoursInput.value = btn.getAttribute('data-hours-preset');
            });
        });
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
        var currentPage = 1;

        function matches(row) {
            var status = statusFilter ? statusFilter.value : '';
            var date = dateFilter ? dateFilter.value : '';
            if (status && row.getAttribute('data-status') !== status) return false;
            if (date && row.getAttribute('data-created') !== date) return false;
            return true;
        }

        function render() {
            var pageSize = pageSizeSelect ? parseInt(pageSizeSelect.value, 10) : 10;
            var filtered = rows.filter(matches);
            var totalPages = Math.max(1, Math.ceil(filtered.length / pageSize));
            if (currentPage > totalPages) currentPage = totalPages;
            rows.forEach(function (row) { row.style.display = 'none'; });
            filtered.slice((currentPage - 1) * pageSize, currentPage * pageSize).forEach(function (row) { row.style.display = ''; });
            if (empty) empty.style.display = filtered.length ? 'none' : 'block';
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

        function updateBulk() {
            var checked = table.querySelectorAll('tbody input[type="checkbox"]:checked').length;
            if (bulkBar) bulkBar.classList.toggle('is-visible', checked > 0);
            if (selectedCount) selectedCount.textContent = checked;
        }

        [statusFilter, dateFilter, pageSizeSelect].forEach(function (el) {
            if (el) el.addEventListener('change', function () { currentPage = 1; render(); });
        });
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
})();

(function () {
    'use strict';
    function ready(fn){ if(document.readyState !== 'loading') fn(); else document.addEventListener('DOMContentLoaded', fn); }
    ready(function(){
        document.querySelectorAll('[data-autofill-target]').forEach(function(btn){
            btn.addEventListener('click', function(){
                var target = document.getElementById(btn.getAttribute('data-autofill-target'));
                if(target){ target.value = btn.getAttribute('data-autofill-value') || ''; target.dispatchEvent(new Event('change')); }
            });
        });
        setupGenericPagination();
    });
    function setupGenericPagination(){
        document.querySelectorAll('table[data-ui-paginate="true"]').forEach(function(table){
            if(table.dataset.uiReady === '1') return;
            table.dataset.uiReady = '1';
            var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr'));
            if(rows.length <= 1 && rows[0] && rows[0].querySelector('.empty-state')) return;
            var pageSize = parseInt(table.getAttribute('data-ui-page-size') || '10', 10);
            if(!pageSize || pageSize < 1) pageSize = 10;
            var pager = document.querySelector('[data-ui-pager-for="' + table.id + '"]');
            if(!pager){
                var nav = document.createElement('nav');
                nav.className = 'mt-3';
                nav.setAttribute('aria-label', 'Table pagination');
                pager = document.createElement('ul');
                pager.className = 'pagination justify-content-end mb-0';
                pager.setAttribute('data-ui-pager-for', table.id || '');
                nav.appendChild(pager);
                table.closest('.table-responsive').after(nav);
            }
            var currentPage = 1;
            function render(){
                var totalPages = Math.max(1, Math.ceil(rows.length / pageSize));
                if(currentPage > totalPages) currentPage = totalPages;
                rows.forEach(function(row, idx){ row.style.display = (idx >= (currentPage-1)*pageSize && idx < currentPage*pageSize) ? '' : 'none'; });
                pager.innerHTML = '';
                var prev = document.createElement('li');
                prev.className = 'page-item ' + (currentPage === 1 ? 'disabled' : '');
                prev.innerHTML = '<button type="button" class="page-link">Previous</button>';
                prev.querySelector('button').addEventListener('click', function(){ if(currentPage > 1){ currentPage--; render(); }});
                pager.appendChild(prev);
                for(var i=1;i<=totalPages;i++){
                    var li = document.createElement('li');
                    li.className = 'page-item ' + (i === currentPage ? 'active' : '');
                    li.innerHTML = '<button type="button" class="page-link">' + i + '</button>';
                    (function(page){ li.querySelector('button').addEventListener('click', function(){ currentPage = page; render(); }); })(i);
                    pager.appendChild(li);
                }
                var next = document.createElement('li');
                next.className = 'page-item ' + (currentPage === totalPages ? 'disabled' : '');
                next.innerHTML = '<button type="button" class="page-link">Next</button>';
                next.querySelector('button').addEventListener('click', function(){ if(currentPage < totalPages){ currentPage++; render(); }});
                pager.appendChild(next);
            }
            render();
        });
    }
})();
