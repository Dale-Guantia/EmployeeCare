<?php

namespace Tests\Support;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/** Opt-in only. Never inherits the application's connection, URL, cache, or .env. */
class EngineDatabase
{
    public static function configuration(array $values, array $protectedNames = []): array
    {
        foreach (['DRIVER', 'HOST', 'PORT', 'DATABASE', 'USERNAME', 'PASSWORD', 'CONFIRM'] as $key) {
            if (!array_key_exists($key, $values) || $values[$key] === false) {
                throw new \LogicException('Missing explicit ENGINE_TEST_' . $key . '.');
            }
        }
        $name = $values['DATABASE'];
        if (!in_array($values['DRIVER'], ['sqlsrv', 'mysql'], true)
            || !preg_match('/\Aec_engine_test_[a-z0-9]{8,32}\z/D', $name)
            || $values['CONFIRM'] !== $name || $values['HOST'] === '' || $values['USERNAME'] === ''
            || !ctype_digit((string) $values['PORT'])) {
            throw new \LogicException('Engine tests require an explicitly confirmed isolated database named ec_engine_test_<8-32 lowercase letters/digits>.');
        }
        foreach ($protectedNames as $protected) {
            if (strcasecmp($name, (string) $protected) === 0) { throw new \LogicException('Application database is forbidden.'); }
        }
        return ['driver' => $values['DRIVER'], 'host' => $values['HOST'], 'port' => $values['PORT'],
            'database' => $name, 'username' => $values['USERNAME'], 'password' => $values['PASSWORD'],
            'url' => null, 'prefix' => '', 'prefix_indexes' => true, 'charset' => $values['DRIVER'] === 'mysql' ? 'utf8mb4' : 'utf8',
            'collation' => 'utf8mb4_unicode_ci', 'strict' => true, 'encrypt' => 'yes',
            'trust_server_certificate' => ($values['TRUST_CERTIFICATE'] ?? '') === '1'];
    }

    public static function boot()
    {
        $values = [];
        foreach (['DRIVER', 'HOST', 'PORT', 'DATABASE', 'USERNAME', 'PASSWORD', 'CONFIRM', 'TRUST_CERTIFICATE'] as $key) {
            $values[$key] = getenv('ENGINE_TEST_' . $key);
        }
        // Compare against local and cached database names without displaying any configuration.
        $base = dirname(__DIR__, 2);
        $protected = [];
        if (is_file($base . '/.env')) {
            $parsed = \Dotenv\Dotenv::parse(file_get_contents($base . '/.env'));
            if (isset($parsed['DB_DATABASE'])) { $protected[] = $parsed['DB_DATABASE']; }
            if (!empty($parsed['DATABASE_URL'])) { $protected[] = ltrim((string) parse_url($parsed['DATABASE_URL'], PHP_URL_PATH), '/'); }
        }
        if (is_file($base . '/bootstrap/cache/config.php')) {
            $cached = require $base . '/bootstrap/cache/config.php';
            foreach ($cached['database']['connections'] ?? [] as $connection) {
                $protected[] = $connection['database'] ?? '';
                if (!empty($connection['url'])) { $protected[] = ltrim((string) parse_url($connection['url'], PHP_URL_PATH), '/'); }
            }
        }
        $connection = self::configuration($values, $protected);
        return self::bootApplication($connection);
    }

    public static function bootApplication(array $connection)
    {
        $base = dirname(__DIR__, 2);
        foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DATABASE_URL' => '',
            'CACHE_DRIVER' => 'array', 'SESSION_DRIVER' => 'array', 'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync',
            'APP_CONFIG_CACHE' => $base . '/tests/Engine/.disabled-config-cache.php'] as $key => $value) {
            putenv($key . '=' . $value); $_ENV[$key] = $value; $_SERVER[$key] = $value;
        }
        if (is_file(getenv('APP_CONFIG_CACHE'))) { throw new \LogicException('Unexpected engine-test config cache.'); }
        $app = require $base . '/bootstrap/app.php';
        // This directory deliberately has no application .env file.
        $app->useEnvironmentPath($base . '/tests/Engine')->loadEnvironmentFrom('.engine-env-disabled');
        $app->make(Kernel::class)->bootstrap();
        $app['config']->set('database.connections', ['engine_test' => $connection]);
        $app['config']->set('database.default', 'engine_test');
        $app['db']->setDefaultConnection('engine_test');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('mail.default', 'array');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('logging.channels.engine_test_null', ['driver' => 'monolog', 'handler' => \Monolog\Handler\NullHandler::class]);
        $app['config']->set('logging.default', 'engine_test_null');
        Http::swap(new OfflineHttpFactory($app['events']));
        Storage::fake('public'); Storage::fake('ticket_attachments'); Storage::fake('local');
        foreach (['public', 'ticket_attachments', 'local'] as $name) {
            $app['config']->set('filesystems.disks.' . $name . '.root', Storage::disk($name)->path(''));
        }
        try {
            $driver = $connection['driver'];
            $actual = DB::selectOne($driver === 'sqlsrv' ? 'SELECT DB_NAME() AS db_name' : 'SELECT DATABASE() AS db_name');
            if (strcasecmp($actual->db_name, $connection['database']) !== 0) { throw new \LogicException('Wrong database identity.'); }
        } catch (\Throwable $e) {
            throw new \RuntimeException('Isolated engine connection/identity check failed; no migrations run. Check test-only credentials, network, driver and TLS.');
        }
        return $app;
    }

    public static function claimEmptyDatabase(string $token): void
    {
        $driver = DB::connection()->getDriverName();
        $objects = DB::select($driver === 'sqlsrv'
            ? "SELECT name FROM sys.objects WHERE is_ms_shipped = 0"
            : 'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()');
        if ($objects) { throw new \LogicException('Engine test database must be completely empty; nothing was changed.'); }
        \Illuminate\Support\Facades\Schema::create('ec_engine_test_owner', function ($table) { $table->string('token', 64); });
        DB::table('ec_engine_test_owner')->insert(['token' => $token]);
    }

    public static function assertOwner(string $token): void
    {
        if (!preg_match('/\A[a-f0-9]{64}\z/D', $token)
            || DB::table('ec_engine_test_owner')->count() !== 1
            || DB::table('ec_engine_test_owner')->value('token') !== $token) {
            throw new \LogicException('Engine worker ownership check failed.');
        }
    }
}
