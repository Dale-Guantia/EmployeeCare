const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

class Element {
    constructor(tag) { this.tagName = tag; this.children = []; this.events = {}; this.attributes = {}; this.style = {}; this.value = ''; }
    set innerHTML(value) { throw new Error('Skill controls must not parse HTML.'); }
    setAttribute(key, value) { assert.notEqual(key, 'onclick'); this.attributes[key] = value; }
    getAttribute(key) { return this.attributes[key]; }
    appendChild(node) { node.parent = this; this.children.push(node); }
    insertBefore(node, reference) { node.parent = this; this.children.splice(this.children.indexOf(reference), 0, node); }
    remove() { this.parent.children.splice(this.parent.children.indexOf(this), 1); }
    addEventListener(name, handler) { this.events[name] = handler; }
    fire(name, details = {}) { this.events[name]?.call(this, { preventDefault() {}, ...details }); }
    focus() { this.focused = true; }
    querySelectorAll(selector) {
        if (selector === 'input') return this.children.filter(node => node.tagName === 'input');
        if (selector === '.skill-badge') return this.children.filter(node => node.className?.includes('skill-badge'));
        throw new Error('Unexpected or interpolated selector: ' + selector);
    }
}

function harness(saved = []) {
    const input = new Element('input');
    const container = new Element('div'); container.appendChild(input);
    const hidden = new Element('div');
    for (const value of saved) { const node = new Element('input'); node.value = value; node.name = 'skills[]'; hidden.appendChild(node); }
    const elements = { 'skill-input': input, 'skills-container': container, 'hidden-skills-inputs': hidden };
    const context = vm.createContext({ document: {
        getElementById: id => elements[id], createElement: tag => new Element(tag),
        addEventListener: (name, handler) => { assert.equal(name, 'DOMContentLoaded'); handler(); }
    } });
    const blade = fs.readFileSync(path.join(__dirname, '../../resources/views/vendor/backpack/base/my_account.blade.php'), 'utf8');
    const section = blade.slice(blade.indexOf("@section('after_scripts')"));
    const script = section.match(/<script>([\s\S]*?)<\/script>/)[1];
    vm.runInContext(script, context);
    return { input, container, hidden, context, badges: () => container.querySelectorAll('.skill-badge'),
        values: () => hidden.querySelectorAll('input').map(node => node.value) };
}

test('saved quotes, selector syntax, HTML and Unicode render as literal text and remove safely', () => {
    const values = ['<img src=x onerror="globalThis.pwned=1">', "it's [a] \"skill\"", '"][value="other"]', '日本語 résumé', '</span><script>globalThis.pwned=1</script>'];
    const h = harness(values);
    assert.deepEqual(h.values(), values);
    h.badges().forEach((badge, i) => {
        assert.equal(badge.children[0].textContent, values[i]);
        assert.equal(badge.children[1].tagName, 'button');
        assert.equal(badge.children[1].type, 'button');
        assert.equal(badge.children[1].attributes.onclick, undefined);
    });
    assert.equal(h.context.pwned, undefined);
    h.badges()[2].children[1].fire('click');
    assert.deepEqual(h.values(), values.filter((_, i) => i !== 2));
    assert.equal(h.badges().length, 4);
});

test('adding, case-insensitive duplicate detection and hidden form values remain functional', () => {
    const h = harness(['JavaScript']);
    for (const value of [' javascript ', 'javascript', '   ']) { h.input.value = value; h.input.fire('keydown', { key: 'Enter' }); }
    assert.deepEqual(h.values(), ['JavaScript']);
    const skill = '  <b onclick="pwned=1">[安全] \'quoted\'</b>  ';
    h.input.value = skill; let prevented = false;
    h.input.fire('keydown', { key: ',', preventDefault: () => { prevented = true; } });
    assert.equal(prevented, true);
    assert.equal(h.input.value, '');
    assert.equal(h.badges()[1].children[0].textContent, skill.trim().toLowerCase());
    assert.equal(h.hidden.children[1].name, 'skills[]');
    assert.equal(h.hidden.children[1].value, skill.trim().toLowerCase());
    h.badges()[0].children[1].fire('click');
    assert.deepEqual(h.values(), [skill.trim().toLowerCase()]);
});

test('empty-input Backspace removes the last tag and container click focuses the input', () => {
    const h = harness(['first', 'last["quoted"]']);
    h.input.fire('keyup', { key: 'Backspace' });
    assert.deepEqual(h.values(), ['first']);
    assert.equal(h.badges().length, 1);
    h.container.fire('click'); assert.equal(h.input.focused, true);
});
