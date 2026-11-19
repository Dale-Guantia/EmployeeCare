# PHP 8.5 / Laravel 13 upgrade

This checkout now requires PHP 8.5. It uses Laravel 13, Backpack CRUD 7 with the
CoreUI 4 theme, Livewire 3, Sanctum 4, Spatie Permission 6, DomPDF 3,
Symfony Process 8 and PHPUnit 12. Frontend builds retain Laravel Mix 6 and use Axios 1.
Install the exact versions in the Composer and npm lockfiles.

The initial runtime upgrade used CoreUI 2 as a compatibility step. The subsequent
theme migration and its verification are recorded in [COREUI4_MIGRATION.md](COREUI4_MIGRATION.md).

## Application changes

- Preserve the existing customized Backpack views through a custom view namespace
  and the CoreUI theme fallback. UI settings now live in `config/backpack/ui.php`.
- Use Laravel's CORS and request-forgery middleware, current proxy header constants,
  and the Spatie 6 middleware namespace.
- Adapt ticket discussion events, uploads, signed request tests and mounted-app URLs
  to Livewire 3. Each comment write rechecks ticket visibility and account status.
- Use Flysystem 3's local adapter APIs while retaining the existing attachment roots,
  integrity checks, transaction compensation and private download authorization.
- Preserve Carbon 2's whole-unit, absolute date-difference behavior explicitly in
  deadlines and report calculations.
- The old permission-table migration now reads configured pivot names directly.
  This enables new installations on Spatie 6 without rerunning it on existing databases.
- Add a forward Sanctum migration for a nullable indexed `expires_at` column.
  Existing personal-access-token records and application data are retained.
- Update PHPUnit data providers, Livewire protocol fixtures, native-engine harnesses,
  and isolated asset storage. Test assets cannot replace the application asset cache.

## Database migration

The only newly introduced migration is:

```text
php artisan migrate --path=database/migrations/2026_10_29_090000_add_expiry_to_personal_access_tokens.php
```

Review the target database before running it. This does not authorize executing other
pending migrations or rerunning historical migrations. Keep the existing `.env`,
application key, session settings, storage roots, role assignments and user records.

## Verification commands

```text
php vendor/phpunit/phpunit/phpunit --do-not-cache-result
node --test tests/JavaScript/*.cjs
node tests/Support/production-build-check.cjs
composer validate --no-check-publish
composer check-platform-reqs
```

Set `POLICY_SAMPLE_TESTS=1` to include the three supplied policy PDFs with real Poppler /
Tesseract extraction and queue consumption. The Windows timeout test needs permission
to terminate its own subprocess tree; restricted sandboxes can make it fail.

The SQL Server suite is separate: see `DEPLOYMENT_READINESS.md`. Explicit local Windows
authentication is supported with `ENGINE_TEST_INTEGRATED_AUTH=1`, loopback host, and
empty username/password. All database-name, confirmation, empty-schema, identity and
ownership-token checks still apply. Do not use this mode against a shared/production
server; prefer a database-restricted test account there. Fixtures are retained for
inspection, and the harness never resets or drops databases.

## Operational requirements

Keep `public/storage` linked to `storage/app/public`; Backpack's asset cache uses that
public disk. After an upgrade, warm the asset cache with `php artisan basset:cache`
where external asset downloads are permitted. Rebuild frontend assets through the
existing Mix workflow. Restart application processes and policy-ingestion workers
with PHP 8.5; do not let PHP 7.4 workers consume jobs from the upgraded application.

## Verification limits

Automated PHP, JavaScript, real OCR, SQL Server migration/report/concurrency and direct
HTTP checks were exercised during implementation. Direct HTTP checks included login,
stylesheets, admin pages, Livewire comments and uploads, protected downloads, public
forms, all three PDF exports, and valid/expired API tokens on synthetic SQL Server data.
PDF exports were rendered for visual inspection. Browser automation was stopped by
the computer-use runtime because it could not reliably identify Chrome's URL; full
interactive visual browser verification remains outstanding. Live external SSO, paid
AI calls, actual SMTP delivery, MySQL engine behavior and deployment are not certified.
Current security-advisory endpoints were unreachable, so rerun `composer audit --locked`
and `npm audit` when access is available; an unavailable audit is not a clean audit.

## Local execution result - 2026-10-29

Main-checkout verification: **354 PHP tests / 2,559 assertions**, including all three
real policy PDF samples; **48 JavaScript tests**; production Mix build; **242 PHP syntax
checks**; Composer manifest/platform checks. The isolated SQL Server engine suite passed
**80 assertions**, and full fresh migrations plus HTTP workflows passed on a separate
synthetic SQL Server database. The named Sanctum migration was applied separately to
local `employeecare`; token rows stayed at zero and its column/index were verified.

Original replaced source files and vendor dependencies are retained privately under
`storage/app/php85-upgrade-backup/`. Verification results, JUnit output, migration
verification and the inventory of retained test databases are under its `verification/`
subdirectory. The temporary upgrade checkout and loopback test server were removed.
