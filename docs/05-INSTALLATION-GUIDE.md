# 05 - Installation and Configuration Guide

## Scope and prerequisites

Use these steps in a **new, isolated development installation**. They are instructions, not commands executed against your application database during documentation. Existing-system deployment is covered [separately](09-DEPLOYMENT-AND-MAINTENANCE.md).

- Project standard PHP 7.4/Laravel 8; inspected CLI PHP 7.4.14. Do not treat `^7.3|^8.0` in Composer as proof of compatibility with every PHP 8 release.
- Composer with access to locked dependencies; `composer.lock` must be retained. PHP extensions must satisfy the lockfile; run `composer check-platform-reqs` after installation.
- MySQL with a separate empty database and restricted development account, or SQL Server with matching PHP SQLSRV/PDO_SQLSRV and ODBC drivers. Exact supported server release is **Not Verified**.
- PHP database extension for the selected engine; mbstring, fileinfo, XML/DOM and the extensions required by the installed packages. GD/image support is needed where rendering/conversion code uses it. Inspect `php -m`; do not assume Apache uses the CLI's php.ini.
- Node/npm for Mix compilation. No Node engine range is declared in package.json; a supported version for the existing lock must be validated. Node v26.3.1 ran the current JavaScript tests, not an installation/build certification.
- Writable `storage` and `bootstrap/cache`; web server document root at `public`.
- Tesseract/Poppler plus selected language files for OCR and a worker for browser-uploaded PDFs.

Sources: [composer.json](../composer.json), [package.json](../package.json), [filesystem config](../config/filesystems.php), [OCR service](../app/Services/OcrService.php).

## Get the repository and configure a development environment

The original README identified the repository URL below; its remote availability was not checked.

```powershell
git clone https://github.com/Dale-Guantia/EmployeeCare.git
Set-Location EmployeeCare
Copy-Item .env.example .env
```

Edit the new `.env` locally. Never copy production credentials into documentation. Example values below are placeholders for a new development database:

```dotenv
APP_NAME="Employee Care System"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=employeecare_dev
DB_USERNAME=your_development_database_user
DB_PASSWORD=
CACHE_DRIVER=file
SESSION_DRIVER=file
SESSION_SECURE_COOKIE=false
QUEUE_CONNECTION=sync
FILESYSTEM_DRIVER=local
MAIL_MAILER=log
```

Set the database password privately. `SESSION_SECURE_COOKIE=false` is for isolated HTTP development only; source defaults secure cookies to true. Set true and use HTTPS in production. APP_ENV=local is relevant to local login availability and demo seeding. `QUEUE_CONNECTION=sync` does **not** make browser policy ingestion synchronous: that job explicitly uses policy_ingestion.

Create the empty MySQL database through your approved database administration tool, using UTF-8/utf8mb4 consistent with config. This guide does not grant permission to create or alter production databases.

For SQL Server, use DB_CONNECTION=sqlsrv and appropriate host/port/database/user settings. Configuration enables encryption and currently trusts the server certificate; confirm the production certificate policy. A separate `connectsession` connection is hardcoded in config; its necessity and external identity contract require developer confirmation. Do not substitute it for the main database without analysis.

## Install and prepare

```powershell
composer install
composer check-platform-reqs
php artisan key:generate
php artisan migrate:status
php artisan migrate
php artisan storage:link
npm.cmd ci
npm.cmd run development
php artisan serve
```

Generate a key only for a new installation. Rotating an existing key can invalidate encrypted data and sessions. Use `composer install`, not an unreviewed composer update. Root Composer scripts run package discovery; review errors rather than bypassing requirements.

The supplied migration chain includes column changes. If an engine reports a missing DBAL requirement for change(), inspect the installed packages and Laravel 8 engine support before approving any dependency addition. **Not Verified:** a clean full migration run on MySQL/SQL Server for this documentation package.

## Seeding and first administrator

