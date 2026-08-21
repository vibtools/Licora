(function (root, factory) {
    var api = factory();
    if (typeof module === 'object' && module.exports) module.exports = api;
    if (root) root.LicoraDashboard = api;
})(typeof window !== 'undefined' ? window : globalThis, function () {
    'use strict';

    var DEFAULT_POLL_MS = 30000;

    function asInt(value) {
        var parsed = parseInt(value, 10);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function text(value, fallback) {
        if (value === null || value === undefined || value === '') return fallback || '—';
        return String(value);
    }

    function formatTimestamp(value) {
        if (!value) return '—';
        var date = new Date(value);
        if (Number.isNaN(date.getTime())) return '—';
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }

    function formatActivityTime(value) {
        if (!value) return '—';
        var date = new Date(value.replace(' ', 'T'));
        if (Number.isNaN(date.getTime())) return text(value, '—');
        return date.toLocaleString([], { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    }

    function indexSeries(series) {
        var indexed = Object.create(null);
        (Array.isArray(series) ? series : []).forEach(function (point) {
            if (!point || !point.date) return;
            indexed[String(point.date)] = asInt(point.count);
        });
        return indexed;
    }

    function alignSeries(left, right) {
        var labels = Object.create(null);
        (Array.isArray(left) ? left : []).concat(Array.isArray(right) ? right : []).forEach(function (point) {
            if (point && point.date) labels[String(point.date)] = true;
        });
        var ordered = Object.keys(labels).sort();
        var leftIndex = indexSeries(left);
        var rightIndex = indexSeries(right);
        return {
            labels: ordered,
            left: ordered.map(function (label) { return leftIndex[label] || 0; }),
            right: ordered.map(function (label) { return rightIndex[label] || 0; })
        };
    }

    function combineRecentActivity(recent) {
        var result = [];
        var v1 = recent && Array.isArray(recent.v1_tracked) ? recent.v1_tracked : [];
        var v2 = recent && Array.isArray(recent.v2_tracked) ? recent.v2_tracked : [];
        v1.forEach(function (call) {
            var code = asInt(call && call.response_code);
            result.push({
                source: 'API v1',
                action: text(call && call.endpoint, 'verify'),
                context: call && call.license_key ? String(call.license_key).slice(0, 12) + '…' : 'No license',
                result: String(code),
                tone: code === 200 ? 'success' : (code >= 400 && code < 500 ? 'warning' : 'danger'),
                created_at: text(call && call.created_at, '')
            });
        });
        v2.forEach(function (event) {
            var parts = [];
            if (event && event.app_id) parts.push(String(event.app_id));
            if (event && event.license_id !== null && event.license_id !== undefined) parts.push('License #' + asInt(event.license_id));
            result.push({
                source: 'API v2',
                action: text(event && event.event_type, 'audit_event'),
                context: parts.length ? parts.join(' · ') : 'Audit event',
                result: 'Recorded',
                tone: 'primary',
                created_at: text(event && event.created_at, '')
            });
        });
        result.sort(function (a, b) {
            var at = Date.parse(String(a.created_at).replace(' ', 'T')) || 0;
            var bt = Date.parse(String(b.created_at).replace(' ', 'T')) || 0;
            return bt - at;
        });
        return result.slice(0, 12);
    }

    function validatePayload(payload) {
        if (!payload || payload.success !== true || !payload.data || typeof payload.data !== 'object') {
            throw new Error('Dashboard response contract is invalid.');
        }
        ['licenses', 'devices', 'api_activity', 'recent_activity', 'expiration', 'health'].forEach(function (key) {
            if (!payload.data[key] || typeof payload.data[key] !== 'object') {
                throw new Error('Dashboard response is missing ' + key + '.');
            }
        });
        return payload;
    }

    function createController(options) {
        options = options || {};
        var request = options.request;
        var view = options.view || {};
        var pollMs = asInt(options.pollMs) || DEFAULT_POLL_MS;
        var setTimer = options.setTimer || function (fn, ms) { return setInterval(fn, ms); };
        var clearTimer = options.clearTimer || function (id) { clearInterval(id); };
        var inFlight = false;
        var timer = null;
        var stopped = false;
        var lastSuccessAt = null;

        function notify(name) {
            if (typeof view[name] === 'function') {
                var args = Array.prototype.slice.call(arguments, 1);
                view[name].apply(view, args);
            }
        }

        function refresh(reason) {
            if (stopped || inFlight) return Promise.resolve({ skipped: true });
            if (typeof request !== 'function') return Promise.reject(new Error('Dashboard request transport is unavailable.'));
            inFlight = true;
            notify('setLoading', true, reason || 'poll');
            return Promise.resolve().then(function () { return request(); }).then(function (response) {
                if (response && response.status === 401) {
                    stopped = true;
                    if (timer !== null) { clearTimer(timer); timer = null; }
                    notify('showAuthRequired');
                    return { authRequired: true };
                }
                if (!response || response.ok !== true) {
                    var error = new Error('Dashboard refresh failed.');
                    error.status = response ? response.status : 0;
                    throw error;
                }
                return response.json();
            }).then(function (payload) {
                if (payload && payload.authRequired) return payload;
                validatePayload(payload);
                var completedAt = payload.generated_at || new Date().toISOString();
                notify('render', payload);
                lastSuccessAt = completedAt;
                notify('showFresh', lastSuccessAt);
                return payload;
            }).catch(function (error) {
                notify('showStale', lastSuccessAt, error);
                return { error: error };
            }).finally(function () {
                inFlight = false;
                notify('setLoading', false, reason || 'poll');
            });
        }

        function start() {
            if (stopped || timer !== null) return;
            timer = setTimer(function () { refresh('poll'); }, pollMs);
        }

        function stop() {
            stopped = true;
            if (timer !== null) { clearTimer(timer); timer = null; }
        }

        function seed(payload) {
            validatePayload(payload);
            lastSuccessAt = payload.generated_at || null;
            notify('render', payload);
            notify('showFresh', lastSuccessAt);
        }

        return {
            refresh: refresh,
            start: start,
            stop: stop,
            seed: seed,
            isInFlight: function () { return inFlight; },
            isStopped: function () { return stopped; },
            getLastSuccessAt: function () { return lastSuccessAt; }
        };
    }

    function createElement(documentRef, tag, className, value) {
        var el = documentRef.createElement(tag);
        if (className) el.className = className;
        if (value !== undefined) el.textContent = String(value);
        return el;
    }

    function createDomView(documentRef, chartFactory) {
        var apiChart = null;
        var expirationChart = null;
        var refreshButton = documentRef.querySelector('[data-dashboard-refresh]');
        var refreshText = documentRef.querySelector('[data-dashboard-refresh-text]');
        var updatedAt = documentRef.querySelector('[data-dashboard-updated-at]');
        var state = documentRef.querySelector('[data-dashboard-state]');
        var stateText = documentRef.querySelector('[data-dashboard-state-text]');
        var signin = documentRef.querySelector('[data-dashboard-signin]');
        var authLocked = false;

        function cssVar(name, fallback) {
            if (!documentRef.defaultView || !documentRef.defaultView.getComputedStyle) return fallback;
            var value = documentRef.defaultView.getComputedStyle(documentRef.documentElement).getPropertyValue(name).trim();
            return value || fallback;
        }

        function setState(message, tone, showSignin) {
            if (!state || !stateText) return;
            state.hidden = !message;
            state.classList.remove('is-warning', 'is-danger');
            if (tone) state.classList.add('is-' + tone);
            stateText.textContent = message || '';
            if (signin) signin.hidden = !showSignin;
        }

        function setText(selector, value) {
            var node = documentRef.querySelector(selector);
            if (node) node.textContent = String(value);
        }

        function updateHealth(health) {
            var v2Ready = !!(health.api_v2 && health.api_v2.schema_ready && health.api_v2.key_pair_ready);
            var items = {
                database: { value: text(health.database && health.database.label), tone: health.database && health.database.ok ? 'ok' : 'danger' },
                api_v2: { value: v2Ready ? 'Ready' : 'Needs setup', tone: v2Ready ? 'ok' : 'warning' },
                cron_scripts: { value: health.cron_scripts && health.cron_scripts.available ? 'Available' : 'Missing', tone: health.cron_scripts && health.cron_scripts.available ? 'ok' : 'danger' },
                php: { value: text(health.php && health.php.version), tone: health.php && health.php.ok ? 'ok' : 'danger' },
                environment: { value: text(health.environment && health.environment.value), tone: 'neutral' }
            };
            Object.keys(items).forEach(function (key) {
                var item = documentRef.querySelector('[data-dashboard-health="' + key + '"]');
                var value = documentRef.querySelector('[data-dashboard-health-value="' + key + '"]');
                if (value) value.textContent = key === 'environment' ? items[key].value.charAt(0).toUpperCase() + items[key].value.slice(1) : items[key].value;
                if (item) {
                    item.classList.remove('is-ok', 'is-warning', 'is-danger', 'is-neutral');
                    item.classList.add('is-' + items[key].tone);
                }
            });
        }

        function updateCharts(data) {
            if (typeof chartFactory !== 'function') return;
            var api = alignSeries(data.api_activity.v1_tracked.last_14_days, data.api_activity.v2_tracked.last_14_days);
            var expiration = alignSeries(data.expiration.expired_last_30_days, data.expiration.expiring_next_30_days);
            var primary = cssVar('--licora-primary', '#2563eb');
            var secondary = cssVar('--licora-secondary', '#7c3aed');
            var danger = cssVar('--status-danger', '#c9363e');
            var warning = cssVar('--status-warning', '#b76d00');
            var muted = cssVar('--text-muted', '#7b8798');
            var border = cssVar('--border-inner', '#e7ebf1');
            var chartOptions = {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, color: muted } } },
                scales: { x: { grid: { display: false }, ticks: { color: muted, maxTicksLimit: 7 } }, y: { beginAtZero: true, grid: { color: border }, ticks: { color: muted, precision: 0 } } }
            };
            if (!apiChart) {
                var apiCanvas = documentRef.getElementById('dailyApiChart');
                if (apiCanvas) apiChart = chartFactory(apiCanvas, { type: 'line', data: { labels: api.labels, datasets: [
                    { label: 'API v1 Verify', data: api.left, borderColor: primary, backgroundColor: primary, tension: .25, pointRadius: 2 },
                    { label: 'API v2 Audit Events', data: api.right, borderColor: secondary, backgroundColor: secondary, tension: .25, pointRadius: 2 }
                ] }, options: chartOptions });
            } else {
                apiChart.data.labels = api.labels;
                apiChart.data.datasets[0].data = api.left;
                apiChart.data.datasets[1].data = api.right;
                apiChart.update('none');
            }
            if (!expirationChart) {
                var expirationCanvas = documentRef.getElementById('expiredTrendChart');
                if (expirationCanvas) expirationChart = chartFactory(expirationCanvas, { type: 'bar', data: { labels: expiration.labels, datasets: [
                    { label: 'Expired — Last 30 Days', data: expiration.left, backgroundColor: danger },
                    { label: 'Expiring — Next 30 Days', data: expiration.right, backgroundColor: warning }
                ] }, options: chartOptions });
            } else {
                expirationChart.data.labels = expiration.labels;
                expirationChart.data.datasets[0].data = expiration.left;
                expirationChart.data.datasets[1].data = expiration.right;
                expirationChart.update('none');
            }
        }

        function updateRecentActivity(recent) {
            var tbody = documentRef.querySelector('[data-dashboard-recent-activity]');
            if (!tbody) return;
            while (tbody.firstChild) tbody.removeChild(tbody.firstChild);
            var rows = combineRecentActivity(recent);
            if (!rows.length) {
                var emptyRow = createElement(documentRef, 'tr');
                var emptyCell = createElement(documentRef, 'td');
                emptyCell.colSpan = 5;
                emptyCell.appendChild(createElement(documentRef, 'div', 'dashboard-empty', 'No tracked activity yet.'));
                emptyRow.appendChild(emptyCell);
                tbody.appendChild(emptyRow);
                return;
            }
            rows.forEach(function (item) {
                var tr = createElement(documentRef, 'tr');
                tr.appendChild(createElement(documentRef, 'td', '', formatActivityTime(item.created_at)));
                var sourceCell = createElement(documentRef, 'td');
                sourceCell.appendChild(createElement(documentRef, 'span', 'dashboard-source-badge', item.source));
                tr.appendChild(sourceCell);
                var actionCell = createElement(documentRef, 'td');
                actionCell.appendChild(createElement(documentRef, 'code', '', item.action));
                tr.appendChild(actionCell);
                tr.appendChild(createElement(documentRef, 'td', '', item.context));
                var resultCell = createElement(documentRef, 'td');
                resultCell.appendChild(createElement(documentRef, 'span', 'badge bg-' + item.tone, item.result));
                tr.appendChild(resultCell);
                tbody.appendChild(tr);
            });
        }

        function updateTopLicenses(apiActivity) {
            var tbody = documentRef.querySelector('[data-dashboard-top-licenses]');
            if (!tbody) return;
            while (tbody.firstChild) tbody.removeChild(tbody.firstChild);
            var rows = apiActivity && apiActivity.v1_tracked && Array.isArray(apiActivity.v1_tracked.top_licenses) ? apiActivity.v1_tracked.top_licenses : [];
            if (!rows.length) {
                var emptyRow = createElement(documentRef, 'tr');
                var emptyCell = createElement(documentRef, 'td');
                emptyCell.colSpan = 2;
                emptyCell.appendChild(createElement(documentRef, 'div', 'dashboard-empty', 'No tracked API v1 license activity yet.'));
                emptyRow.appendChild(emptyCell);
                tbody.appendChild(emptyRow);
                return;
            }
            rows.forEach(function (license) {
                var tr = createElement(documentRef, 'tr');
                var licenseCell = createElement(documentRef, 'td');
                licenseCell.appendChild(createElement(documentRef, 'code', '', license && license.license_key ? String(license.license_key).slice(0, 18) : 'Unknown'));
                tr.appendChild(licenseCell);
                var countCell = createElement(documentRef, 'td', 'text-end');
                countCell.appendChild(createElement(documentRef, 'span', 'badge bg-primary', asInt(license && license.count)));
                tr.appendChild(countCell);
                tbody.appendChild(tr);
            });
        }

        return {
            render: function (payload) {
                var data = payload.data;
                setText('[data-dashboard-kpi="total_licenses"]', asInt(data.licenses.total));
                setText('[data-dashboard-kpi="active_licenses"]', asInt(data.licenses.active));
                setText('[data-dashboard-kpi="recent_devices"]', asInt(data.devices.recently_seen));
                setText('[data-dashboard-kpi="expiring_soon"]', asInt(data.licenses.expiring_soon));
                setText('[data-dashboard-kpi-meta="expired_licenses"]', asInt(data.licenses.expired));
                setText('[data-dashboard-kpi-meta="suspended_licenses"]', asInt(data.licenses.suspended));
                setText('[data-dashboard-kpi-meta="active_devices"]', asInt(data.devices.active_flagged));
                setText('[data-dashboard-kpi-meta="total_devices"]', asInt(data.devices.total_records));
                updateHealth(data.health);
                updateCharts(data);
                updateRecentActivity(data.recent_activity);
                updateTopLicenses(data.api_activity);
            },
            setLoading: function (loading) {
                if (refreshButton) {
                    refreshButton.disabled = authLocked || !!loading;
                    refreshButton.classList.toggle('is-loading', !!loading);
                }
                if (loading && refreshText) refreshText.textContent = 'Refreshing…';
            },
            showFresh: function (timestamp) {
                authLocked = false;
                if (updatedAt) updatedAt.textContent = formatTimestamp(timestamp);
                setState('', '', false);
                if (refreshButton) refreshButton.disabled = false;
                if (refreshText) refreshText.textContent = 'Refresh';
            },
            showStale: function (lastSuccessAt) {
                var suffix = lastSuccessAt ? ' Last successful update: ' + formatTimestamp(lastSuccessAt) + '.' : '';
                setState('Data may be stale.' + suffix, 'warning', false);
                if (refreshText) refreshText.textContent = 'Retry';
            },
            showAuthRequired: function () {
                authLocked = true;
                setState('Session expired. Sign in again to continue dashboard refresh.', 'danger', true);
                if (refreshText) refreshText.textContent = 'Refresh paused';
                if (refreshButton) refreshButton.disabled = true;
            }
        };
    }

    function initBrowser(documentRef, windowRef) {
        var root = documentRef.getElementById('licora-dashboard');
        if (!root) return null;
        var endpoint = root.getAttribute('data-dashboard-endpoint') || 'ajax/dashboard-data.php';
        var pollMs = asInt(root.getAttribute('data-dashboard-poll-ms')) || DEFAULT_POLL_MS;
        var chartFactory = windowRef.Chart ? function (canvas, config) { return new windowRef.Chart(canvas, config); } : null;
        var view = createDomView(documentRef, chartFactory);
        var controller = createController({
            pollMs: pollMs,
            view: view,
            request: function () {
                return windowRef.fetch(endpoint, {
                    method: 'GET',
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: { 'Accept': 'application/json' }
                });
            }
        });
        var initialNode = documentRef.getElementById('dashboard-initial-data');
        if (initialNode) {
            try { controller.seed(JSON.parse(initialNode.textContent || '{}')); }
            catch (error) { view.showStale(null, error); }
        }
        var refresh = documentRef.querySelector('[data-dashboard-refresh]');
        if (refresh) refresh.addEventListener('click', function () { controller.refresh('manual'); });
        controller.start();
        return controller;
    }

    if (typeof document !== 'undefined' && typeof window !== 'undefined') {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () { initBrowser(document, window); }, { once: true });
        } else {
            initBrowser(document, window);
        }
    }

    return {
        DEFAULT_POLL_MS: DEFAULT_POLL_MS,
        alignSeries: alignSeries,
        combineRecentActivity: combineRecentActivity,
        validatePayload: validatePayload,
        createController: createController,
        createDomView: createDomView,
        initBrowser: initBrowser,
        formatTimestamp: formatTimestamp
    };
});
