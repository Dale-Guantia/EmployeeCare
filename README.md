# Employee Care System

Laravel/Backpack helpdesk for employee concerns, ticket handling, customer satisfaction surveys and HR policy assistance.

The current application provides role-scoped tickets, skill/workload assignment, organizational reassignment, discussion/files, resolution/reopening, database notifications, dashboards/PDF reports, public satisfaction/ARTA forms and an OCR-backed policy assistant. It also includes office and master-data lifecycle guards. See the [project handover](docs/PROJECT-HANDOVER.md) and [documentation index](docs/README.md) for more detail; older guides may describe the pre-upgrade application.

## Current stack

- PHP **8.5** (current CLI: 8.5.12) and Laravel **13.39.0** in `composer.lock`.
- Backpack CRUD **7.1.22**, PermissionManager **7.3.1**, CoreUI 4 theme **1.2.7**, Livewire **3.8.11**, Spatie Permission **6.25.0**, and Sanctum **4.3.3** in `composer.lock`.
- Blade, CoreUI 4 / Bootstrap 5, jQuery and Laravel Mix/Webpack. Some pages use separate frontend assets.
- MySQL is the configuration default; SQL Server connections and database-specific code are also present. The active deployment database is not established by this README.
- Dompdf, PDFParser, Tesseract/Poppler OCR and an Anthropic-backed HR assistant.

These versions describe the current local source and lockfile, including uncommitted work. Older PHP 7.4 / Laravel 8 documents are historical. The separate Filament rebuild specifications are plans, not this application's implementation.

## Development quick start

Use a new isolated development database and review the [installation guide](docs/05-INSTALLATION-GUIDE.md) before executing commands. Do not run these against production.

```powershell
git clone https://github.com/Dale-Guantia/EmployeeCare.git
Set-Location EmployeeCare
Copy-Item .env.example .env
composer install
composer check-platform-reqs
```

Configure the new local `.env` privately: `APP_ENV=local`, the correct `APP_URL`, an isolated database connection, session/cookie settings and selected service options. For HTTP-only local development `SESSION_SECURE_COOKIE=false` is needed; production should use HTTPS and secure cookies. Keep web, CLI and queue workers on a compatible PHP runtime.

```powershell
php artisan key:generate
php artisan migrate
php artisan storage:link
npm.cmd ci
npm.cmd run development
php artisan serve
```

Generate a key only for a new installation. Review seeders before any db:seed: they synchronize reference/role data and create demo users only in local/testing. This README does not publish login credentials or employee records. Production account provisioning requires an approved process.

Open `/employee-care/login` locally; current source redirects non-local login-page requests to the external HRDO login. Public forms are `/survey` and `/arta-form`. The survey kiosk supports offline viewing but requires a connection to submit.

The repository has a GitHub `origin` remote, but remote connectivity and authentication should be checked from the environment where it will be used.

## Policy OCR and worker

