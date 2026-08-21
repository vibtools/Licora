'use strict';


function fakeClassList() {
    const values = new Set();
    return {
        add: (...names) => names.forEach(name => values.add(name)),
        remove: (...names) => names.forEach(name => values.delete(name)),
        toggle: (name, force) => {
            if (force === true) { values.add(name); return true; }
            if (force === false) { values.delete(name); return false; }
            if (values.has(name)) { values.delete(name); return false; }
            values.add(name); return true;
        },
        contains: name => values.has(name)
    };
}

function fakeNode() {
    return {
        disabled: false,
        hidden: false,
        textContent: '',
        classList: fakeClassList()
    };
}

function createMinimalDocument() {
    const nodes = {
        '[data-dashboard-refresh]': fakeNode(),
        '[data-dashboard-refresh-text]': fakeNode(),
        '[data-dashboard-updated-at]': fakeNode(),
        '[data-dashboard-state]': fakeNode(),
        '[data-dashboard-state-text]': fakeNode(),
        '[data-dashboard-signin]': fakeNode()
    };
    return {
        nodes,
        querySelector: selector => nodes[selector] || null,
        defaultView: { getComputedStyle: () => ({ getPropertyValue: () => '' }) },
        documentElement: {}
    };
}

const assert = require('assert');
const dashboard = require('../admin/assets/js/dashboard.js');

function payload(generatedAt) {
    return {
        success: true,
        generated_at: generatedAt || '2026-08-20T22:00:00+00:00',
        data: {
            licenses: { total: 10, active: 7, expired: 2, suspended: 1, expiring_soon: 3 },
            devices: { recently_seen: 4, active_flagged: 5, total_records: 6 },
            api_keys: { total: 2, active: 1 },
            api_activity: {
                v1_tracked: {
                    last_14_days: [{ date: '2026-08-19', count: 2 }, { date: '2026-08-20', count: 3 }],
                    top_licenses: [{ license_key: 'AAAA1111-BBBB2222', count: 5 }]
                },
                v2_tracked: { last_14_days: [{ date: '2026-08-20', count: 4 }] }
            },
            recent_activity: {
                v1_tracked: [{ endpoint: 'verify', license_key: 'AAAA1111-BBBB2222', response_code: 200, created_at: '2026-08-20 22:00:00' }],
                v2_tracked: [{ event_type: 'activation_success', app_id: 'desktop', license_id: 1, created_at: '2026-08-20 22:01:00' }]
            },
            expiration: {
                expired_last_30_days: [{ date: '2026-08-18', count: 1 }],
                expiring_next_30_days: [{ date: '2026-08-25', count: 2 }]
            },
            health: {
                database: { ok: true, label: 'Connected' },
                php: { ok: true, version: '8.4.24' },
                environment: { value: 'production' },
                cron_scripts: { available: true },
                api_v2: { schema_ready: true, key_pair_ready: true }
            }
        }
    };
}

function response(status, body) {
    return {
        status,
        ok: status >= 200 && status < 300,
        json: async () => body
    };
}

