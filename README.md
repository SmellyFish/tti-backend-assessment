# TTI Backend Engineer Assessment

## Patient Reported Outcomes (PRO) API

### Overview

Build a RESTful API that allows patients to submit and retrieve Patient Reported Outcomes (PROs); structured symptom and quality-of-life reports tied to their treatment. This exercise reflects the kind of work you would do on the Wave Health platform.

**Time expectation:** 3–4 hours. We respect your time. Focus on quality over quantity. A well-architected subset is better than a rushed complete solution.

**Stack:** PHP 8.4+, Laravel 11+, MySQL 8+

### Local development (Docker)

Prerequisites: [Docker](https://docs.docker.com/get-docker/) with Compose v2.

1. Start containers (PHP-FPM app, Nginx, MySQL 8 with a persistent volume):

   ```bash
   docker compose up -d
   ```

2. Install PHP dependencies and configure the app:

   ```bash
   docker compose exec app composer install
   cp .env.example .env
   docker compose exec app php artisan key:generate
   docker compose exec app php artisan migrate --seed
   ```

   Use the copied `.env` as-is for Docker so `DB_HOST=db` points at the MySQL service. If you already have a `.env` from a non-Docker setup (for example SQLite), replace the `DB_*` block with the values from `.env.example` before running migrations.

3. Open the app at [http://localhost:8000](http://localhost:8000). The browser shows the same content as [`resources/docs/api-schema.md`](resources/docs/api-schema.md) (see **API documentation** below). Laravel’s health check responds at `GET /up` (HTTP 200 when the application is booting correctly).

### API documentation (canonical)

**Single source of truth** for the PRO API: data model, full route table (implemented and planned), request/response JSON examples, curl samples, and validation error shape:

- **[`resources/docs/api-schema.md`](resources/docs/api-schema.md)**

Code lives under [`routes/api.php`](routes/api.php) (`/api` prefix), with Form Requests and API Resources in `app/Http/Requests` and `app/Http/Resources`. Keep the markdown file updated when the API changes; the README only summarizes where to look.

### OpenAPI specification

A machine-readable OpenAPI 3.1 contract is available at:

- **[`resources/docs/openapi.yaml`](resources/docs/openapi.yaml)**

Validate the spec from the repository root:

```bash
npx @redocly/cli lint resources/docs/openapi.yaml
```

Optional: open the file in [Swagger Editor](https://editor.swagger.io/) or Redoc-compatible viewers to inspect generated docs.

### API rate limiting

All routes under `/api/*` use Laravel's API limiter with a policy of **60 requests per minute per IP**.

- Throttled requests return **HTTP 429** using Laravel's default throttle response format.
- OpenAPI responses for all implemented operations include a `429` contract in [`resources/docs/openapi.yaml`](resources/docs/openapi.yaml).
- For local development, you can bypass throttling by setting `API_RATE_LIMIT_ENABLED=false` in `.env` (applies only when `APP_ENV=local`).

### Sanctum scaffolding

Laravel Sanctum is scaffolded for token-based API auth, while existing PRO endpoints remain public by design.

- Token endpoint: `POST /api/auth/token`
- Request payload: `email`, `password`, optional `device_name`
- Success response includes `token`, `token_type` (`Bearer`), and basic `user` info
- Invalid credentials return **401**
- A protected smoke-test route exists at `GET /api/auth-test` in `local`/`testing` only to validate token wiring; core PRO routes remain public.

Use the returned token in authenticated requests when needed:

```bash
curl -H "Authorization: Bearer <token>" http://localhost:8000/api/patients/1/summary?instrument_id=1
```

### Design decisions

- **Typed answer storage via JSON**: Answer values are stored in a single JSON column so one schema supports `scale_1_5` (number), `yes_no` (boolean), and `free_text` (string) without polymorphic tables.
- **Strict write contracts with Form Requests**: Input validation is centralized in `StorePatientRequest`, `StoreInstrumentRequest`, `StoreSubmissionRequest`, and `SummaryRequest`, keeping controllers focused on orchestration.
- **Stable API responses with Resources**: API Resources are used for all implemented endpoints to avoid leaking raw model internals and to keep response shapes predictable.
- **Summary aggregation shape**: `GET /api/patients/{id}/summary` returns top-level metadata (`total_submissions`, earliest/latest dates) plus per-question metrics keyed by response type (`average_score`, `yes_percentage`, `non_empty_count`).
- **Localized API strings**: User-facing API messages are resolved through `lang/{locale}/api.php` so additional locales can be added without source-code string rewrites.

### Trade-offs

- **Time-boxed implementation**: The assessment was implemented in phases; core correctness, validation, and test coverage were prioritized over broader platform concerns.
- **In-memory aggregation for summary**: The summary endpoint currently loads relevant submissions and answers then computes aggregates in PHP for clarity and maintainability; SQL-side aggregation or caching can be added later if data volume grows.
- **Single canonical docs file**: Endpoint payload details live in `resources/docs/api-schema.md` and are rendered at `/`; this README intentionally links to that source instead of duplicating examples.
- **Minimal auth + throttling scaffolding**: Sanctum token issuance and global API throttling are intentionally lightweight foundations, not a fully locked-down production policy.
- **Throttle response shape**: `429` responses currently use Laravel's default throttle payload rather than a custom API envelope.

### Future improvements

- Add summary caching/invalidation strategy for high-frequency dashboard reads.
- Move from scaffolding to production-ready authz/authn (protect selected PRO routes, scopes/abilities, token lifecycle controls, and role-based policies).
- Introduce endpoint-level throttling policies by route category and identity (IP + authenticated user dimensions).
- Add query/performance instrumentation (including automated N+1 guards in tests).
- Expand localization with additional language files and request-driven locale negotiation.
- Add OpenAPI spec generation and contract-level schema validation in CI.
- Add CI pipeline with automated test/lint checks and container build verification.

### Phase 7 bonus status

Implemented bonus items:
- OpenAPI 3.1 contract at `resources/docs/openapi.yaml`
- API rate limiting with local bypass toggle (`API_RATE_LIMIT_ENABLED=false` in `APP_ENV=local`)
- Sanctum token issuance scaffolding (`POST /api/auth/token`) with a local/testing protected smoke route (`GET /api/auth-test`)
- Bonus edge-case coverage in API feature tests (summary precision/messy data, rate-limit bypass, localization envelopes, public route regression, Unicode/encoding contracts)

Recommended verification run:

```bash
php artisan test tests/Feature/Api
php artisan test
```

### PRD mapping (quick reviewer guide)

This section maps key PRD requirements in [`plan.md`](plan.md) to concrete implementation artifacts.

| PRD area | Where it is implemented |
|--------|------|
| Core REST endpoints | `routes/api.php`, `app/Http/Controllers/Api/*`, `tests/Feature/Api/*` |
| Request validation + error envelope | `app/Http/Requests/*`, `bootstrap/app.php`, `lang/en/api.php` |
| Summary aggregation logic | `app/Services/PatientInstrumentSummaryBuilder.php`, `app/Http/Controllers/Api/SubmissionController.php`, `tests/Feature/Api/SummaryApiTest.php` |
| Data model, relationships, indexes | `database/migrations/*`, `app/Models/*`, `app/Enums/ResponseType.php` |
| API response shaping | `app/Http/Resources/*`, `app/Providers/AppServiceProvider.php` (`JsonResource::withoutWrapping()`) |
| Dockerized local setup | `docker-compose.yml`, `Dockerfile`, `.env.example`, setup steps in this README |
| API docs (human + machine readable) | `resources/docs/api-schema.md`, `resources/docs/openapi.yaml` |
| Bonus scaffolding (rate limiting + Sanctum) | `app/Providers/AppServiceProvider.php`, `app/Http/Controllers/Api/AuthTokenController.php`, `app/Http/Requests/IssueTokenRequest.php`, `tests/Feature/Api/RateLimitApiTest.php`, `tests/Feature/Api/AuthTokenTest.php` |

### Localization

User-facing API strings such as `validation_failed` and `not_found` live under **`lang/{locale}/`** (see [`lang/en/api.php`](lang/en/api.php)) and are resolved with Laravel’s `__()` helper in [`bootstrap/app.php`](bootstrap/app.php). Default locale is **`en`**; override with **`APP_LOCALE`** and **`APP_FALLBACK_LOCALE`** in `.env` (see [`config/app.php`](config/app.php)).

To add another language later, add a parallel file such as `lang/es/api.php` with the **same keys** and Spanish values, then set the application locale per request (for example middleware that reads `Accept-Language`) using `App::setLocale('es')`.

Published language stubs for validation and auth messages (optional) live under `lang/` after running `php artisan lang:publish`—see [Laravel localization](https://laravel.com/docs/localization).

Services:

| Service | Role |
|--------|------|
| `app` | PHP 8.4-FPM, Composer, extensions: `pdo_mysql`, `mbstring`, `bcmath`, `zip` |
| `web` | Nginx → forwards PHP to `app:9000`, document root `public/` |
| `db` | MySQL 8.4, database `laravel`, user `laravel` / password `secret` (see `.env.example`) |

Default database settings in `.env.example` use `DB_HOST=db` (the Compose service name). MySQL data is stored in the `mysql_data` Docker volume.

### Database (PRO domain)

Migrations define **patients**, **instruments**, **questions**, **submissions**, and **answers**, with foreign keys and indexes on `patients.mrn` (unique), `submissions.patient_id`, and `submissions.instrument_id`. Question **response types** are stored as strings and mapped in PHP to `App\Enums\ResponseType` (`scale_1_5`, `yes_no`, `free_text`). Answer **values** are stored as JSON so each row can hold a number, boolean, or string as required by the question type.

The questions table uses a **`sort_order`** column (integer) for ordering within an instrument—this avoids the SQL reserved word `order` and will be exposed as `order` in API payloads in a later phase.

Eloquent relationships: `Patient` → submissions; `Instrument` → questions / submissions; `Submission` → answers; `Answer` → submission / question (see `app/Models`).

### Seeding sample data

The default seeder loads **PRO** demo data only (two patients, one instrument with three questions, and three submissions with answers). It does not create Laravel `users`.

From a configured environment:

```bash
php artisan migrate:fresh --seed
```

With Docker:

```bash
docker compose exec app php artisan migrate:fresh --seed
```

Re-run seeding without wiping migrations:

```bash
php artisan db:seed
# or
php artisan db:seed --class=Database\\Seeders\\ProSampleDataSeeder
```

### Automated tests

PHPUnit is configured in [`phpunit.xml`](phpunit.xml). Tests use an **in-memory SQLite** database (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`) so the suite does not require MySQL. Feature tests that hit the database should use `RefreshDatabase`.

Run all tests:

```bash
php artisan test
```

Or:

```bash
./vendor/bin/phpunit
```

With Docker:

```bash
docker compose exec app php artisan test
```

Filter by suite or file, for example:

```bash
php artisan test --testsuite=Feature
docker compose exec app php artisan test tests/Feature/Api/PatientStoreTest.php
```

### Exploring models (optional)

To sanity-check relations in a REPL:

```bash
php artisan tinker
# or: docker compose exec app php artisan tinker
```

Example: `App\Models\Patient::with('submissions.answers')->first()`.

### Assessment brief reference

The original assessment brief and requirement wording is captured in [`plan.md`](plan.md).

This README is intentionally optimized as a project operation guide (setup, architecture, design decisions, and verification), while request/response API details remain canonical in:
- [`resources/docs/api-schema.md`](resources/docs/api-schema.md)
- [`resources/docs/openapi.yaml`](resources/docs/openapi.yaml)