Install Tesseract with selected language data and Poppler/pdftoppm. Original Windows setup references: [Tesseract](https://github.com/UB-Mannheim/tesseract/wiki), [Poppler](https://github.com/oschwartz10612/poppler-windows/releases).

```dotenv
OCR_TESSERACT_PATH="C:/Program Files/Tesseract-OCR/tesseract.exe"
OCR_PDFTOPPM_PATH="C:/poppler/Library/bin/pdftoppm.exe"
OCR_LANGUAGES=eng+fil
```

Verify tesseract --version, tesseract --list-langs, pdftoppm -v and php artisan hr:test-ocr under the actual service identity. Restart web/worker processes after PATH changes.

Browser policy uploads use a dedicated database queue regardless of the default QUEUE_CONNECTION. On Windows development, run one continuous listener:

```powershell
php artisan queue:listen policy_ingestion --queue=policy-ingestion --sleep=3 --tries=1 --timeout=900 --memory=256
```

Production needs supervised execution. Upload success means saved/queued, not extraction complete. OCR results needing review are excluded from the active document library until their status becomes active. See [queue operations](docs/09-DEPLOYMENT-AND-MAINTENANCE.md) and the [queue supplement](docs/POLICY_INGESTION_QUEUE.md). Configure provider keys privately; do not commit secrets.

## HR policy assistant

The assistant retrieves excerpts from active uploaded policy PDFs for each question. It uses recent conversation turns to interpret follow-ups, but earlier answers are not policy evidence. The prompt limits it to document-supported policy and process guidance; it does not perform HR actions or learn automatically from user questions. When retrieval finds no matching excerpt, the application returns a fixed reply directing the user to HRDO. See the [prompt rules](app/Services/PolicyChatPrompt.php), [retriever](app/Services/PolicyRetriever.php) and [evaluation guide](docs/hr-chat-evaluation/README.md). The evaluation questions and baseline are draft review material, not HR-approved answer-quality certification.

## Documentation and verification

- [System overview](docs/01-SYSTEM-OVERVIEW.md), [architecture](docs/02-SYSTEM-ARCHITECTURE.md), [database](docs/03-DATABASE-DOCUMENTATION.md).
- [Functional rules and role matrix](docs/04-FUNCTIONAL-SPECIFICATIONS.md), [route/API reference](docs/07-ROUTES-AND-API.md), [traceability](docs/TRACEABILITY-MATRIX.md).
- [Administrator manual](docs/10-ADMINISTRATOR-MANUAL.md), [role-specific user manual](docs/11-USER-MANUAL.md), [troubleshooting](docs/13-TROUBLESHOOTING.md).
- [Security](docs/08-SECURITY-DOCUMENTATION.md), [deployment](docs/09-DEPLOYMENT-AND-MAINTENANCE.md), [testing](docs/12-TESTING-AND-QA.md), [known gaps](docs/DOCUMENTATION-GAPS.md).
- [Upgrade notes](docs/PHP85_UPGRADE.md), [CoreUI migration](docs/COREUI4_MIGRATION.md), [browser fixes](docs/BROWSER_FIXES.md), and [project handover](docs/PROJECT-HANDOVER.md).

The ordinary PHP suite uses the guarded SQLite `:memory:` test setup and fake storage; it does not certify SQL Server or a deployed browser workflow. Run it only after reviewing the [testing guide](docs/12-TESTING-AND-QA.md) and `phpunit.xml`:

```powershell
php artisan test
node --test (Get-ChildItem -File tests/JavaScript/*Test.cjs).FullName
```

The `subprocess` group starts a real child process to verify OCR timeout enforcement. On Windows, Symfony Process uses `taskkill` to terminate that process. Run this check in a development/test execution context that permits terminating its own child processes; a restricted sandbox can return `ERROR: Access denied` and leave the child running until its natural exit. Keep the timeout assertion enabled.

On **2026-11-19**, a follow-up run of `php artisan test --compact` outside the restricted sandbox reported **587 PHP tests passed, 3 skipped, 0 failed** (4,993 assertions). The three skipped tests are opt-in PDF/OCR sample tests. Earlier cleanup runs reported 586 passed, 3 skipped and 1 failed: the sandbox denied Windows `taskkill`, causing the simulated OCR subprocess to exceed the 3.5-second limit in `tests/Feature/PolicyChatIngestionTest.php:92`. The original assertion passes outside that sandbox; no application or test code was changed.

The earlier cleanup verification also reported **128 JavaScript tests passed** and Composer validation passed; those checks were not rerun for this timeout investigation. These are recorded local results, not a claim that all application flows or integrations work. Native SQL Server, live AI/OCR/mail, production configuration and full browser acceptance require separate verification.

## License

The original README and composer.json declare MIT. No LICENSE file was found in this checkout; obtain owner confirmation before relying on a distributable license artifact. See [MIT license description](https://choosealicense.com/licenses/mit/).
