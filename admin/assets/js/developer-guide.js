(function () {
    'use strict';

    var tabs = Array.prototype.slice.call(document.querySelectorAll('[data-devguide-tab]'));
    var panels = Array.prototype.slice.call(document.querySelectorAll('[data-devguide-panel]'));

    function activate(key, updateHash) {
        var found = false;
        tabs.forEach(function (tab) {
            var active = tab.getAttribute('data-devguide-tab') === key;
            tab.classList.toggle('active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
            if (active) { found = true; }
        });
        if (!found) { return false; }
        panels.forEach(function (panel) {
            panel.hidden = panel.getAttribute('data-devguide-panel') !== key;
        });
        if (updateHash && window.history && window.history.replaceState) {
            window.history.replaceState(null, '', '#' + key);
        }
        return true;
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            activate(tab.getAttribute('data-devguide-tab') || '', true);
        });
    });

    var hashKey = (window.location.hash || '').replace(/^#/, '');
    if (hashKey) { activate(hashKey, false); }

    function copyText(text, button) {
        var done = function () {
            var original = button.innerHTML;
            button.innerHTML = '<i class="bi bi-check2"></i> Copied';
            window.setTimeout(function () { button.innerHTML = original; }, 1400);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done).catch(function () { fallbackCopy(text, done); });
            return;
        }
        fallbackCopy(text, done);
    }

    function fallbackCopy(text, done) {
        var area = document.createElement('textarea');
        area.value = text;
        area.setAttribute('readonly', 'readonly');
        area.style.position = 'fixed';
        area.style.opacity = '0';
        document.body.appendChild(area);
        area.select();
        try { document.execCommand('copy'); done(); } catch (ignore) {}
        document.body.removeChild(area);
    }

    Array.prototype.slice.call(document.querySelectorAll('[data-devguide-copy-target]')).forEach(function (button) {
        button.addEventListener('click', function () {
            var id = button.getAttribute('data-devguide-copy-target') || '';
            var target = document.getElementById(id);
            if (target) { copyText(target.textContent || '', button); }
        });
    });

    Array.prototype.slice.call(document.querySelectorAll('[data-devguide-copy-text]')).forEach(function (button) {
        button.addEventListener('click', function () {
            copyText(button.getAttribute('data-devguide-copy-text') || '', button);
        });
    });
}());
