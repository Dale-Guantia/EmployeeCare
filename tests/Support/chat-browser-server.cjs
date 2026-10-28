// Temporary loopback-only server. Serves only fixtures and public assets, never PHP/.env.
const http = require('node:http');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');
const fixture = path.join(root, 'storage/framework/testing/hr-chat-browser');
const publicRoot = path.join(root, 'public');
const types = { '.html': 'text/html', '.js': 'application/javascript', '.css': 'text/css', '.woff2': 'font/woff2', '.woff': 'font/woff', '.png': 'image/png', '.svg': 'image/svg+xml', '.ico': 'image/x-icon' };
const server = http.createServer((req, res) => {
    const url = new URL(req.url, 'http://127.0.0.1');
    const route = url.pathname.replace(/^\/nested(?=\/)/, '');
    let file;
    if (route === '/full' || route === '/widget') file = path.join(fixture, route.slice(1) + '.html');
    else if (route === '/fixture.js') file = path.join(__dirname, 'chat-browser-fixture.js');
    else {
        file = path.resolve(publicRoot, '.' + decodeURIComponent(route));
        if (!file.startsWith(publicRoot + path.sep) || !types[path.extname(file)]) { res.writeHead(404).end(); return; }
    }
    fs.readFile(file, (error, data) => {
        if (error) { res.writeHead(404).end(); return; }
        if (path.extname(file) === '.html' && url.pathname.startsWith('/nested/')) {
            data = Buffer.from(data.toString().replaceAll('http://127.0.0.1:8765/', 'http://127.0.0.1:8765/nested/').replaceAll('src="/fixture.js"', 'src="/nested/fixture.js"'));
        }
        res.writeHead(200, { 'Content-Type': (types[path.extname(file)] || 'text/plain') + '; charset=utf-8', 'Cache-Control': 'no-store' }); res.end(data);
    });
});
server.listen(8765, '127.0.0.1', () => console.log('Isolated browser fixture: http://127.0.0.1:8765/full and /widget (also /nested/full).'));
process.on('SIGINT', () => server.close());
