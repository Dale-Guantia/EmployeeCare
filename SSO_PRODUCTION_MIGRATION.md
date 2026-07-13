# SSO + Subfolder Deployment — Production Migration Guide

> Written after local SSO + subfolder-asset work was completed and verified against a Windows/Laragon + mock-gems test rig. Read this fully before touching the real GEMS app or the production server.

---

## 1. Summary

Built and locally verified an SSO handoff flow where GEMS encrypts a short-lived, signed token containing a user's identity (`gems_user_id`, name, email, role, dept/emp codes) and redirects the browser to EmployeeCare's `/sso/login?token=...`. EmployeeCare decrypts the token with a shared `SSO_KEY`, validates expiry and single-use (nonce + cache), finds-or-creates the matching local user (matching on `gems_user_id` first, falling back to `email` and backfilling `gems_user_id` to avoid duplicate-account inserts), assigns a role, and logs them straight into the Backpack admin panel — no login form. Separately, because EmployeeCare will be deployed under a URL **subfolder** (`https://hrdo.gemspasig.ph/employeecare`, mirroring the local junction-based `http://mock-gems.test/employeecare` setup), a second round of fixes addressed systemic path-resolution bugs: a hardcoded root-relative logo path, Livewire's asset/AJAX URLs (which don't respect `APP_URL` out of the box), and a broken/missing `public/storage` symlink that was causing uploaded file 404s. Both the SSO flow and every subfolder fix were verified end-to-end via real HTTP requests (fresh login, repeat login, expired token, replayed token, logo/asset loading, file download, and the Livewire-based ticket chat feature).

---

## 2. Files Changed in EmployeeCare

| File | Change |
|---|---|
| `app/Http/Controllers/SsoLoginController.php` | **New.** Handles incoming SSO tokens — decrypt, validate expiry/nonce, find-or-create user, assign role, log in, redirect to dashboard. Full contents below. |
| `database/migrations/2026_07_09_011828_add_gems_fields_to_users_table.php` | **New.** Adds `gems_user_id` (unique, nullable), `emp_code`, `dept_code` to `users`. Full contents below. |
| `app/Models/User.php` | Added `gems_user_id`, `emp_code`, `dept_code` to `$fillable`. Without this, mass-assignment silently dropped these columns on every insert, which was the real root cause of a duplicate-email crash on second login. |
| `routes/backpack/custom.php` | Wrapped `/sso/login` and `/login` in a `Route::group(['middleware' => ['web']], ...)` block. They were previously declared outside any middleware group, so `StartSession` never ran and the login never persisted across the redirect to the dashboard. **`/login`'s fallback redirect is still hardcoded to `mock-gems.test` — see Section 7, this must be fixed before deploying.** |
| `config/backpack/base.php` | `project_logo` was a hardcoded `<img src="/assets/...">` (root-absolute path). Changed to build the URL from `env('APP_URL')`. This is what broke the sidebar/login logo under the subfolder. |
| `config/livewire.php` | **New** (published from the vendor package, previously un-customized). Set `asset_url` and `app_url` to `env('APP_URL')`. This is the fix for Livewire's script tag and AJAX endpoint (see Section 5). |
| `public/storage` | Not a git-tracked file (gitignored), but the symlink was broken locally (a real empty directory, not a reparse point) and had to be recreated via `php artisan storage:link`. This must be done fresh on the production server — see Section 6. |

### `app/Http/Controllers/SsoLoginController.php` (deploy as-is)

