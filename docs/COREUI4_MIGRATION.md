# CoreUI v4 theme migration

The active theme is `backpack/theme-coreuiv4` 1.2.7 on Backpack 7 / Laravel 13.
Custom application views retain the `vendor.backpack.base.` namespace and use
`backpack.theme-coreuiv4::` as their fallback. Layout classes are configured in
`config/backpack/theme-coreuiv4.php`; the obsolete CoreUI v2 package/config were removed.

## Changes

- Adopt CoreUI v4's wrapper/sidebar structure and Bootstrap 5 navigation, forms,
  badges, spacing, modal controls and dropdowns, including ticket actions emitted
  by controllers. Existing permission checks and business actions remain in place.
- Use Bootstrap 5 DataTables adapters and Tom Select styling. Cache these assets
  with Basset. Public survey pages and PDF templates retain their independent styles.
- Replace the Bootstrap-dependent icon-picker popover with a native searchable
  input/datalist and a live preview. All 1,344 Font Awesome 5.3.1 style/name choices
  are retained from the official release metadata; stored icon values are unchanged.
  Source: https://github.com/FortAwesome/Font-Awesome/tree/5.3.1/advanced-options/metadata
- Preserve mounted-app Livewire update/upload URLs and obtain its JavaScript URL
  from the registered route, including `livewire.min.js` when debug mode is off.

## Verification on 2026-10-29

- Full regression run: **358 PHP tests / 2,617 assertions**, no failures/errors/skips;
  includes all three real policy PDF/OCR samples.
- After the HTTP check exposed the debug-off Livewire URL issue, the fix and new
  regression test passed a final **41-test / 311-assertion** focused run, covering
  theme pages, authorization, signed Livewire messages/uploads and mounted URLs.
- **51 JavaScript tests**, production Mix build, Composer manifest/platform checks,
  Blade compilation and **381 PHP syntax checks** passed.
- **76 direct HTTP requests** passed against the previously isolated SQL Server
  browser database: login, custom admin pages, ten list/search endpoints, nine
  create forms and local theme assets. All **26 distinct rendered inline scripts**
  passed JavaScript syntax checks.
- The existing application server's login and 18 local CSS/JavaScript assets also
  returned HTTP 200 with real content.

Interactive Chrome checks could not be completed: URL detection initially failed,
and a later retry was stopped by a physical Escape key press. Visual desktop/mobile
behavior and live SSO, AI and SMTP integrations are not certified by these tests.

## Local operations

No application database migrations or `.env` edits were needed. Native SQL Server
HTTP checks used a synthetic account only in the retained database
`ec_engine_test_browser691b3bec5731f512`. The temporary loopback test server was stopped.

The pre-theme source snapshot and verification evidence are retained privately in
`storage/app/coreuiv4-upgrade-backup/`. No commit, deployment or publication was made.
After dependency installation on another machine, run `php artisan basset:cache`
where asset downloads are allowed, and preserve the public storage link.
