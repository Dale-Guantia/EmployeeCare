const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const dom = require('./dom-harness.cjs');
const source = fs.readFileSync(path.join(__dirname, '../../public/js/analytics-charts.js'), 'utf8');

function harness(overrides = {}, available = true) {
    const data = { volume: { canvasId: 'volumeTrendChart', labels: ['2026-11-01', '2026-11-02'], counts: [2, 4] },
        status: { labels: ['Unassigned', 'Awaiting action', 'Completed', 'Reopened'], counts: [1, 4, 0, 1],
            colors: ['#c2c2c2', '#13579b', '#42ffc6', '#5cb0ff'] }, ...overrides };
    const document = dom('<script data-report-charts></script>' + ['volumeTrendChart', 'statusChart'].map(id =>
        '<div data-analytics-chart-panel><canvas id="' + id + '"></canvas><p data-analytics-chart-empty hidden>No tickets.</p></div>').join(''));
    document.querySelector('script').textContent = JSON.stringify(data);
    const charts = {};
    const context = { document };
    if (available) context.Chart = function (canvas, config) { charts[canvas.getAttribute('id')] = config; };
    vm.runInNewContext(source, context);
    return { document, charts, data };
}

test('reports share the trend design while preserving their exact data and saved status colors', () => {
    const h = harness();
    const volume = h.charts.volumeTrendChart;
    assert.equal(volume.type, 'line');
    assert.deepEqual(Array.from(volume.data.labels), h.data.volume.labels);
    assert.deepEqual(Array.from(volume.data.datasets[0].data), h.data.volume.counts);
    const overview = h.charts.statusChart;
    assert.equal(overview.type, 'doughnut');
    assert.equal(overview.options.cutout, '68%');
    assert.deepEqual(Array.from(overview.data.labels), h.data.status.labels);
    assert.deepEqual(Array.from(overview.data.datasets[0].data), h.data.status.counts);
    assert.deepEqual(Array.from(overview.data.datasets[0].backgroundColor), h.data.status.colors);
});

test('report status tooltips retain full names and display exact counts and percentages', () => {
    const name = 'A renamed workflow status with a long display name';
    const overview = harness({ status: { labels: [name, 'Pending'], counts: [125, 375], colors: ['#123456', '#fed971'] } }).charts.statusChart;
    assert.equal(overview.options.plugins.tooltip.callbacks.label({ label: name, parsed: 125 }), name + ': 125 tickets (25%)');
});

test('zero report selections and unavailable chart resources have explicit fallback states', () => {
    const empty = harness({ volume: { canvasId: 'volumeTrendChart', labels: ['2026-11'], counts: [0] },
        status: { labels: ['Completed'], counts: [0], colors: ['#123456'] } });
    assert.equal(Object.keys(empty.charts).length, 0);
    for (const message of empty.document.querySelectorAll('[data-analytics-chart-empty]')) assert.equal(message.hidden, false);
    const unavailable = harness({}, false);
    for (const message of unavailable.document.querySelectorAll('[data-analytics-chart-empty]')) {
        assert.equal(message.hidden, false);
        assert.equal(message.textContent, 'Chart unavailable.');
    }
});

test('report date navigation preserves the global office filters and clears retired local filters', () => {
    const view = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/reports.blade.php'), 'utf8');
    const builder = view.match(/function buildReportsUrl\(params\)\s*\{[\s\S]*?return url\.toString\(\);\s*\}/)[0];
    const context = { document: dom('<select data-report-department value="2"></select><select data-report-division value="9"></select>'),
        window: { location: { href: 'https://fixture.test/reports?chart_department_id=4&reassignment_period=all' } }, URL };
    vm.runInNewContext(builder + '; result = buildReportsUrl({period:"custom", start:"2026-11-01", end:"2026-11-05"});', context);
    const params = new URL(context.result).searchParams;
    assert.equal(params.get('department_id'), '2');
    assert.equal(params.get('division_id'), '9');
    assert.equal(params.get('start'), '2026-11-01');
    assert.equal(params.get('end'), '2026-11-05');
    assert.equal(params.has('chart_department_id'), false);
    assert.equal(params.has('reassignment_period'), false);
});
