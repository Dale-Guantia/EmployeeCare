// Compile the real Mix configuration without replacing published application assets.
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawnSync } = require('node:child_process');

const root = path.resolve(__dirname, '../..');
const tempBase = path.resolve(os.tmpdir());
const temporary = fs.mkdtempSync(path.join(tempBase, 'employeecare-build-'));
const output = path.join(temporary, 'output');
const config = path.join(temporary, 'webpack.mix.cjs');
try {
    fs.writeFileSync(config, `require(${JSON.stringify(path.join(root, 'webpack.mix.js'))});
const mix = require(${JSON.stringify(path.join(root, 'node_modules/laravel-mix'))});
mix.setPublicPath(${JSON.stringify(output)});
mix.override(configuration => { configuration.output.path = ${JSON.stringify(output)}; });
`);
    const environment = { ...process.env };
    const pathKey = Object.keys(environment).find(key => key.toLowerCase() === 'path') || 'PATH';
    environment[pathKey] = path.join(root, 'node_modules/.bin') + path.delimiter + (environment[pathKey] || '');
    const result = spawnSync(process.execPath, [path.join(root, 'node_modules/laravel-mix/bin/cli.js'),
        '--production', '--mix-config', config], { cwd: root, env: environment, stdio: 'inherit' });
    if (result.error) throw result.error;
    if (result.status !== 0) throw new Error('Production compilation failed.');
    const files = [];
    function scan(directory) {
        for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
            const file = path.join(directory, entry.name);
            if (entry.isDirectory()) scan(file); else files.push(file);
        }
    }
    scan(output);
    if (!files.some(file => file.endsWith(`${path.sep}app.js`)) || !files.some(file => file.endsWith(`${path.sep}app.css`))) {
        throw new Error('Build did not produce both application entry points.');
    }
    console.log('Production build passed in temporary output; published assets were preserved.');
} finally {
    // Delete only the directory returned by this invocation's mkdtemp, under the OS temp root.
    const relative = path.relative(tempBase, path.resolve(temporary));
    if (relative && !relative.startsWith('..') && !path.isAbsolute(relative)
        && path.basename(temporary).startsWith('employeecare-build-')) {
        fs.rmSync(temporary, { recursive: true, force: true });
    }
}
