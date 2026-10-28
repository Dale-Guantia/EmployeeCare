# Employee Care System

Laravel/Backpack helpdesk for employee concerns, ticket handling, customer satisfaction surveys and HR policy assistance.

The current application provides role-scoped tickets, skill/workload assignment, organizational reassignment, discussion/files, resolution/reopening, database notifications, dashboards/PDF reports, public satisfaction/ARTA forms and an OCR-backed policy assistant. See the [documentation index](docs/README.md) for the complete source-based handover package.

## Verified stack

- Project standard: PHP 7.4 and Laravel 8; Composer requires Laravel ^8.75 and the lockfile records 8.x-dev.
- Backpack CRUD 5.6.1, PermissionManager 6.0.17, Spatie Permission and Livewire 2.
- Blade, Bootstrap, jQuery, Laravel Mix/Webpack; selected report/chat libraries also load from CDNs.
- MySQL configuration default, with SQL Server connections, migrations and SQL dialect handling. Actual deployment must be verified separately.
- Dompdf, PDFParser, Tesseract/Poppler and selected Anthropic integration.

The documentation snapshot is dated **2026-10-27**, based on the working tree at Git HEAD 4927f4e plus existing uncommitted changes. It does not certify production deployment or newer runtime compatibility. A Filament replacement is not implemented.

## Development quick start

Use a new isolated development database and review the [installation guide](docs/05-INSTALLATION-GUIDE.md) before executing commands. Do not run these against production.

```powershell
git clone https://github.com/Dale-Guantia/EmployeeCare.git
Set-Location EmployeeCare
Copy-Item .env.example .env
composer install
composer check-platform-reqs
```

Configure the new local .env privately: APP_ENV=local, correct APP_URL, isolated DB connection, session/cookie settings and selected service options. For HTTP-only local development SESSION_SECURE_COOKIE=false is needed; production should use HTTPS/secure cookies.

```powershell
php artisan key:generate
php artisan migrate
php artisan storage:link
npm.cmd ci
npm.cmd run development
php artisan serve
```

Generate a key only for a new installation. Review seeders before any db:seed: they synchronize reference/role data and create demo users only in local/testing. This README does not publish login credentials or employee records. Production account provisioning requires an approved process.

Open `/employee-care/login` locally; non-local login-page requests redirect to an external HRDO login in current source. Public forms are `/survey` and `/arta-form`. The kiosk supports offline viewing and requires a connection to submit.

The repository URL above is preserved from the original README; remote availability was not checked in this documentation task.

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

Production needs supervised execution. Upload success means saved/queued, not extraction complete. See [queue operations](docs/09-DEPLOYMENT-AND-MAINTENANCE.md) and the existing [queue supplement](docs/POLICY_INGESTION_QUEUE.md). Configure provider keys privately; do not commit secrets.

## Documentation and verification

- [System overview](docs/01-SYSTEM-OVERVIEW.md), [architecture](docs/02-SYSTEM-ARCHITECTURE.md), [database](docs/03-DATABASE-DOCUMENTATION.md).
- [Functional rules and role matrix](docs/04-FUNCTIONAL-SPECIFICATIONS.md), [route/API reference](docs/07-ROUTES-AND-API.md), [traceability](docs/TRACEABILITY-MATRIX.md).
- [Administrator manual](docs/10-ADMINISTRATOR-MANUAL.md), [role-specific user manual](docs/11-USER-MANUAL.md), [troubleshooting](docs/13-TROUBLESHOOTING.md).
- [Security](docs/08-SECURITY-DOCUMENTATION.md), [deployment](docs/09-DEPLOYMENT-AND-MAINTENANCE.md), [testing](docs/12-TESTING-AND-QA.md), [known gaps](docs/DOCUMENTATION-GAPS.md).
- [Documentation coverage and current verification](docs/DOCUMENTATION-COVERAGE-REPORT.md).

Current documentation-task verification: 48 JavaScript tests passed; PHP suites, database-engine probes, production build and deployed browser workflows were not run. Tests and operational commands require the isolation procedures in the testing guide. Existing audit documents contain historical results, not fresh certification.

## License

The original README and composer.json declare MIT. No LICENSE file was found in this checkout; obtain owner confirmation before relying on a distributable license artifact. See [MIT license description](https://choosealicense.com/licenses/mit/).
