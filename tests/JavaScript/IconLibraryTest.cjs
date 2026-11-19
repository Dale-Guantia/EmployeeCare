const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const dom = require('./dom-harness.cjs');

test('the icon library opens, paginates, searches, and applies a selected glyph to the submitted input', () => {
    const choices = Object.fromEntries(Array.from({ length: 60 }, (_, i) => ['fas fa-item-' + i, 'Item ' + i]));
    choices['fab fa-github'] = 'GitHub';
    const document = dom('<div data-icon-picker><input value="fas fa-home"><i data-icon-preview></i><details data-icon-library><summary>Icons</summary><input type="search" data-icon-search><div data-icon-grid></div><button data-icon-previous></button><span data-icon-page></span><button data-icon-next></button></details><script data-icon-options type="application/json">' + JSON.stringify(choices) + '</script></div>');
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/js/icon-picker.js'), 'utf8'), {
        document, Event: class { constructor(type) { this.type = type; } }
    });
    const library = document.querySelector('[data-icon-library]');
    const grid = document.querySelector('[data-icon-grid]');
    const input = document.querySelector('[data-icon-picker] > input');
    library.open = true; library.fire('toggle');
    assert.equal(grid.children.length, 60);
    document.querySelector('[data-icon-next]').click();
    assert.equal(grid.children.length, 1);
    const search = document.querySelector('[data-icon-search]');
    search.value = 'github'; search.fire('input');
    assert.equal(grid.children.length, 1);
    assert.equal(grid.children[0].getAttribute('aria-label'), 'GitHub (fab fa-github)');
    grid.children[0].click();
    assert.equal(input.value, 'fab fa-github');
    assert.equal(document.querySelector('[data-icon-preview]').className, 'fab fa-github');
    assert.equal(library.open, false);
    search.value = 'nonexistent'; search.fire('input');
    assert.equal(grid.children.length, 0);
    assert.equal(document.querySelector('[data-icon-page]').textContent, 'No matching icons');
});
