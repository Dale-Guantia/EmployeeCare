const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const dom = require('./dom-harness.cjs');
const source = fs.readFileSync(path.join(__dirname, '../../public/js/survey-report-filters.js'), 'utf8');

function harness(period = 'month', offices = true) {
    const document = dom('<form data-global-report-filters><select name="period" value="' + period + '"></select>' +
        (offices ? '<select name="department_id" value="2"></select><select name="division_id" value="9"></select>' : '') +
        '<div data-report-half><select name="half" value="h1"></select></div>' +
        '<div data-report-custom><input name="start" value="2026-11-01"><input name="end" value="2026-11-05"></div></form>');
    const form = document.querySelector('form');
    const submissions = [];
    form.requestSubmit = () => submissions.push(Object.fromEntries(form.querySelectorAll('select,input').map(input =>
        [input.getAttribute('name'), input.disabled ? null : input.value])));
    vm.runInNewContext(source, { document });
    return { document, form, submissions };
}

test('date filters show only their matching controls and custom dates wait for Apply', () => {
    const h = harness();
    const period = h.form.querySelector('[name="period"]');
    const half = h.form.querySelector('[data-report-half]');
    const custom = h.form.querySelector('[data-report-custom]');
    assert.equal(half.hidden, true);
    assert.equal(custom.hidden, true);
    period.value = 'custom';
    period.fire('change');
    assert.equal(custom.hidden, false);
    assert.equal(h.submissions.length, 0);
    assert.equal(custom.querySelector('input').required, true);
    period.value = 'all';
    period.fire('change');
    assert.equal(custom.hidden, true);
    assert.equal(custom.querySelector('input').required, false);
    assert.equal(h.submissions[0].period, 'all');
});

test('CSS department changes clear the old division and preserve the selected dates', () => {
    const h = harness('custom');
    const department = h.form.querySelector('[name="department_id"]');
    const division = h.form.querySelector('[name="division_id"]');
    department.value = '3';
    department.fire('change');
    assert.equal(division.value, '');
    assert.equal(division.disabled, false);
    assert.equal(h.submissions[0].department_id, '3');
    assert.equal(h.submissions[0].start, '2026-11-01');
    assert.equal(h.submissions[0].end, '2026-11-05');
    department.value = '';
    department.fire('change');
    assert.equal(division.disabled, true);
    assert.equal(h.submissions[1].division_id, null);
});

test('division and half changes submit their values and ARTA works without office controls', () => {
    const h = harness('half');
    h.form.querySelector('[name="division_id"]').fire('change');
    assert.equal(h.submissions[0].division_id, '9');
    const arta = harness('half', false);
    const half = arta.form.querySelector('[name="half"]');
    half.value = 'h2';
    half.fire('change');
    assert.equal(arta.submissions[0].half, 'h2');
    assert.equal(arta.form.querySelector('[data-report-half]').hidden, false);
    assert.equal(arta.form.querySelector('[name="department_id"]'), null);
});

test('the filter script safely ignores other report pages', () => {
    assert.doesNotThrow(() => vm.runInNewContext(source, { document: dom('<main></main>') }));
});
