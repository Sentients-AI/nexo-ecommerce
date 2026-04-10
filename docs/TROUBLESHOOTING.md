# Troubleshooting Guide

A living document of every real error, quirk, and gotcha encountered in this project.
**Purpose:** Save time. When you hit an error, search this file first.
**Convention:** Errors are grouped by category. Each entry has: what it is, when it happens, why, and the exact fix.

> **Contributing:** When you fix a new issue that isn't here, add it. Future you (and future developers) will be grateful.

---

## Table of Contents

- [Authentication & CSRF](#authentication--csrf)
- [WebSockets / Reverb](#websockets--reverb)
- [Stripe / Payments / Webhooks](#stripe--payments--webhooks)
- [Multi-Tenancy](#multi-tenancy)
- [Database & Migrations](#database--migrations)
- [Queues & Jobs](#queues--jobs)
- [Frontend / Vite / Inertia](#frontend--vite--inertia)
- [Testing](#testing)
- [Scout / Typesense Search](#scout--typesense-search)
- [Deployment / CI / cPanel](#deployment--ci--cpanel)
- [Filament Admin Panel](#filament-admin-panel)
- [Sanctum / API Auth](#sanctum--api-auth)
- [Rector / Pint / Static Analysis](#rector--pint--static-analysis)

---

## Authentication & CSRF

### **Error:** HTTP 419 — Page Expired on every form submit

**Context:** Happens after fresh installs, after pulling changes, or when switching environments. Login, registration, and checkout forms all return 419.

**Cause:** Laravel's CSRF middleware can't read the token. In an Inertia/SPA setup this usually means `bootstrap.js` isn't sending the `X-CSRF-TOKEN` header, or the cookie isn't being forwarded.

**Solution:**
1. Make sure `bootstrap.js` reads the token from the meta tag:
   ```js
   window.axios.defaults.headers.common['X-CSRF-TOKEN'] =
       document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
   ```
2. Run `php artisan config:clear && php artisan cache:clear`.
3. Hard-refresh the browser (`Ctrl+Shift+R`) to clear stale cookies.

---

### **Error:** HTTP 419 on Stripe webhook endpoint (`/webhooks/stripe`)

**Context:** Stripe webhook deliveries returning 419 in logs.

**Cause:** Laravel's `VerifyCsrfToken` middleware fires before the webhook controller. Stripe sends raw POST data without a CSRF token.

**Solution:** Exempt the webhook route in `bootstrap/app.php`:
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: [
        'webhooks/stripe',
    ]);
})
```
**Commit reference:** `c57f17c`

---

### **Error:** Auth redirect loops — authenticated users land on `/login` instead of dashboard

**Context:** After login, users are bounced back to `/login` instead of `/en` (the default locale-prefixed home).

**Cause:** The default `redirectTo` in the authentication middleware points to `/home`, which doesn't exist in this localized routing setup.

**Solution:** Set explicit redirects in `bootstrap/app.php`:
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->redirectGuestsTo(fn () => route('login'));
    $middleware->redirectAuthenticatedUsersTo('/en');
})
```
**Commit reference:** `c57f17c`

---

## WebSockets / Reverb

### **Error:** WebSocket connection to `ws://localhost:8080` failed

**Context:** Happens after restarting the machine, pulling new Git changes, or starting a new dev session.

**Cause:** The Reverb server process isn't running.

**Solution:**
1. Start Reverb: `php artisan reverb:start`
2. Clear config cache: `php artisan config:clear`
3. Restart the frontend dev server: `npm run dev` (or `composer run dev` to start everything at once)

---

### **Error:** Echo initialises and immediately throws — `Pusher is not defined` or similar

**Context:** Happens on environments where `VITE_REVERB_APP_KEY` isn't set in `.env`.

**Cause:** `bootstrap.js` unconditionally initialises Echo/Pusher even when the key is absent.

**Solution:** Guard the Echo initialisation:
```js
if (import.meta.env.VITE_REVERB_APP_KEY) {
    window.Echo = new Echo({ ... });
}
```
**Commit reference:** `224b709`

---

### **Error:** Real-time stock/price updates stop working after deployment

**Context:** Works locally, breaks on staging/production.

**Cause:** Missing or wrong `REVERB_*` environment variables. Also check that the reverse-proxy (nginx/Apache) is forwarding WebSocket upgrade headers.

**Solution:**
1. Verify all four env vars are set: `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_HOST`.
2. For cPanel/shared hosting that doesn't support persistent processes, consider using Pusher as a fallback instead of self-hosted Reverb.
3. Run `php artisan reverb:start --daemon` (or configure a supervisor process).

---

## Stripe / Payments / Webhooks

### **Error:** `400 Bad Request: Invalid payload` on webhook endpoint

**Context:** Testing checkout locally with Stripe CLI.

**Cause:** The `STRIPE_WEBHOOK_SECRET` in `.env` is stale or doesn't match the current CLI listener session.

**Solution:**
1. Start the CLI listener: `stripe listen --forward-to localhost/webhooks/stripe`
2. Copy the new `whsec_...` secret printed by the CLI.
3. Update `STRIPE_WEBHOOK_SECRET` in `.env`.
4. Run `php artisan config:clear`.

---

### **Error:** Payment intent created but order never moves past `pending`

**Context:** Checkout completes on the frontend but the order stays in `pending` status indefinitely.

**Cause:** The `payment_intent.succeeded` webhook event never arrived (CLI not running, wrong webhook secret, or webhook route CSRF-blocked).

**Solution:**
1. Check Stripe Dashboard → Developers → Webhooks → recent events to confirm delivery.
2. Ensure the CSRF exemption for `/webhooks/stripe` is in place (see Authentication section above).
3. Run `stripe listen` locally and watch for delivery errors.
4. Re-trigger a failed event from the Stripe Dashboard.

---

### **Error:** `client_secret` missing from checkout response — redirect to `/checkout/pending` fails

**Context:** The `POST /api/v1/checkout` response doesn't include `payment_intent.client_secret`.

**Cause:** The `PaymentIntent` model wasn't storing `client_secret` in the database. Fixed in a past migration.

**Solution:**
1. Run `php artisan migrate` to ensure the `client_secret` column exists on `payment_intents`.
2. Check the `PaymentIntent` model has `client_secret` in its `$fillable` array.

**Commit reference:** `440d6e6` / `53b123d`

---

### **Error:** Idempotency key collision — duplicate orders created on double-submit

**Context:** User clicks "Continue to Payment" twice quickly.

**Cause:** The frontend wasn't generating or persisting an idempotency key per checkout session.

**Solution:** The checkout composable (`useCheckout.ts`) generates a key and stores it in `sessionStorage`. The key is sent as the `Idempotency-Key` header on the `POST /api/v1/checkout` request. The backend `IdempotencyKey` domain deduplicates it. Don't remove this mechanism.

---

## Multi-Tenancy

### **Error:** 404 on API requests — models return empty even though data exists in the DB

**Context:** Feature tests that don't set up tenant context. Also happens locally if you hit an API endpoint without a valid tenant subdomain or API token tied to a tenant.

**Cause:** The `BelongsToTenant` trait applies a global `TenantScope` that filters all queries by `Context::get('tenant_id')`. If the context isn't set, nothing is returned.

**Solution (tests):**
```php
// Use the WithTenant trait in your test class
uses(WithTenant::class);

beforeEach(function () {
    $this->setUpTenant(); // sets Context::add('tenant_id', ...)
});
```

**Solution (local dev):** Hit the API via a subdomain (`acme-store.localhost`) or use `actingAs()` with a user who has a `tenant_id`.

---

### **Error:** `tenant_id` is `null` on models created inside queued jobs

**Context:** A queued job creates a `Product` or `Order` and the `tenant_id` is null in the database.

**Cause:** Laravel queues serialize and deserialize the job. The `Context` facade state (which holds `tenant_id`) is not automatically carried across queue worker boundaries.

**Solution:** The `AppServiceProvider` propagates tenant context through queues using:
```php
Context::dehydrating(fn ($context) => $context->add('tenant_id', Context::get('tenant_id')));
Context::hydrated(fn ($context) => Context::add('tenant_id', $context->get('tenant_id')));
```
If you ever clear or re-register the `AppServiceProvider`, ensure these hooks are still present.

---

### **Error:** `BelongsToTenant` overwrites an intentionally `null` tenant_id (super-admin models)

**Context:** Creating a super-admin `User` (tenant_id = null) from a test or seeder and the tenant_id gets set to the current tenant.

**Cause:** The original `BelongsToTenant` trait used `if ($this->tenant_id === null)` which also caught explicitly set `null` values.

**Solution:** The trait now uses `array_key_exists` to distinguish "not set" from "explicitly null":
```php
if (!array_key_exists('tenant_id', $this->attributes)) {
    $this->tenant_id = Context::get('tenant_id');
}
```
**Commit reference:** `0136ce7`

---

### **Error:** Composite unique index violation after adding `tenant_id` to a table

**Context:** Running `php artisan migrate` fails with `SQLSTATE[23000]: Integrity constraint violation` on a unique index.

**Cause:** The old unique index (e.g., `products_sku_unique`) doesn't include `tenant_id`, so two tenants with the same SKU violate it.

**Solution:** In the migration, drop the old index and recreate it with `tenant_id` as the first column:
```php
$table->dropUnique(['sku']);
$table->unique(['tenant_id', 'sku']);
```

---

## Database & Migrations

### **Error:** `Column not found` after modifying a migration

**Context:** You modify a column (e.g., change nullable to non-nullable) and existing attributes disappear.

**Cause:** Laravel 12 requires all previously-defined column attributes to be repeated in `->change()` calls. Omitting them drops those attributes silently.

**Solution:** Always include the full column definition:
```php
// Wrong — drops nullable
$table->string('email')->change();

// Correct — preserves nullable
$table->string('email')->nullable()->change();
```

---

### **Error:** `SQLSTATE[HY000]: General error: 1 table "X" already exists` when running `migrate:fresh`

**Context:** Running tests with `RefreshDatabase` or running `migrate:fresh` locally.

**Cause:** A migration creates a table that another migration also tries to create (duplicate migration naming or a `down()` method that doesn't drop the table).

**Solution:**
1. Check for duplicate `Schema::create('table_name')` calls across migrations.
2. Ensure every `up()` has a matching `Schema::dropIfExists()` in `down()`.
3. Run `php artisan migrate:fresh` on a clean database.

---

### **Error:** `DB_ENGINE` environment variable not recognized — migrations fail on MariaDB/MyISAM hosting

**Context:** Deploying to shared hosting that uses MariaDB with the MyISAM engine.

**Cause:** The `config/database.php` `engine` key was hardcoded to `null` instead of reading from an env var.

**Solution:** Set `DB_ENGINE=InnoDB` (or `MyISAM`) in `.env`. The config reads:
```php
'engine' => env('DB_ENGINE', null),
```
**Commit reference:** `224b709`

---

## Queues & Jobs

### **Error:** Queued jobs fail silently — no output, no failed_jobs entry

**Context:** A job is dispatched but nothing happens. No exception is thrown.

**Cause:** Queue worker isn't running.

**Solution:**
1. Start the worker: `php artisan queue:work`
2. For development: `php artisan queue:listen --tries=1` (better stack traces)
3. Check `QUEUE_CONNECTION` in `.env` — should be `database` or `redis`, not `sync` in production.

---

### **Error:** Failed job keeps retrying and filling `failed_jobs` table

**Context:** A job that calls an external API (Stripe, exchange rates) keeps failing and being retried.

**Cause:** The job has `$tries > 1` and the failure isn't a permanent one, but the API is consistently down.

**Solution:**
1. Check `failed_jobs` table: `php artisan queue:failed`
2. Retry: `php artisan queue:retry all`
3. Flush stale failures: `php artisan queue:flush`
4. For permanent failures, implement `failed()` method on the job to handle cleanup.

---

## Frontend / Vite / Inertia

### **Error:** `Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest`

**Context:** Happens after pulling new changes or after the first deploy when assets haven't been built.

**Cause:** The compiled asset manifest (`public/build/manifest.json`) is missing or stale.

**Solution:**
- **Development:** Run `npm run dev` or `composer run dev`
- **Production:** Run `npm run build` before deploying
- **CI/CD:** The build step must run before the deploy step (see `.github/workflows/deploy.yml`)

**Commit reference:** `440d6e6`

---

### **Error:** Tailwind classes added in Vue components are missing from the compiled CSS

**Context:** You add a new Tailwind class in a `.vue` file and it doesn't appear in the browser.

**Cause:** Tailwind v4 scans sources at build time. If the dev server isn't running with HMR, changes aren't picked up immediately.

**Solution:**
1. If running `npm run dev`: the class should appear within seconds (HMR). Hard-refresh if it doesn't.
2. If building for production: run `npm run build`.
3. If the class still doesn't appear, check `app.css` — the `@source` directive must include `'../js/**/*.vue'` (or similar glob matching your component path).

---

### **Error:** Custom OKLCH color classes (e.g., `bg-navy-900`, `text-brand-500`) don't compile

**Context:** After adding new palette entries to `@theme` in `app.css`, the classes aren't generated.

**Cause:** Tailwind v4 generates utilities from `@theme` at build time. A syntax error or invalid OKLCH value silently skips the palette.

**Solution:**
1. Validate the OKLCH value: lightness must be `0–1`, chroma `0–0.4`, hue `0–360`.
2. Run `npm run build` and check the terminal for CSS parse errors.
3. Confirm you're using `@import "tailwindcss"` (v4 syntax), not `@tailwind base/components/utilities` (v3 deprecated syntax).

---

### **Error:** Inertia page component throws `Cannot read properties of undefined` on the first render

**Context:** A page component tries to access nested props before they arrive (e.g., `user.profile.avatar`).

**Cause:** Inertia v2 deferred props arrive after the initial render. The component renders once with `null`/`undefined` before the data loads.

**Solution:** Use optional chaining throughout: `user?.profile?.avatar`. For deferred props, add skeleton loaders using the `.skeleton` CSS utility class.

---

### **Error:** `$listeners` is undefined in Vue 3 component

**Context:** A component uses `$listeners` (Vue 2 API) to detect if a parent has bound an event handler.

**Cause:** `$listeners` was removed in Vue 3. It was merged into `$attrs`.

**Solution:** Use `$attrs` instead, or pass an explicit boolean prop:
```html
<!-- Instead of $listeners?.rowClick -->
:class="onRowClick ? 'cursor-pointer' : ''"
```

---

## Testing

### **Error:** Tests return 404 even though the route exists and the model was created

**Context:** Feature tests that hit API endpoints return 404 for every request.

**Cause:** The test doesn't call `setUpTenant()`, so the global `TenantScope` filters out all models. The factory creates the model in a different (auto-created) tenant context.

**Solution:** Add the `WithTenant` trait and call `setUpTenant()` in `beforeEach`:
```php
uses(RefreshDatabase::class, WithTenant::class);

beforeEach(function () {
    $this->setUpTenant();
    // Now Context::get('tenant_id') is set and factories respect it
});
```

---

### **Error:** `expect($count)->toBe(1)` fails with 0 even though the record was created

**Context:** A test creates a model and immediately queries for it with a raw SQL filter.

**Cause A:** SQLite integer division returns `0` for `2/3` (truncates, doesn't round). A `whereRaw` like `refunded_count / total_count >= 0.5` always returns 0 in SQLite.

**Solution:** Cast to float in SQLite:
```sql
CAST(refunded_count AS REAL) / CAST(total_count AS REAL) >= 0.5
```

**Cause B:** The model uses the `BelongsToTenant` global scope and the test doesn't have tenant context. (See 404 entry above.)

---

### **Error:** `$this->faker` returns `null` in factories

**Context:** Running seeders or factories in Laravel 12.

**Cause:** Laravel 12 no longer injects `$this->faker` into factories automatically. The property is `null`.

**Solution:** Use the `fake()` helper function instead:
```php
// Wrong
'name' => $this->faker->name(),

// Correct
'name' => fake()->name(),
```
**Commit reference:** `224b709`

---

### **Error:** `PromotionSeeder` (or any seeder) fails with unique constraint violation when run twice

**Context:** Running `php artisan db:seed` multiple times (local dev, CI re-runs).

**Cause:** The seeder uses `Promotion::create()` unconditionally and the second run tries to insert a duplicate.

**Solution:** Use `firstOrCreate()` or check existence before creating:
```php
Promotion::firstOrCreate(
    ['code' => 'SUMMER20'],
    [...attributes...]
);
```
**Commit reference:** `224b709`

---

### **Error:** Pest `->and()` chaining doesn't work — use `->expect()` instead returns error

**Context:** Writing assertions that check multiple values on different subjects.

**Cause:** In Pest, continued assertions on a **new subject** use `->and(newValue)`, not `->expect(newValue)`.

**Solution:**
```php
// Wrong
expect($order->status)->toBe('pending')
    ->expect($order->total)->toBe(5000); // ← invalid

// Correct
expect($order->status)->toBe('pending')
    ->and($order->total)->toBe(5000); // ← use ->and()
```

---

## Scout / Typesense Search

### **Error:** Search returns no results even though products exist in the database

**Context:** After a fresh install or after seeding data, Typesense search returns empty.

**Cause:** Models haven't been indexed yet. Scout doesn't automatically index existing records — only new/updated ones.

**Solution:**
1. Run the manual re-index: `php artisan scout:import "App\Domain\Product\Models\Product"`
2. Or use the admin panel UI button (Scout Search Admin Trigger feature — `/control-plane/scout-reindex`).

---

### **Error:** `Typesense\Exceptions\ObjectNotFound` — collection does not exist

**Context:** Running a search before the collection has been created.

**Cause:** The Typesense collection schema is created when the first record is indexed. If the database is empty, the collection doesn't exist.

**Solution:**
1. Seed the database: `php artisan db:seed`
2. Index the models: `php artisan scout:import "App\Domain\Product\Models\Product"`
3. Or create the collection manually with `php artisan scout:sync-index-settings`.

---

### **Error:** Search works locally but fails in production — `TYPESENSE_API_KEY` not set

**Context:** After deployment.

**Cause:** The `TYPESENSE_API_KEY`, `TYPESENSE_HOST`, and `TYPESENSE_PORT` environment variables aren't set on the production server.

**Solution:** Add to the production `.env`:
```env
SCOUT_DRIVER=typesense
TYPESENSE_API_KEY=your-key
TYPESENSE_HOST=localhost
TYPESENSE_PORT=8108
TYPESENSE_PROTOCOL=http
```

---

## Deployment / CI / cPanel

### **Error:** Deploy workflow triggers before CI tests finish — deploys broken code

**Context:** GitHub Actions deploy workflow starts running at the same time as the CI test workflow.

**Cause:** The original setup used `wait-on-check-action` which fired the gate check before CI jobs were even registered by GitHub.

**Solution:** Use `workflow_run` trigger in the deploy workflow so it only runs after CI completes:
```yaml
on:
  workflow_run:
    workflows: ["CI"]
    types: [completed]
    branches: [main]
```
**Commit reference:** `2f252d5`

---

### **Error:** `npm: command not found` on the cPanel deploy server

**Context:** The deploy script runs `npm run build` on the cPanel server and it fails.

**Cause:** cPanel shared hosting doesn't have Node.js/npm in the PATH for SSH sessions.

**Solution:** Build assets on the CI runner (GitHub Actions) and SCP the compiled `public/build/` directory to the server — don't build on the server.
```yaml
- name: Build assets
  run: npm ci && npm run build

- name: Deploy built assets
  run: scp -r public/build user@host:public_html/public/build
```
**Commit reference:** `440d6e6`

---

### **Error:** `composer: command not found` — deployment script can't find Composer on cPanel

**Context:** The deploy SSH script runs `composer install` and it fails.

**Cause:** Composer is installed at a non-standard path on cPanel (e.g., `~/bin/composer.phar` or `/usr/local/bin/composer`).

**Solution:** Detect the Composer path dynamically:
```bash
COMPOSER=$(which composer || which composer.phar || echo ~/bin/composer.phar)
$COMPOSER install --no-dev --optimize-autoloader
```
**Commit reference:** `be5778f`

---

### **Error:** `fakerphp/faker` not found when running seeders in production

**Context:** Running `php artisan db:seed` on production after deploying with `composer install --no-dev`.

**Cause:** `fakerphp/faker` was in `require-dev` in `composer.json`. Seeders (called via `DatabaseSeeder`) depend on it at runtime in production.

**Solution:** Move `fakerphp/faker` to `require` (not `require-dev`):
```json
"require": {
    "fakerphp/faker": "^1.23"
}
```
**Commit reference:** `b6dcf60`

---

## Filament Admin Panel

### **Error:** Filament panel shows a blank white page or throws `View not found`

**Context:** After running `php artisan optimize` or after a fresh install.

**Cause A:** Filament's cached views are stale.

**Solution:** `php artisan filament:optimize-clear` followed by `php artisan optimize`.

**Cause B:** Custom Blade components referenced in a panel provider don't exist yet.

**Solution:** Publish and compile Filament views: `php artisan vendor:publish --tag=filament-views`.

---

### **Error:** Filament resource actions throw `Authorization failed` despite the user being an admin

**Context:** A super-admin user can't perform a delete/edit action on a resource.

**Cause:** The resource has a `Policy` registered, and the `Gate` check for the action fails because the policy's `before()` hook isn't returning `true` for super-admins.

**Solution:** Add a `before()` method to the policy:
```php
public function before(User $user): ?bool
{
    if ($user->isSuperAdmin()) {
        return true;
    }
    return null; // fall through to individual methods
}
```

---

### **Error:** `canAccess()` method on a custom Filament page doesn't respect tenant admin vs super-admin roles

**Context:** Both tenant admins and super-admins should see the Fraud Dashboard, but one group is blocked.

**Cause:** The `canAccess()` check used a combined `||` expression that short-circuits incorrectly when either method throws.

**Solution:** Use explicit null-safe calls and separate checks:
```php
public static function canAccess(): bool
{
    $user = auth()->user();
    if ($user?->isAdmin() === true) {
        return true;
    }
    return $user?->isSuperAdmin() === true;
}
```
**Commit reference:** `819b24c`

---

## Sanctum / API Auth

### **Error:** Sanctum SPA authentication fails — `401 Unauthorized` on every API request from the frontend

**Context:** The Inertia frontend hits `/api/v1/...` endpoints and gets 401, even after logging in.

**Cause:** The frontend's domain isn't in Sanctum's `stateful` domains list.

**Solution:** Add the local dev domain to `SANCTUM_STATEFUL_DOMAINS` in `.env`:
```env
SANCTUM_STATEFUL_DOMAINS=localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,127.0.0.1:8001
```
Or update `config/sanctum.php` directly. Remember to run `php artisan config:clear` after.

**Commit reference:** `819b24c`

---

### **Error:** API tokens work in Postman but SPA cookie auth fails in the browser

**Context:** The Sanctum guard is set to `web`, but the request has an `Authorization: Bearer` token.

**Cause:** Sanctum tries cookie auth first, then token auth. If the CSRF cookie isn't present (e.g., the browser blocked it or `SameSite` policy is strict), cookie auth fails. Bearer token auth requires the `api` guard.

**Solution:**
1. Ensure `GET /sanctum/csrf-cookie` is called before any stateful API request.
2. Make sure the API route uses the `auth:sanctum` middleware (not just `auth`).
3. For local HTTPS issues with `SameSite=Lax` cookies, set `SESSION_SECURE_COOKIE=false` in `.env`.

---

## Rector / Pint / Static Analysis

### **Error:** Rector CI fails — `env()` called outside of a config file

**Context:** Any `env('FOO')` call directly in a non-config PHP file triggers a Rector rule failure.

**Cause:** The project enforces the Laravel best practice: `env()` should only be in `config/*.php` files. Application code must use `config('key')`.

**Solution:** Move the value to a config file and call `config()`:
```php
// Wrong — in a controller/action/model
$key = env('STRIPE_KEY');

// Correct — in config/services.php
'stripe' => ['key' => env('STRIPE_KEY')],

// Then in your code
$key = config('services.stripe.key');
```

---

### **Error:** `env('DB_ENGINE')` with a default of `null` fails Rector CI

**Context:** `env('DB_ENGINE', null)` causes a Rector rule violation about redundant default values.

**Cause:** Rector flags `env('X', null)` as a redundant default since `env()` already returns `null` if the variable is absent.

**Solution:** Remove the default:
```php
// Wrong
'engine' => env('DB_ENGINE', null),

// Correct
'engine' => env('DB_ENGINE'),
```
**Commit reference:** `eeb0716`

---

### **Error:** Pint reformats `if ($x === null)` to `if (! $x instanceof Foo)` — unexpected behavior change

**Context:** After running `vendor/bin/pint --dirty`, a null check gets rewritten into an `instanceof` check that doesn't behave the same way.

**Cause:** Pint applies certain opinionated PHP-CS-Fixer rules. The rule may change semantics if the variable can be non-null falsy values.

**Solution:** This is intentional — the `instanceof` form is more explicit and type-safe. Review the change carefully. If the logic differs, add a `// @phpcs:ignore` comment or configure the rule in `pint.json`.

---

*Last updated: April 2026*
