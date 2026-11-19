const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const dom = require('./dom-harness.cjs');

function harness() {
    const document = dom('<div id="drop-zone-attachments"><input id="input-attachments"></div><div id="preview-container-attachments" class="d-none"><div id="file-list-attachments"></div><div id="new-file-list-attachments"></div></div>');
    class DataTransfer { constructor() { this.files = []; this.items = { add: file => this.files.push(file) }; } }
    const source = fs.readFileSync(path.join(__dirname, '../../resources/views/vendor/backpack/crud/fields/modern_upload.blade.php'), 'utf8');
    const script = source.match(/<script>([\s\S]*?)<\/script>/)[1].replaceAll("{{ $field['name'] }}", 'attachments');
    vm.runInNewContext(script, { document, DataTransfer });
    document.fire('DOMContentLoaded');
    return { document, input: document.getElementById('input-attachments'), list: document.getElementById('new-file-list-attachments'), preview: document.getElementById('preview-container-attachments') };
}

test('actual file selection renders all names and sizes, and removal updates the submitted files', () => {
    const h = harness();
    const dangerous = '<img src=x onerror=alert(1)>.txt';
    h.input.files = [{ name: dangerous, size: 1024 }, { name: 'example.pdf', size: 1048576 }];
    h.input.fire('change');
    assert.equal(h.list.querySelectorAll('[data-upload-filename]').length, 2);
    assert.equal(h.list.querySelector('[data-upload-filename]').textContent, dangerous);
    assert.equal(h.list.querySelectorAll('img').length, 0);
    assert.match(h.list.textContent, /1\.00 MB/);
    assert.equal(h.preview.classList.contains('d-none'), false);
    h.list.querySelector('[data-remove-new-file]').click();
    assert.deepEqual(h.input.files.map(file => file.name), ['example.pdf']);
    h.list.querySelector('[data-remove-new-file]').click();
    assert.equal(h.input.files.length, 0);
    assert.equal(h.preview.classList.contains('d-none'), true);
});

test('drag/drop uses the same preview path and an empty selection clears the preview', () => {
    const h = harness();
    h.document.getElementById('drop-zone-attachments').fire('drop', { dataTransfer: { files: [{ name: 'dropped.txt', size: 25 }] } });
    assert.equal(h.list.querySelector('[data-upload-filename]').textContent, 'dropped.txt');
    h.input.files = []; h.input.fire('change');
    assert.equal(h.list.children.length, 0);
    assert.equal(h.preview.classList.contains('d-none'), true);
});