[DatabaseSeeder](../database/seeders/DatabaseSeeder.php) invokes role/permission, department, division, priority, status and issue seeders, plus UsersTableSeeder only in local/testing. The users seeder independently rejects other environments. Seeders may update reference records and synchronize role grants; they are not a harmless deployment step on an existing database.

For a disposable local installation after reviewing the seeders:

```powershell
php artisan db:seed
```

Demo account data and shared passwords exist in source and must not be used as a production onboarding mechanism. This documentation deliberately omits those credentials and personal records. Provision a production administrator using a separately approved process. No dedicated secure administrator-provisioning command was found. Preserve the seeded status meanings and organizational assumptions; see [known gaps](DOCUMENTATION-GAPS.md).

## OCR and policy processing

Original project setup references: [Tesseract Windows builds](https://github.com/UB-Mannheim/tesseract/wiki) and [Poppler Windows builds](https://github.com/oschwartz10612/poppler-windows/releases). These links are inherited setup references; no download/installation was performed.

```dotenv
OCR_TESSERACT_PATH="C:/Program Files/Tesseract-OCR/tesseract.exe"
OCR_PDFTOPPM_PATH="C:/poppler/Library/bin/pdftoppm.exe"
OCR_LANGUAGES=eng+fil
```

Install both selected language data sets; restart the web server and worker after changing PATH/environment. Tool paths depend on installation location.

```powershell
tesseract --version
tesseract --list-langs
pdftoppm -v
php artisan hr:test-ocr
php artisan queue:listen policy_ingestion --queue=policy-ingestion --sleep=3 --tries=1 --timeout=900 --memory=256
```

The last command is a continuous Windows development listener serving multiple uploads. Production needs supervised operation, not a manually started process per PDF. See [maintenance](09-DEPLOYMENT-AND-MAINTENANCE.md).

Set ANTHROPIC_API_KEY privately and confirm ANTHROPIC_MODEL with your provider account. Source config defaults are implementation values, not proof those external models are available. Do not copy keys into files committed to Git. Gemini/OpenRouter keys do not change the controller's selected provider.

## Startup checks and installation problems

1. Open `/employee-care/login` in local mode; verify account active status and assigned roles.
2. Verify `/survey` and `/arta-form` without panel login.
3. Create a disposable ticket, confirm routing/discussion and authorized attachments.
4. Upload a disposable policy and observe worker completion; HTTP success alone is insufficient.
5. Confirm frontend assets, secure-cookie behavior and URL/base path match the server actually serving requests.

For 419/login loops, inspect cookies, scheme, session and CSRF. For failed database column changes, inspect engine/driver/schema requirements without resetting the database. For pending policies, inspect queue and worker. See [troubleshooting](13-TROUBLESHOOTING.md). Never run migrate:fresh or db:wipe to repair an established installation.

## Environment-variable reference (names only)

Names are extracted from config/app code; no environment values or secrets were copied. Configuration defaults and deployment policy are separate. See the linked file before changing an option. Test-only ENGINE_TEST_* and ENGINE_DATA_TEST_* flags are discussed in the QA guide, not production settings.

| Source | Referenced environment keys |
| --- | --- |
| [config/app.php](../config/app.php) | `APP_DEBUG`, `APP_ENV`, `APP_KEY`, `APP_NAME`, `APP_URL`, `ASSET_URL` |
| [config/backpack/base.php](../config/backpack/base.php) | `APP_ENV`, `BACKPACK_REGISTRATION_OPEN`, `BACKPACK_TOKEN_USERNAME` |
| [config/broadcasting.php](../config/broadcasting.php) | `ABLY_KEY`, `BROADCAST_DRIVER`, `PUSHER_APP_CLUSTER`, `PUSHER_APP_ID`, `PUSHER_APP_KEY`, `PUSHER_APP_SECRET` |
| [config/cache.php](../config/cache.php) | `APP_NAME`, `AWS_ACCESS_KEY_ID`, `AWS_DEFAULT_REGION`, `AWS_SECRET_ACCESS_KEY`, `CACHE_DRIVER`, `CACHE_PREFIX`, `DYNAMODB_CACHE_TABLE`, `DYNAMODB_ENDPOINT`, `MEMCACHED_HOST`, `MEMCACHED_PASSWORD`, `MEMCACHED_PERSISTENT_ID`, `MEMCACHED_PORT`, `MEMCACHED_USERNAME` |
| [config/database.php](../config/database.php) | `APP_NAME`, `DATABASE_URL`, `DB_CONNECTION`, `DB_DATABASE`, `DB_FOREIGN_KEYS`, `DB_HOST`, `DB_PASSWORD`, `DB_PORT`, `DB_SOCKET`, `DB_USERNAME`, `MYSQL_ATTR_SSL_CA`, `REDIS_CACHE_DB`, `REDIS_CLIENT`, `REDIS_CLUSTER`, `REDIS_DB`, `REDIS_HOST`, `REDIS_PASSWORD`, `REDIS_PORT`, `REDIS_PREFIX`, `REDIS_URL` |
| [config/filesystems.php](../config/filesystems.php) | `APP_URL`, `AWS_ACCESS_KEY_ID`, `AWS_BUCKET`, `AWS_DEFAULT_REGION`, `AWS_ENDPOINT`, `AWS_SECRET_ACCESS_KEY`, `AWS_URL`, `AWS_USE_PATH_STYLE_ENDPOINT`, `FILESYSTEM_DRIVER` |
| [config/hashing.php](../config/hashing.php) | `BCRYPT_ROUNDS` |
| [config/logging.php](../config/logging.php) | `LOG_CHANNEL`, `LOG_DEPRECATIONS_CHANNEL`, `LOG_LEVEL`, `LOG_SLACK_WEBHOOK_URL`, `LOG_STDERR_FORMATTER`, `PAPERTRAIL_PORT`, `PAPERTRAIL_URL` |
| [config/mail.php](../config/mail.php) | `MAIL_ENCRYPTION`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, `MAIL_HOST`, `MAIL_LOG_CHANNEL`, `MAIL_MAILER`, `MAIL_PASSWORD`, `MAIL_PORT`, `MAIL_SENDMAIL_PATH`, `MAIL_USERNAME` |
| [config/ocr.php](../config/ocr.php) | `OCR_DPI`, `OCR_MAX_CONCURRENCY`, `OCR_MIN_CONFIDENCE`, `OCR_MIN_TEXT_CHARS_PER_PAGE`, `OCR_OEM`, `OCR_PAGE_TIMEOUT`, `OCR_PSM` |
| [config/queue.php](../config/queue.php) | `AWS_ACCESS_KEY_ID`, `AWS_DEFAULT_REGION`, `AWS_SECRET_ACCESS_KEY`, `DB_CONNECTION`, `QUEUE_CONNECTION`, `QUEUE_FAILED_DRIVER`, `REDIS_QUEUE`, `SQS_PREFIX`, `SQS_QUEUE`, `SQS_SUFFIX` |
| [config/sanctum.php](../config/sanctum.php) | `APP_URL`, `SANCTUM_STATEFUL_DOMAINS` |
| [config/services.php](../config/services.php) | `ANTHROPIC_API_KEY`, `ANTHROPIC_MODEL`, `AWS_ACCESS_KEY_ID`, `AWS_DEFAULT_REGION`, `AWS_SECRET_ACCESS_KEY`, `GEMINI_API_KEY`, `GEMINI_MODEL`, `MAILGUN_DOMAIN`, `MAILGUN_ENDPOINT`, `MAILGUN_SECRET`, `OCR_LANGUAGES`, `OCR_PDFTOPPM_PATH`, `OCR_TESSERACT_PATH`, `OPENROUTER_API_KEY`, `POSTMARK_TOKEN` |
| [config/session.php](../config/session.php) | `APP_NAME`, `SESSION_CONNECTION`, `SESSION_DOMAIN`, `SESSION_DRIVER`, `SESSION_LIFETIME`, `SESSION_SECURE_COOKIE`, `SESSION_STORE` |
