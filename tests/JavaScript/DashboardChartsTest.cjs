const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const dom = require('./dom-harness.cjs');
const source = fs.readFileSync(path.join(__dirname, '../../public/js/analytics-charts.js'), 'utf8');

function harness(overrides = {}, available = true) {
    const data = { status: { labels: ['Renamed resolved', 'Pending', 'Unassigned'], counts: [2, 4, 0],
        colors: ['#123456', '#fed971', '#c2c2c2'] },
        volume: { labels: ['2026-11-01', '2026-11-02'], counts: [2, 4] }, ...overrides };
    const document = dom('<script data-dashboard-charts></script>' +
        ['statusChart', 'dashVolumeTrendChart'].map(id => '<div data-analytics-chart-panel><canvas id="' + id + '"></canvas><p data-analytics-chart-empty hidden>No selected tickets.</p></div>').join(''));
    document.querySelector('script').textContent = JSON.stringify(data);
    const charts = {};
    const context = { document };
    if (available) context.Chart = function (canvas, config) { charts[canvas.getAttribute('id')] = config; };
    vm.runInNewContext(source, context);
    return { data, document, charts };
}

test('dashboard charts preserve exact filtered counts, dates, names and saved status colors', () => {
    const h = harness();
    const status = h.charts.statusChart;
    assert.equal(status.type, 'doughnut');
    assert.deepEqual(Array.from(status.data.labels), h.data.status.labels);
    assert.deepEqual(Array.from(status.data.datasets[0].data), h.data.status.counts);
    assert.deepEqual(Array.from(status.data.datasets[0].backgroundColor), h.data.status.colors);
    const volume = h.charts.dashVolumeTrendChart;
    assert.equal(volume.type, 'line');
    assert.deepEqual(Array.from(volume.data.labels), h.data.volume.labels);
    assert.deepEqual(Array.from(volume.data.datasets[0].data), h.data.volume.counts);
    assert.equal(volume.options.scales.y.beginAtZero, true);
    assert.equal(volume.options.scales.y.ticks.precision, 0);
    assert.equal(volume.options.scales.y.max, undefined);
    assert.equal(volume.data.datasets[0].tension, 0);
});

test('status tooltips use the full selected total and retain zero statuses', () => {
    const h = harness();
    const label = h.charts.statusChart.options.plugins.tooltip.callbacks.label;
    assert.equal(label({ label: 'Renamed resolved', parsed: 2 }), 'Renamed resolved: 2 tickets (33.3%)');
    assert.equal(label({ label: 'Unassigned', parsed: 0 }), 'Unassigned: 0 tickets (0%)');
});

test('compact date ticks keep complete dates and actual counts in tooltips', () => {
    const config = harness().charts.dashVolumeTrendChart;
    const tick = config.options.scales.x.ticks.callback;
    assert.equal(tick.call({ getLabelForValue: () => '2026-11-01' }, 0), 'Nov 1');
    assert.equal(tick.call({ getLabelForValue: () => '2026-10' }, 0), 'Oct 2026');
    assert.equal(tick.call({ getLabelForValue: () => 'Other label' }, 0), 'Other label');
    const tooltip = config.options.plugins.tooltip.callbacks;
    assert.equal(tooltip.title([{ label: '2026-11-01' }]), 'November 1, 2026');
    assert.equal(tooltip.label({ parsed: { y: 4 } }), 'Tickets created: 4');
    assert.equal(tooltip.title([]), '');
});

test('empty and zero activity selections show messages instead of misleading empty charts', () => {
    const h = harness({ status: { labels: ['Resolved'], counts: [0], colors: ['#123456'] }, volume: { labels: [], counts: [] } });
    assert.equal(Object.keys(h.charts).length, 0);
    for (const panel of h.document.querySelectorAll('[data-analytics-chart-panel]')) {
        assert.equal(panel.querySelector('canvas').hidden, true);
        assert.equal(panel.querySelector('[data-analytics-chart-empty]').hidden, false);
    }
});

test('unavailable chart resources leave an explicit fallback on both panels', () => {
    const h = harness({}, false);
    assert.equal(Object.keys(h.charts).length, 0);
    for (const message of h.document.querySelectorAll('[data-analytics-chart-empty]')) {
        assert.equal(message.hidden, false);
        assert.equal(message.textContent, 'Chart unavailable.');
    }
});

test('a single date bucket still renders its actual count without inventing extra points', () => {
    const config = harness({ volume: { labels: ['2026-11'], counts: [25] } }).charts.dashVolumeTrendChart;
    assert.deepEqual(Array.from(config.data.labels), ['2026-11']);
    assert.deepEqual(Array.from(config.data.datasets[0].data), [25]);
});

test('dashboard chart script safely ignores pages without its payload', () => {
    assert.doesNotThrow(() => vm.runInNewContext(source, { document: dom('<main></main>') }));
});
