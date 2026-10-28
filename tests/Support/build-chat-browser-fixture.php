<?php

// Render the actual Blade views using disposable SQLite and fake storage only.
require __DIR__ . '/../../vendor/autoload.php';
foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DATABASE_URL' => '',
    'CACHE_DRIVER' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync'] as $name => $value) {
    putenv($name . '=' . $value); $_ENV[$name] = $value; $_SERVER[$name] = $value;
}
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\Tests\Support\IsolatedDatabase::prepare($app);
$user = \App\Models\User::create(['name' => 'Browser fixture', 'username' => 'browser-fixture', 'email' => 'browser@example.test',
    'password' => bcrypt('fixture-only'), 'is_active' => true]);
$user->assignRole('employee');
backpack_auth()->login($user);
$request = \Illuminate\Http\Request::create('http://127.0.0.1:8765/admin/hr-assistant');
$request->setLaravelSession($app['session']->driver());
$request->setRouteResolver(function () use ($app) { return $app['router']->getRoutes()->getByName('hr.chat.index'); });
$app->instance('request', $request);
$app['url']->forceRootUrl('http://127.0.0.1:8765');
$directory = storage_path('framework/testing/hr-chat-browser');
if (!is_dir($directory)) { mkdir($directory, 0755, true); }
$harness = '<script src="/fixture.js"></script>';
$page = view('admin.hr_chat', ['topPrompts' => ['Wellness leave?', '<img src=x onerror=alert(1)>']])->render();
$page = str_replace('<head>', '<head>' . $harness, $page);
file_put_contents($directory . '/full.html', $page);
$request->setRouteResolver(function () { return null; });
$widget = view(backpack_view('inc.hr-float-widget'))->render();
file_put_contents($directory . '/widget.html', '<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1">'
    . $harness . '<link rel="stylesheet" href="/packages/backpack/base/css/blue-bundle.css">'
    . '<link rel="stylesheet" href="/packages/line-awesome/css/line-awesome.min.css"></head><body><h1>Isolated floating widget</h1>' . $widget . '</body></html>');
echo "Rendered isolated full-page and widget fixtures. No live database or provider requests.\n";
