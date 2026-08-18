(function () {
    'use strict';
    function ready(fn) { if (document.readyState !== 'loading') fn(); else document.addEventListener('DOMContentLoaded', fn); }
    ready(function () {
        var body = document.body;
        var toggle = document.getElementById('licoraSidebarToggle');
        var sidebar = document.getElementById('licoraSidebar');
        var backdrop = document.getElementById('licoraSidebarBackdrop');
        if (!toggle || !sidebar || !backdrop) return;

        function setOpen(open) {
            body.classList.toggle('ui-sidebar-open', !!open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        function setSubmenu(control, open) {
            var targetId = control.getAttribute('aria-controls');
            var submenu = targetId ? document.getElementById(targetId) : null;
            if (!submenu) return;
            control.setAttribute('aria-expanded', open ? 'true' : 'false');
            submenu.hidden = !open;
        }

        sidebar.querySelectorAll('[data-ui-submenu-toggle]').forEach(function (control) {
            setSubmenu(control, control.getAttribute('aria-expanded') === 'true');
            control.addEventListener('click', function () {
                setSubmenu(control, control.getAttribute('aria-expanded') !== 'true');
            });
        });

        toggle.addEventListener('click', function () { setOpen(!body.classList.contains('ui-sidebar-open')); });
        backdrop.addEventListener('click', function () { setOpen(false); });
        document.addEventListener('keydown', function (event) { if (event.key === 'Escape') setOpen(false); });
        sidebar.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () { if (window.innerWidth < 900) setOpen(false); });
        });
        window.addEventListener('resize', function () { if (window.innerWidth >= 900) setOpen(false); });
    });
}());
