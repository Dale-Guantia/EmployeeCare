const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const root = path.resolve(__dirname, '../..');

test('C10 uploaded filenames remain literal text in the actual preview function', () => {
    const source = fs.readFileSync(path.join(root, 'resources/views/vendor/backpack/crud/fields/modern_upload.blade.php'), 'utf8');
    const start = source.indexOf('    function updateNewFilePreviews(files)');
    const end = source.indexOf('    function removeNewFile(', start);
    assert.ok(start > 0 && end > start);
    const items = [];
    const context = {
        newFileList: { innerHTML: '', appendChild: item => items.push(item) },
        previewContainer: { classList: { remove() {} } },
        document: { createElement() {
            const filename = { textContent: '' };
            return { style: {}, innerHTML: '', filename, querySelector: selector => selector === '[data-upload-filename]' ? filename : { addEventListener() {} } };
        } },
        removeNewFile() {}
    };
    vm.createContext(context);
    vm.runInContext(source.slice(start, end), context);
    const name = '<img src=x onerror="globalThis.injected=true">&unsafe.txt';
    context.updateNewFilePreviews([{ name, size: 2048 }, { name: 'ordinary.pdf', size: 1048576 }]);
    assert.equal(items[0].filename.textContent, name);
    assert.equal(items[0].innerHTML.includes(name), false);
    assert.equal(items[1].filename.textContent, 'ordinary.pdf');
    assert.match(items[1].innerHTML, /1\.00 MB/);
});

test('H3 frontend lockfile matches all direct dependency requirements', () => {
    const manifest = JSON.parse(fs.readFileSync(path.join(root, 'package.json'), 'utf8'));
    const lock = JSON.parse(fs.readFileSync(path.join(root, 'package-lock.json'), 'utf8'));
    assert.deepEqual(lock.packages[''].devDependencies, manifest.devDependencies);
    for (const name of Object.keys(manifest.devDependencies)) {
        assert.ok(lock.packages['node_modules/' + name].version);
        assert.ok(lock.packages['node_modules/' + name].integrity);
    }
});
