const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const dom = require('./dom-harness.cjs');
const source = fs.readFileSync(path.join(__dirname, '../../public/js/report-tables.js'), 'utf8');

function harness(info = { pages: 1, recordsTotal: 2 }, existing = false, ajax = false) {
    const document = dom('<div id="wrapper"><div class="report-table-title"></div><table data-report-table ' + (ajax ? 'data-report-ajax="/survey/responses"' : '') + ' data-report-order-column="6" aria-label="Latest Tickets"><thead><tr><th>Details</th><th>Name</th><th data-report-rating="true">Rating</th><th>Date</th></tr></thead><tbody><tr><td><button class="report-details-toggle" aria-expanded="false">+</button></td></tr></tbody></table><div class="dataTables_paginate"></div><div class="dataTables_length"></div></div>');
    const wrapper = document.getElementById('wrapper');
    const table = document.querySelector('table');
    const events = {};
    let options, initializations = 0;
    const api = { page: { info: () => info }, table: () => ({ container: () => wrapper }) };
    function $(target) {
        if (typeof target === 'function') return target();
        return { DataTable: value => {
            options = value; initializations++;
            value.drawCallback.call({ api: () => api });
            return api;
        }, on: (event, handler) => { events[event] = handler; } };
    }
    $.fn = { dataTable: { isDataTable: () => existing, render: { text: () => ({ display: value => String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;') }) } } };
    const context = { document, window: { jQuery: $ } };
    vm.runInNewContext(source, context);
    return { document, table, wrapper, events, api, info, options: () => options, initializations: () => initializations,
        draw: () => options.drawCallback.call({ api: () => api }) };
}

test('small and empty tables hide pagination and length but retain record information', () => {
    for (const total of [0, 2, 10]) {
        const h = harness({ pages: total ? 1 : 0, recordsTotal: total });
        assert.equal(h.wrapper.querySelector('.dataTables_paginate').hidden, true);
        assert.equal(h.wrapper.querySelector('.dataTables_length').hidden, true);
        assert.match(h.options().dom, /li/);
        assert.match(h.options().dom, /f/);
        assert.equal(h.options().pageLength, 10);
    }
});

test('pagination updates after search and length remains available for a larger dataset', () => {
    const h = harness({ pages: 3, recordsTotal: 25 });
    assert.equal(h.wrapper.querySelector('.dataTables_paginate').hidden, false);
    assert.equal(h.wrapper.querySelector('.dataTables_length').hidden, false);
    h.info.pages = 1; h.draw();
    assert.equal(h.wrapper.querySelector('.dataTables_paginate').hidden, true);
    assert.equal(h.wrapper.querySelector('.dataTables_length').hidden, false);
    h.info.pages = 3; h.draw();
    assert.equal(h.wrapper.querySelector('.dataTables_paginate').hidden, false);
});

test('only the details button expands rows and expanded state is announced', () => {
    const h = harness();
    assert.equal(h.options().responsive.details.target, 'button.report-details-toggle');
    assert.equal(h.options().responsive.details.type, 'column');
    assert.equal(h.options().columnDefs[0].orderable, false);
    const row = { node: () => h.table.querySelector('tbody tr') };
    const button = row.node().querySelector('button');
    h.events['responsive-display.dt']({}, h.api, row, true);
    assert.equal(button.getAttribute('aria-expanded'), 'true');
    assert.equal(button.getAttribute('aria-label'), 'Hide additional details');
    assert.equal(button.textContent, '−');
    h.events['responsive-display.dt']({}, h.api, row, false);
    assert.equal(button.getAttribute('aria-expanded'), 'false');
    assert.equal(button.getAttribute('aria-label'), 'Show additional details');
    assert.equal(button.textContent, '+');
});

test('latest tickets sort by the timestamp column and heading is inserted as text', () => {
    const h = harness();
    assert.equal(JSON.stringify(h.options().order), '[[6,"desc"]]');
    assert.equal(h.wrapper.querySelector('h5').textContent, 'Latest Tickets');
});

test('an initialized table is not initialized a second time', () => {
    assert.equal(harness(undefined, true).initializations(), 0);
});

test('the script safely ignores pages without DataTables', () => {
    assert.doesNotThrow(() => vm.runInNewContext(source, { window: {} }));
});

test('survey tables preserve server paging and map the leading control column out of ordering requests', () => {
    const h = harness({ pages: 4, recordsTotal: 35 }, false, true);
    const options = h.options();
    assert.equal(options.serverSide, true);
    assert.equal(options.processing, true);
    assert.equal(options.ajax.url, '/survey/responses');
    assert.equal(options.columns.length, 4);
    assert.equal(options.columns[0].data, null);
    assert.equal(options.columns[0].searchable, false);
    assert.match(options.columns[0].defaultContent, /report-details-toggle/);
    assert.equal(JSON.stringify(options.columns.slice(1).map(column => column.data)), '[0,1,2]');
    const params = { order: [{ column: 8, dir: 'desc' }], start: 20, length: 10, search: { value: 'Service 26' } };
    options.ajax.data(params);
    assert.equal(params.order[0].column, 7);
    assert.equal(params.start, 20);
    assert.equal(params.search.value, 'Service 26');
    assert.equal(h.wrapper.querySelector('.dataTables_paginate').hidden, false);
    options.ajax.data({});
});

test('survey ajax data stays escaped and rating badges use only known safe classes', () => {
    const options = harness(undefined, false, true).options();
    assert.equal(options.columns[1].render('<img src=x onerror=alert(1)>', 'display'), '&lt;img src=x onerror=alert(1)&gt;');
    const rating = options.columns[2].render;
    assert.match(rating('Very Satisfied', 'display'), /ec-rating-positive/);
    assert.match(rating('Strongly Disagree', 'display'), /ec-rating-negative/);
    assert.match(rating('<script>bad</script>', 'display'), /ec-rating-neutral/);
    assert.doesNotMatch(rating('<script>bad</script>', 'display'), /<script>/);
    assert.match(rating('constructor', 'display'), /ec-rating-neutral/);
    assert.equal(rating('Agree', 'sort'), 'Agree');
    assert.match(rating(null, 'display'), /N\/A/);
});
