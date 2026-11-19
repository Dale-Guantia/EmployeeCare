const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

function picker(value) {
    const events = {};
    const input = { value, addEventListener: (name, callback) => { events[name] = callback; } };
    const preview = { className: '' };
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/js/icon-picker.js'), 'utf8'), {
        document: { querySelectorAll: () => [{ querySelector: selector => selector === 'input' ? input : preview }] }
    });
    return { input, preview, events };
}

test('saved solid, regular and brand icons preview without changing stored values', () => {
    for (const value of ['fas fa-balance-scale', 'far fa-address-book', 'fab fa-github']) {
        const h = picker(value);
        assert.equal(h.preview.className, value);
        assert.equal(h.input.value, value);
    }
});

test('short icon choices normalize and full choices preview on input', () => {
    const h = picker('');
    h.input.value = 'fa-home'; h.events.change();
    assert.equal(h.input.value, 'fas fa-home');
    assert.equal(h.preview.className, 'fas fa-home');
    h.input.value = 'fab fa-github'; h.events.input();
    assert.equal(h.preview.className, 'fab fa-github');
});

test('clearing and invalid values cannot inject preview markup or attributes', () => {
    const h = picker('fas fa-home');
    for (const value of ['', '<img src=x onerror=alert(1)>', 'fas fa-home" onclick="alert(1)']) {
        h.input.value = value; h.events.input();
        assert.equal(h.preview.className, '');
    }
});
