const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const dom = require('./dom-harness.cjs');
const source = fs.readFileSync(path.join(__dirname, '../../public/js/survey-report-charts.js'), 'utf8');

function harness(counts, chartAvailable = true, unit = 'Responses') {
    const document = dom('<div data-survey-chart><canvas></canvas><p data-chart-empty hidden></p><script data-survey-chart-data></script></div>');
    document.querySelector('script').textContent = JSON.stringify({ title: 'Distribution', labels: ['Agree', 'Disagree'], counts, unit });
    const configurations = [];
    const context = { document };
    if (chartAvailable) context.Chart = function (canvas, config) { configurations.push(config); };
    vm.runInNewContext(source, context);
    return { document, configurations };
}

test('survey charts show a clear empty state for zero responses instead of an empty grid', () => {
    const h = harness([0, 0]);
    assert.equal(h.configurations.length, 0);
    assert.equal(h.document.querySelector('canvas').hidden, true);
    assert.equal(h.document.querySelector('[data-chart-empty]').hidden, false);
    assert.match(h.document.querySelector('[data-chart-empty]').textContent, /No data/);
});

test('nonempty survey charts use the exact counts and horizontal integer axes', () => {
    const h = harness([27, 3]);
    assert.equal(h.configurations.length, 1);
    const config = h.configurations[0];
    assert.equal(JSON.stringify(config.data.datasets[0].data), '[27,3]');
    assert.equal(config.type, 'bar');
    assert.equal(config.options.indexAxis, 'y');
    assert.equal(config.options.scales.x.beginAtZero, true);
    assert.equal(config.options.scales.x.ticks.precision, 0);
    assert.equal(config.options.maintainAspectRatio, false);
});

test('missing chart resources leave the summary readable with an explicit fallback', () => {
    const h = harness([2, 1], false);
    assert.equal(h.document.querySelector('canvas').hidden, true);
    assert.match(h.document.querySelector('[data-chart-empty]').textContent, /Summary counts are shown above/);
});

test('CSS rating totals are labelled as rating answers rather than response counts', () => {
    const h = harness([4, 0], true, 'Rating answers');
    assert.equal(h.configurations[0].data.datasets[0].label, 'Rating answers');
});

test('survey chart labels are compact while tooltips preserve full names and rating counts', () => {
    const config = harness([27, 3], true, 'Rating answers').configurations[0];
    const name = 'A staff member with a long descriptive display name';
    const label = config.options.scales.y.ticks.callback.call({ getLabelForValue: () => name }, 0);
    assert.equal(Array.from(label).length, 28);
    assert.equal(config.options.scales.y.ticks.autoSkip, false);
    assert.equal(config.options.plugins.tooltip.callbacks.title([{ dataIndex: 0 }]).join(''), 'Agree');
    assert.equal(config.options.plugins.tooltip.callbacks.label({ dataIndex: 0 }), 'Rating answers: 27');
    assert.equal(config.options.scales.x.max, undefined);
});
