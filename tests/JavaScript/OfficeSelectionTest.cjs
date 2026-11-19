const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const dom = require('./dom-harness.cjs');

function issueHarness(dept = '', div = '', retainedNullDepartment = null) {
    const document = dom('<select name="department_id" value="' + dept + '"></select><select name="division_id" value="' + div + '"></select><script data-issue-office-options></script>');
    document.querySelector('script').textContent = JSON.stringify({ retainedNullDepartment, divisions: [
        { id: 10, department_id: 1, division_name: 'Team <one>' },
        { id: 20, department_id: 2, division_name: 'Team two' }] });
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/js/issue-office-selection.js'), 'utf8'), { document });
    return { department: document.querySelector('[name="department_id"]'), division: document.querySelector('[name="division_id"]') };
}

test('issue division stays disabled until department is selected, then shows only matching options', () => {
    const h = issueHarness();
    assert.equal(h.division.disabled, true);
    assert.equal(h.division.required, false);
    h.department.value = '1'; h.department.fire('change');
    assert.equal(h.division.disabled, false);
    assert.equal(h.division.required, true);
    assert.deepEqual(h.division.children.map(option => option.value), ['', '10']);
    assert.equal(h.division.children[1].textContent, 'Team <one>');
    h.division.value = '10'; h.department.value = '2'; h.department.fire('change');
    assert.equal(h.division.value, '');
    assert.deepEqual(h.division.children.map(option => option.value), ['', '20']);
    h.department.value = ''; h.department.fire('change');
    assert.equal(h.division.disabled, true);
});

test('issue department with no divisions enables an optional empty division', () => {
    const h = issueHarness('3');
    assert.equal(h.division.disabled, false);
    assert.equal(h.division.required, false);
    assert.equal(h.division.children.length, 1);
    assert.match(h.division.children[0].textContent, /optional/);
});

test('issue edit retains its matching division and historical department-only selection', () => {
    assert.equal(issueHarness('1', '10').division.value, '10');
    assert.equal(issueHarness('1', '', 1).division.required, false);
});

function reportHarness() {
    const document = dom('<select data-report-department value="1"></select><select data-report-division value="10"></select>');
    const redirects = [];
    const window = { location: { href: 'https://fixture.test/admin/reports?period=custom&start=2026-11-01&end=2026-11-04&department_id=1&division_id=10&chart_department_id=7', assign: value => redirects.push(new URL(value)) } };
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/js/report-office-filters.js'), 'utf8'), { document, window, URL });
    return { department: document.querySelector('[data-report-department]'), division: document.querySelector('[data-report-division]'), redirects };
}

test('report department navigation preserves dates and clears previous division', () => {
    const h = reportHarness();
    h.department.value = '2'; h.department.fire('change');
    const params = h.redirects[0].searchParams;
    assert.equal(params.get('period'), 'custom');
    assert.equal(params.get('start'), '2026-11-01');
    assert.equal(params.get('end'), '2026-11-04');
    assert.equal(params.get('department_id'), '2');
    assert.equal(params.has('division_id'), false);
    assert.equal(params.get('chart_department_id'), '7');
});

test('report division selection and clearing department update the URL correctly', () => {
    const h = reportHarness();
    h.division.value = '20'; h.division.fire('change');
    assert.equal(h.redirects[0].searchParams.get('division_id'), '20');
    h.department.value = ''; h.department.fire('change');
    assert.equal(h.redirects[1].searchParams.has('department_id'), false);
    assert.equal(h.redirects[1].searchParams.has('division_id'), false);
});
