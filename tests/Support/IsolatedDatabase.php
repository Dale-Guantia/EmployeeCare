<?php

namespace Tests\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class IsolatedDatabase
{
    public static function prepare($app): void
    {
        $config = $app['config'];
        self::assertSafeConfiguration($config->get('database.default'), $config->get('database.connections.sqlite'));
        if (!$app->environment('testing')) {
            throw new \LogicException('Tests require APP_ENV=testing.');
        }

        Storage::fake('public');
        Storage::fake('ticket_attachments');
        Storage::fake('local');
        $config->set('filesystems.default', 'local');
        Http::swap(new OfflineHttpFactory($app['events']));
        $app['db']->connection()->setQueryGrammar(new SqliteTestGrammar);

        $app['migration.repository']->createRepository();
        // Only forward migrations, on the verified empty in-memory connection.
        ob_start();
        try {
            $app['migrator']->run([database_path('migrations')]);
        } finally {
            ob_end_clean();
        }

        $seeder = new \Database\Seeders\DatabaseSeeder;
        $seeder->setContainer($app);
        $seeder->run();
    }

    public static function assertSafeConfiguration($connection, $sqlite): void
    {
        if ($connection !== 'sqlite' || !is_array($sqlite)
            || ($sqlite['database'] ?? null) !== ':memory:' || !empty($sqlite['url'])) {
            throw new \LogicException('Tests must use SQLite :memory:, never the application database.');
        }
    }
}
