const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const dom = require('./dom-harness.cjs');
const source = fs.readFileSync(path.join(__dirname, '../../public/js/department-division-charts.js'), 'utf8');

function harness(data, chartAvailable = true, parameter = 'chart_department_id') {
    const document = dom('<select data-division-chart-department data-division-chart-param="' + parameter + '" value="7"></select><div data-independent-division-chart><canvas></canvas><p data-chart-empty hidden></p><script data-division-chart-data></script></div>');
    document.querySelector('script').textContent = JSON.stringify({ colors: '#375ca6', counts: [4], reassigned: [2], kind: 'tickets', ...data });
    const charts = [], redirects = [];
    const window = { location: { href: 'https://fixture.test/admin/dashboard?period=custom&start=2026-11-01&end=2026-11-05&department_id=2&division_id=9&chart_department_id=3', assign: url => redirects.push(new URL(url)) } };
    const context = { document, window, URL };
    if (chartAvailable) context.Chart = function (canvas, config) { charts.push(config); };
    vm.runInNewContext(source, context);
    return { document, charts, redirects };
}

test('chart department navigation preserves global filters and dates without adding a division filter', () => {
    const h = harness({ labels: ['Division'], values: [4] });
    h.document.querySelector('select').fire('change');
    const params = h.redirects[0].searchParams;
    assert.equal(params.get('chart_department_id'), '7');
    assert.equal(params.get('department_id'), '2');
    assert.equal(params.get('division_id'), '9');
    assert.equal(params.get('period'), 'custom');
    assert.equal(params.get('start'), '2026-11-01');
    assert.equal(params.get('end'), '2026-11-05');
    assert.equal(params.has('chart_division_id'), false);
});

test('long labels are shortened on the axis while tooltips retain the complete name', () => {
    const name = 'Very long division name '.repeat(6) + '✓';
    const h = harness({ labels: [name], values: [4] });
    const config = h.charts[0];
    assert.equal(config.options.indexAxis, 'y');
    const label = config.options.scales.y.ticks.callback.call({ getLabelForValue: () => name }, 0);
    assert.equal(Array.from(label).length, 28);
    assert.match(label, /…$/);
    assert.equal(config.options.plugins.tooltip.callbacks.title([{ dataIndex: 0 }]).join(''), name);
    assert.equal(config.options.plugins.tooltip.callbacks.label({ dataIndex: 0 }), 'Tickets: 4');
    assert.equal(config.options.scales.y.ticks.autoSkip, false);
    assert.equal(config.options.maintainAspectRatio, false);
});

test('zero activity divisions remain visible while a department without divisions gets an empty state', () => {
    assert.equal(harness({ labels: ['Zero activity'], values: [0] }).charts.length, 1);
    const h = harness({ labels: [], values: [] });
    assert.equal(h.charts.length, 0);
    assert.equal(h.document.querySelector('canvas').hidden, true);
    assert.match(h.document.querySelector('[data-chart-empty]').textContent, /No divisions/);
});


test('dashboard date navigation retains its division chart department and clears retired reassignment filters', () => {
    const view = fs.readFileSync(path.join(__dirname, '../../resources/views/vendor/backpack/base/dashboard.blade.php'), 'utf8');
    const match = view.match(/function buildDashboardUrl\(params\)\s*\{[\s\S]*?return url\.toString\(\);\s*\}/);
    assert.ok(match);
    const document = dom('<select data-report-department value="2"></select><select data-report-division value="9"></select><select data-division-chart-department value="7"></select>');
    const context = { document, window: { location: { href: 'https://fixture.test/admin/dashboard?reassignment_department_id=4&reassignment_period=all' } }, URL };
    vm.runInNewContext(match[0] + '; result = buildDashboardUrl({period:"month"});', context);
    const params = new URL(context.result).searchParams;
    assert.equal(params.get('period'), 'month');
    assert.equal(params.get('chart_department_id'), '7');
    assert.equal(params.get('department_id'), '2');
    assert.equal(params.get('division_id'), '9');
    assert.equal(params.has('reassignment_period'), false);
    assert.equal(params.has('reassignment_department_id'), false);
});