```php
<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SsoLoginController extends Controller
{
    public function handle(Request $request)
    {
        Log::info('SSO handler hit', ['token_present' => $request->has('token')]);

        $token = $request->query('token');

        if (!$token) {
            abort(403, 'Missing SSO token.');
        }

        $encrypter = new Encrypter(base64_decode(str_replace('base64:', '', env('SSO_KEY'))), 'AES-256-CBC');

        try {
            $payload = json_decode($encrypter->decrypt($token), true);
        } catch (\Exception $e) {
            Log::warning('SSO token decrypt failed', ['error' => $e->getMessage()]);
            abort(403, 'Invalid or tampered SSO token.');
        }

        if (!isset($payload['exp']) || $payload['exp'] < now()->timestamp) {
            abort(403, 'SSO token expired. Please click the link from GEMS again.');
        }

        $nonceKey = 'sso_nonce_' . $payload['nonce'];
        if (Cache::has($nonceKey)) {
            abort(403, 'This SSO token has already been used.');
        }
        Cache::put($nonceKey, true, 120); // block replay for 2 minutes

        $user = User::where('gems_user_id', $payload['gems_user_id'])->first();

        if (!$user) {
            // No gems_user_id match — fall back to email, and link the GEMS id
            // to that existing account instead of inserting a duplicate.
            $user = User::where('email', $payload['email'])->first();

            if ($user) {
                $user->gems_user_id = $payload['gems_user_id'];
                $user->save();
            }
        }

        $isNewUser = false;

        if (!$user) {
            $isNewUser = true;
            $user = User::create([
                'gems_user_id' => $payload['gems_user_id'],
                'name'         => $payload['name'],
                'email'        => $payload['email'],
                'emp_code'     => $payload['emp_code'],
                'dept_code'    => $payload['dept_code'],
                'password'     => bcrypt(\Illuminate\Support\Str::random(32)),
            ]);
        }

        if ($isNewUser) {
            $roleMap = [
                'admin'      => 'admin',
                'hr_partner' => 'hr_staff',
            ];
            $role = $roleMap[$payload['role']] ?? 'employee';
            $user->assignRole($role); // Backpack Permission Manager
        }

        auth()->guard('web')->login($user); // adjust guard name to match your Backpack config

        return redirect()->to(backpack_url('dashboard'));
    }
}
```

### `database/migrations/2026_07_09_011828_add_gems_fields_to_users_table.php` (deploy as-is)

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddGemsFieldsToUsersTable extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('gems_user_id')->unique()->nullable();
            $table->string('emp_code')->nullable();
            $table->integer('dept_code')->nullable();
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['gems_user_id', 'emp_code', 'dept_code']);
        });
    }
}
```

### `config/livewire.php` (deploy as-is — only `asset_url` and `app_url` differ from the package default)

```php
<?php

return [
    'class_namespace' => 'App\\Http\\Livewire',
    'view_path' => resource_path('views/livewire'),
    'layout' => 'layouts.app',

    // Set to env('APP_URL') so Livewire's script tag and AJAX endpoint
    // resolve correctly under the /employeecare subfolder.
    'asset_url' => env('APP_URL'),
    'app_url' => env('APP_URL'),

    'middleware_group' => 'web',

    'temporary_file_upload' => [
        'disk' => null,
        'rules' => null,
        'directory' => null,
        'middleware' => null,
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
        ],
        'max_upload_time' => 5,
    ],

    'manifest_path' => null,
    'back_button_cache' => false,
    'render_on_redirect' => false,
];
```

### `config/backpack/base.php` — relevant snippet only (file is ~360 lines; only this line changed)

```php
// Menu logo. You can replace this with an <img> tag if you have a logo.
'project_logo' => '<img src="' . rtrim(env('APP_URL', ''), '/') . '/assets/emp-care-logo-1.png" alt="Employee Care Logo" style="height: 30px;">',
```

### `routes/backpack/custom.php` — relevant snippet only (bottom of file)

```php
// routes/web.php (EmployeeCare)
Route::group([
    'middleware' => ['web'],
], function () {
    Route::get('/sso/login', [\App\Http\Controllers\SsoLoginController::class, 'handle']);

    // If you have a custom login controller/route, replace its logic with:
    Route::get('/login', function () {
        return redirect('http://mock-gems.test/simulate-login'); // ⚠️ FIX BEFORE DEPLOY — see Section 7
    });
});
```

---

## 3. What to Build in Real GEMS

Mock-gems was throwaway scaffolding — nothing from it should be copied. Real GEMS needs an actual `SsoController` that pulls the **currently authenticated** GEMS user (not a hardcoded array) and issues a token the same way mock-gems did. Based on the real GEMS `users` schema (`id`, `first_name`, `middle_name`, `last_name`, `name_ext`, `email`, `role`, `dept_code`, `emp_code`, `Active`, `deleted`):

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Str;

class SsoController extends Controller
{
    /**
     * Issue a short-lived SSO token for the logged-in GEMS user and
     * redirect them into EmployeeCare, already authenticated.
     */
    public function redirectToEmployeeCare(Request $request)
    {
        $user = auth()->user(); // adjust to GEMS's actual auth guard if not the default

        if (!$user || $user->deleted || !$user->Active) {
            abort(403, 'Account inactive.');
        }

        $fullName = trim("{$user->first_name} {$user->middle_name} {$user->last_name} {$user->name_ext}");

        $payload = [
            'gems_user_id' => $user->id,
            'name'         => $fullName,
            'email'        => $user->email,
            'role'         => $user->role,
            'dept_code'    => $user->dept_code,
            'emp_code'     => $user->emp_code,
            'nonce'        => Str::random(32),
            'iat'          => now()->timestamp,
            'exp'          => now()->addSeconds(60)->timestamp,
        ];

        $encrypter = new Encrypter(base64_decode(str_replace('base64:', '', env('SSO_KEY'))), 'AES-256-CBC');
        $token = $encrypter->encrypt(json_encode($payload));

        return redirect(rtrim(env('EMPLOYEECARE_URL'), '/') . '/sso/login?token=' . urlencode($token));
    }
}
```

