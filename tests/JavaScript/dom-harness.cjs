// Minimal event-capable DOM for exercising the actual UI scripts without a browser.
const { parseDOM } = require('htmlparser2');
const select = require('css-select');
const { Element, Text } = require('domhandler');
const { textContent, getInnerHTML } = require('domutils');

module.exports = function dom(html) {
    const document = { type: 'root', children: parseDOM(html) };
    function decorate(node) {
        if (node._decorated) return node;
        node._decorated = true;
        node.events = {};
        node.style = {};
        node.value = node.attribs?.value || '';
        node.files = [];
        node.addEventListener = (name, fn) => { (node.events[name] ||= []).push(fn); };
        node.dispatchEvent = event => { event.target ||= node; for (const fn of node.events[event.type] || []) fn.call(node, event); };
        node.fire = (name, extra = {}) => node.dispatchEvent({ type: name, preventDefault() {}, stopPropagation() {}, ...extra });
        node.setAttribute = (name, value) => { node.attribs[name] = String(value); };
        node.getAttribute = name => node.attribs[name];
        node.querySelector = selector => select.selectOne(selector, node.children || []);
        node.querySelectorAll = selector => select.selectAll(selector, node.children || []);
        node.closest = selector => { let current = node; while (current) { if (select.is(current, selector)) return current; current = current.parent; } return null; };
        node.appendChild = child => { child.parent = node; node.children.push(child); return child; };
        node.replaceChildren = (...children) => { node.children = []; children.forEach(child => node.appendChild(child)); };
        node.remove = () => { node.parent.children = node.parent.children.filter(child => child !== node); };
        node.focus = () => { document.activeElement = node; };
        node.click = () => node.fire('click');
        node.classList = {
            add: name => { node.attribs.class = [...new Set((node.attribs.class || '').split(/\s+/).concat(name))].join(' '); },
            remove: name => { node.attribs.class = (node.attribs.class || '').split(/\s+/).filter(value => value !== name).join(' '); },
            contains: name => (node.attribs.class || '').split(/\s+/).includes(name)
        };
        node.dataset = new Proxy({}, { get: (_, name) => node.attribs?.['data-' + name.replace(/[A-Z]/g, value => '-' + value.toLowerCase())] });
        Object.defineProperty(node, 'className', { get: () => node.attribs?.class || '', set: value => { node.attribs.class = value; } });
        Object.defineProperty(node, 'textContent', { get: () => textContent(node), set: value => { node.children = [new Text(String(value))]; } });
        Object.defineProperty(node, 'innerHTML', { get: () => getInnerHTML(node), set: value => { node.children = parseDOM(String(value)); node.children.forEach(child => { child.parent = node; decorate(child); }); } });
        (node.children || []).forEach(decorate);
        return node;
    }
    decorate(document);
    document.createElement = tag => decorate(new Element(tag, {}));
    document.getElementById = id => document.querySelector('#' + id);
    return document;
};
