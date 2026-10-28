<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

/** Explicit record-only authorization; never used by the isolated migration suite. */
class AuthorizedEngineDatabase
{
    public const TARGET = 'employeecare@DESKTOP-AQSB8I2:1433';

    public static function boot()
    {
        if (getenv('ENGINE_DATA_TEST_CONFIRM') !== self::TARGET) {
            throw new \LogicException('Data probes require explicit ENGINE_DATA_TEST_CONFIRM for the authorized target.');
        }
        $values = \Dotenv\Dotenv::parse(file_get_contents(dirname(__DIR__, 2) . '/.env'));
        $username = getenv('ENGINE_DATA_TEST_USERNAME');
        $password = getenv('ENGINE_DATA_TEST_PASSWORD');
        $username = $username === false ? ($values['DB_USERNAME'] ?? null) : $username;
        $password = $password === false ? ($values['DB_PASSWORD'] ?? null) : $password;
        // Explicit empty local credentials are valid for Windows integrated authentication.
        if (!is_string($username) || !is_string($password)) {
            throw new \LogicException('Authorized data probes need local credentials; never supply them in chat.');
        }
        $app = EngineDatabase::bootApplication(['driver' => 'sqlsrv', 'host' => 'DESKTOP-AQSB8I2', 'port' => '1433',
            'database' => 'employeecare', 'username' => $username, 'password' => $password, 'url' => null,
            'prefix' => '', 'charset' => 'utf8', 'encrypt' => 'yes', 'trust_server_certificate' => true]);
        $identity = DB::selectOne("SELECT CAST(SERVERPROPERTY('MachineName') AS varchar(128)) AS machine_name,
            CAST(SERVERPROPERTY('ProductVersion') AS varchar(40)) AS engine_version, DB_NAME() AS db_name");
        if (strcasecmp($identity->machine_name, 'DESKTOP-AQSB8I2') !== 0 || strcasecmp($identity->db_name, 'employeecare') !== 0) {
            throw new \LogicException('Authorized data probe connected to the wrong server/database.');
        }
        return $app;
    }

    public static function assertOwner(string $token): int
    {
        if (!preg_match('/\A[a-f0-9]{32}\z/D', $token)) { throw new \LogicException('Invalid fixture token.'); }
        $user = DB::table('users')->where('username', 'ec_engine_probe_' . $token)->where('is_active', false)->first(['id']);
        if (!$user) { throw new \LogicException('Authorized worker fixture is absent.'); }
        return (int) $user->id;
    }
}