async function run() {
    const aligned = dashboard.alignSeries(
        [{ date: '2026-08-19', count: 2 }, { date: '2026-08-20', count: 3 }],
        [{ date: '2026-08-20', count: 4 }, { date: '2026-08-21', count: 5 }]
    );
    assert.deepStrictEqual(aligned.labels, ['2026-08-19', '2026-08-20', '2026-08-21']);
    assert.deepStrictEqual(aligned.left, [2, 3, 0]);
    assert.deepStrictEqual(aligned.right, [0, 4, 5]);

    const activity = dashboard.combineRecentActivity(payload().data.recent_activity);
    assert.strictEqual(activity.length, 2);
    assert.strictEqual(activity[0].source, 'API v2');
    assert.strictEqual(activity[1].source, 'API v1');
    assert.strictEqual(activity[1].result, '200');

    assert.doesNotThrow(() => dashboard.validatePayload(payload()));
    assert.throws(() => dashboard.validatePayload({ success: true, data: {} }), /missing licenses/);

    const events = [];
    let timerCallback = null;
    let timerMs = null;
    let clearedTimer = null;
    const view = {
        render: value => events.push(['render', value.generated_at]),
        showFresh: value => events.push(['fresh', value]),
        showStale: value => events.push(['stale', value]),
        showAuthRequired: () => events.push(['auth']),
        setLoading: (value, reason) => events.push(['loading', value, reason])
    };
    const controller = dashboard.createController({
        view,
        pollMs: 30000,
        request: async () => response(200, payload('2026-08-20T22:02:00+00:00')),
        setTimer: (callback, ms) => { timerCallback = callback; timerMs = ms; return 77; },
        clearTimer: id => { clearedTimer = id; }
    });
    controller.seed(payload());
    assert.strictEqual(controller.getLastSuccessAt(), '2026-08-20T22:00:00+00:00');
    controller.start();
    assert.strictEqual(timerMs, 30000, 'polling keeps the approved 30-second cadence');
    assert.strictEqual(typeof timerCallback, 'function', 'poll callback is registered');
    await controller.refresh('manual');
    assert.strictEqual(controller.getLastSuccessAt(), '2026-08-20T22:02:00+00:00');
    assert(events.some(event => event[0] === 'loading' && event[1] === true && event[2] === 'manual'));
    assert(events.some(event => event[0] === 'loading' && event[1] === false && event[2] === 'manual'));

    let resolvePending;
    let requestCount = 0;
    const overlapController = dashboard.createController({
        view: {},
        request: () => {
            requestCount += 1;
            return new Promise(resolve => { resolvePending = resolve; });
        }
    });
    const first = overlapController.refresh('poll');
    const second = await overlapController.refresh('poll');
    assert.deepStrictEqual(second, { skipped: true });
    assert.strictEqual(requestCount, 1, 'overlapping refresh does not start a second request');
    resolvePending(response(200, payload()));
    await first;

    let staleCount = 0;
    let renderCount = 0;
    const staleController = dashboard.createController({
        view: { render: () => { renderCount += 1; }, showFresh: () => {}, showStale: () => { staleCount += 1; }, setLoading: () => {} },
        request: async () => response(500, { success: false, code: 'DASHBOARD_DATA_ERROR' })
    });
    staleController.seed(payload());
    await staleController.refresh('manual');
    assert.strictEqual(staleCount, 1, 'failed refresh enters stale state');
    assert.strictEqual(renderCount, 1, 'failed refresh preserves the last successful rendered snapshot');

    let authShown = 0;
    let authTimerCleared = null;
    const authController = dashboard.createController({
        view: { showAuthRequired: () => { authShown += 1; }, setLoading: () => {} },
        request: async () => response(401, { success: false, code: 'AUTH_REQUIRED' }),
        setTimer: () => 91,
        clearTimer: id => { authTimerCleared = id; }
    });
    authController.start();
    const authResult = await authController.refresh('poll');
    assert.strictEqual(authResult.authRequired, true);
    assert.strictEqual(authShown, 1, '401 surfaces the auth-required state');
    assert.strictEqual(authController.isStopped(), true, '401 stops future automatic polling');
    assert.strictEqual(authTimerCleared, 91, '401 clears the active polling timer');

    let syncThrowStale = 0;
    const syncThrowController = dashboard.createController({
        view: { showStale: () => { syncThrowStale += 1; }, setLoading: () => {} },
        request: () => { throw new Error('transport unavailable'); }
    });
    const syncThrowResult = await syncThrowController.refresh('manual');
    assert(syncThrowResult.error instanceof Error, 'synchronous transport errors are converted into the normal stale result');
    assert.strictEqual(syncThrowStale, 1, 'synchronous transport errors enter stale state');
    assert.strictEqual(syncThrowController.isInFlight(), false, 'synchronous transport errors always release the request lock');

    let renderFailureStale = 0;
    const renderFailureController = dashboard.createController({
        view: {
            render: value => {
                if (value.generated_at === '2026-08-20T22:03:00+00:00') throw new Error('render failed');
            },
            showFresh: () => {},
            showStale: () => { renderFailureStale += 1; },
            setLoading: () => {}
        },
        request: async () => response(200, payload('2026-08-20T22:03:00+00:00'))
    });
    renderFailureController.seed(payload('2026-08-20T22:00:00+00:00'));
    await renderFailureController.refresh('poll');
    assert.strictEqual(renderFailureStale, 1, 'render failures enter stale state');
    assert.strictEqual(
        renderFailureController.getLastSuccessAt(),
        '2026-08-20T22:00:00+00:00',
        'last-success time advances only after a complete successful render'
    );

    const fakeDocument = createMinimalDocument();
    const domView = dashboard.createDomView(fakeDocument, null);
    domView.showStale('2026-08-20T22:00:00+00:00');
    domView.setLoading(false);
    assert.strictEqual(
        fakeDocument.nodes['[data-dashboard-refresh-text]'].textContent,
        'Retry',
        'stale state keeps the Retry label after loading completes'
    );
    domView.showAuthRequired();
    domView.setLoading(false);
    assert.strictEqual(
        fakeDocument.nodes['[data-dashboard-refresh-text]'].textContent,
        'Refresh paused',
        'auth-required state keeps the paused label after loading completes'
    );
    assert.strictEqual(
        fakeDocument.nodes['[data-dashboard-refresh]'].disabled,
        true,
        'auth-required state keeps manual refresh disabled after loading completes'
    );

    controller.stop();
    assert.strictEqual(clearedTimer, 77, 'controller stop clears its timer');

    console.log('Dashboard browser/runtime checks passed.');
}

run().catch(error => {
    console.error(error && error.stack ? error.stack : error);
    process.exit(1);
});