**Route** (add to GEMS `routes/web.php`, inside GEMS's normal authenticated `web` middleware group):

```php
Route::middleware(['web', 'auth'])
    ->get('/sso/employeecare', [\App\Http\Controllers\SsoController::class, 'redirectToEmployeeCare'])
    ->name('sso.employeecare');
```

**Menu link** (wherever GEMS currently links to EmployeeCare — replace any static/hardcoded URL with the named route):

```blade
<a href="{{ route('sso.employeecare') }}">Employee Care</a>
```

Notes:
- `role` mapping (`admin` → `admin`, `hr_partner` → `hr_staff`, anything else → `employee`) lives in `SsoLoginController` on the EmployeeCare side. If GEMS's `role` values differ from what's currently mapped, update the `$roleMap` array in `SsoLoginController`, not this controller.
- The 60-second `exp` window matches what was tested locally. Adjust if GEMS's redirect chain (network latency, any intermediate pages) needs more headroom — but keep it short; it's meant to be single-use and near-instant.

---

## 4. Environment Variables (Production)

### GEMS `.env`
| Key | Value | Notes |
|---|---|---|
| `SSO_KEY` | freshly generated | **Do not reuse the local testing key.** Generate with `php artisan key:generate --show` (run it, copy the `base64:...` output, do NOT run `key:generate` without `--show` — that would overwrite `APP_KEY` instead). Must be byte-identical to EmployeeCare's `SSO_KEY`. |
| `EMPLOYEECARE_URL` | `https://hrdo.gemspasig.ph/employeecare` | Used by the new `SsoController` to build the redirect target. |

### EmployeeCare `.env`
| Key | Value | Notes |
|---|---|---|
| `APP_URL` | `https://hrdo.gemspasig.ph/employeecare` | **Must include the subfolder.** Everything else (asset(), url(), route(), the Livewire config, the logo fix) is driven off this one value. |
| `SSO_KEY` | same value as GEMS's `SSO_KEY` | Freshly generated, shared between both apps. |
| `SESSION_PATH` | `/employeecare` | Scopes EmployeeCare's session/XSRF cookies to its own subfolder so they don't collide with GEMS's own cookies on the same domain. |
| `SESSION_DOMAIN` | leave unset/null | Both apps are on the same domain (`hrdo.gemspasig.ph`), just different paths — no cross-domain cookie sharing needed, so the default (current host) is correct. |
| `CACHE_DRIVER` | `file` (or `redis`/`database` if you want to scale) | **Must not be `array`** — array cache doesn't persist between requests, which would make the SSO nonce/replay check always pass (false negative on replay detection). Confirmed `file` locally; any persistent driver is fine. |
| `SESSION_DRIVER` | `file` (or `redis`/`database`) | Same reasoning — **must not be `array`**, or the login session never persists past the request that set it. |
| `ASSET_URL` | leave unset | Not needed. `asset()`/`url()` correctly derive the subfolder from the request at runtime; this was confirmed during local testing (Backpack's own CSS/JS links rendered with the correct `/employeecare` prefix with `ASSET_URL` unset). |

There is no separate `LIVEWIRE_ASSET_URL` env var — `config/livewire.php` (Section 2) reads `env('APP_URL')` directly, so setting `APP_URL` correctly is sufficient.

---

## 5. Subfolder Deployment Checklist

**Livewire config (`config/livewire.php`)** — Livewire builds its `<script src="...">` tag and its `window.livewire_app_url` (which the frontend JS uses to build the AJAX endpoint for every component update) via plain string concatenation in `LivewireManager::javaScriptAssets()` — it does **not** go through Laravel's URL generator or respect `APP_URL` automatically. Left at its default (`null`), both resolved to root-relative paths (`/livewire/livewire.js`), 404ing under the subfolder and silently breaking every Livewire component's AJAX updates — including the ticket chat. Fixed by explicitly setting `asset_url` and `app_url` to `env('APP_URL')`.

**Mix build** — This app uses Laravel Mix (`laravel-mix ^6.0.6`, not Vite — there is no `vite.config.js` in this project). The fix here was **purely `.env`-driven**; no rebuild was required, because the only `mix()` call in the codebase (`resources/views/vendor/backpack/base/inc/head.blade.php`) loops over `config('backpack.base.mix_styles')`, which is an empty array — it never actually executes. `public/css`, `public/js`, and `public/mix-manifest.json` don't exist and nothing currently needs them. **If you ever populate `mix_styles` in `config/backpack/base.php`**, you'll need to run `npm install && npm run production` on the server first, or that `mix()` call will throw (missing manifest file).

**Storage symlink** — On the production Linux server, run:
```bash
php artisan storage:link
```
This creates `public/storage` as a real symlink to `storage/app/public` (unlike the local Windows setup, which uses an NTFS junction — see Section 7). Verify it worked:
```bash
ls -la public/storage   # should show: storage -> /path/to/EmployeeCare/storage/app/public
```
Then verify it resolves correctly through the live subfolder URL by requesting any existing uploaded file, e.g.:
```bash
curl -I https://hrdo.gemspasig.ph/employeecare/storage/attachments/<some-existing-filename>
```
Expect `200`, not `404`. If `public/storage` already exists as a plain (non-symlink) directory from a previous deploy attempt, `storage:link` will silently skip it — check with `file public/storage` first; if it says "directory" instead of "symbolic link", remove it (confirm it's empty first) before re-running `storage:link`.

**Blade templates with hardcoded paths** — Only one was found, and it's a config value, not a Blade file: `config/backpack/base.php`'s `project_logo` string. Every actual `.blade.php` file already used `asset()`/`url()` correctly (spot-checked: `arta_form.blade.php`, `survey_form.blade.php`, `resources/views/livewire/ticket-chat.blade.php`, and Backpack's published `resources/views/vendor/backpack/base/inc/head.blade.php`). After production deploy, spot-check:
- [ ] Sidebar/login logo renders (not a broken image icon)
- [ ] `resources/views/livewire/ticket-chat.blade.php`'s attachment links (`asset('storage/' . $path)`) resolve and download correctly

**Chat feature fix** — The ticket chat (`app/Http/Livewire/TicketChat.php` + `resources/views/livewire/ticket-chat.blade.php`) has no custom JS/AJAX of its own — it's 100% Livewire (`wire:model`, `wire:click`, `wire:poll`). It wasn't broken by anything chat-specific; it was collateral damage from the Livewire `asset_url`/`app_url` bug above (livewire.js itself 404'd, so no Livewire component on any page could function). Fixing `config/livewire.php` fixed this along with every other Livewire component in the app.

---

## 6. Deployment Steps

- [ ] **Freeze/notify**: pick a low-traffic window; GEMS's current users will be unaffected until the new route/menu link goes live, but coordinate the EmployeeCare deploy so the subfolder isn't live-but-broken for longer than necessary.
- [ ] On the production server, create the real symlink under GEMS's public folder (equivalent to the local Windows junction):
  ```bash
  ln -s /path/to/employeecare/public /path/to/gems/public/employeecare
  ```
- [ ] Deploy EmployeeCare codebase to `/path/to/employeecare` (outside GEMS's own directory tree, same as local setup).
- [ ] Set all EmployeeCare production `.env` values from Section 4 (`APP_URL`, `SSO_KEY`, `SESSION_PATH`, `CACHE_DRIVER`, `SESSION_DRIVER`).
- [ ] Set GEMS production `.env` values from Section 4 (`SSO_KEY` — same value, `EMPLOYEECARE_URL`).
- [ ] Fix the hardcoded `mock-gems.test` fallback in `routes/backpack/custom.php`'s `/login` route (Section 7) before this step — do not deploy it as-is.
- [ ] Run the EmployeeCare migration on production:
  ```bash
  php artisan migrate --force
  ```
- [ ] Run `php artisan storage:link` on production; verify per Section 5.
- [ ] If `mix_styles` is populated (it isn't currently — see Section 5), run `npm install && npm run production` before the next step. Otherwise skip.
- [ ] Clear and re-cache config on **both** apps after all `.env` changes:
  ```bash
  php artisan config:clear
  php artisan cache:clear
  php artisan config:cache   # optional, but if used, must be re-run after ANY future .env change
  ```
  Stale cached config was a real gotcha during local testing — don't skip this.
- [ ] Add the `SsoController` + route to GEMS (Section 3).
- [ ] Update GEMS's menu link to `route('sso.employeecare')` (Section 3).
- [ ] Smoke-test on production using an account that is **not** a real employee's primary login (see Section 8 for the full test list) before announcing the feature.
- [ ] Only after the full Section 8 checklist passes on production, communicate the new "Employee Care" menu link to end users.

**Suggested order rationale**: deploy and configure EmployeeCare fully (including migration + storage link) *before* wiring up the GEMS-side route/menu link, so there's never a window where GEMS exposes a link to a not-yet-ready EmployeeCare instance. GEMS's existing functionality is untouched until the very last two steps.

---

## 7. Mock-GEMS-Specific / Local-Only — Do NOT Copy to Production

- **`routes/web.php` in mock-gems** (`/simulate-login`, `/simulate-login-expired`, `/simulate-login-inactive`) and its hardcoded test user array (`Dale Guantia`, `gems_user_id: 1`, etc.) — this entire app is throwaway and won't exist in production. Real GEMS needs the `SsoController` from Section 3 instead, which pulls the real authenticated user.
- **⚠️ Found and must be fixed before deploy**: `routes/backpack/custom.php`'s `/login` fallback route is currently hardcoded to `redirect('http://mock-gems.test/simulate-login')`. This is exactly the kind of hardcoding the subfolder work was otherwise careful to avoid — every other fix (logo, Livewire config) is `.env`-driven. Replace this with an `.env`-driven GEMS URL, e.g.:
  ```php
  Route::get('/login', function () {
      return redirect(env('GEMS_LOGIN_URL', '/'));
  });
  ```
  and set `GEMS_LOGIN_URL` (or point it at GEMS's actual login/SSO-initiation page) in EmployeeCare's production `.env`.
- **The Windows NTFS junction** (`mklink /J` / the junction that made `mock-gems\public\employeecare` point at `EmployeeCare\public`) — production uses a real symlink (`ln -s`, Section 6), not a junction. The distinction matters only for how you create it; once created, both behave the same way to Apache/Nginx and PHP.
- **`mock-gems.test`** as a domain — confirmed nowhere else in EmployeeCare's code besides the one `/login` fallback flagged above. `.env`'s `APP_URL` is the only place the actual domain is configured, and that's already correctly environment-specific.

---

## 8. Testing Checklist for Staging

Re-run every one of these against a staging copy of real GEMS + EmployeeCare (same domain/subfolder topology as production) before this touches live users:

- [ ] Fresh login via the GEMS menu link creates exactly one new EmployeeCare user, with `gems_user_id`, `emp_code`, `dept_code`, and role all populated correctly
- [ ] Repeat login (same GEMS user, new token) logs into the **same** EmployeeCare user row — no duplicate created
- [ ] An email-matched-but-`gems_user_id`-null existing user (simulating leftover pre-SSO data) gets backfilled with `gems_user_id` on next login, not duplicated
- [ ] Expired token (test by temporarily shortening `exp` or waiting past it) is rejected with 403, not silently logged in
- [ ] Replaying the exact same token a second time is rejected with 403 ("already been used")
- [ ] Logo displays correctly on the login/dashboard pages (not a broken image icon) under the real `/employeecare` subfolder path
- [ ] Backpack's own CSS/JS (sidebar theme, icons) load correctly — check browser Network tab for any 404s under `/employeecare/packages/...`
- [ ] An existing ticket attachment downloads/previews correctly (not 404) — confirms the production `storage:link` is correctly resolved
- [ ] Uploading a **new** file via the ticket chat attaches and is immediately viewable
- [ ] Ticket chat: sending a message appears without a full page reload, and the browser console shows no failed requests to `/livewire/...` (should all be under `/employeecare/livewire/...`)
- [ ] At least one other Livewire-driven interaction elsewhere in the app (if any exist beyond ticket chat) works correctly under the subfolder — this was a systemic fix, not chat-specific, so it's worth confirming broadly
- [ ] `storage/logs/laravel.log` shows no new errors/exceptions after running through the above
- [ ] Confirm production `.env` has `CACHE_DRIVER` and `SESSION_DRIVER` set to something other than `array` (a misconfigured value here would make the replay-rejection test above pass by accident, for the wrong reason — session/login would also silently fail)
