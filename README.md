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
- **No authentication layer in scope**: Auth/rate limiting were left out of the core implementation to match the exercise scope and keep focus on domain behavior.

### Future improvements

- Add summary caching/invalidation strategy for high-frequency dashboard reads.
- Add API auth (for example Sanctum) and route-level rate limiting.
- Add query/performance instrumentation (including automated N+1 guards in tests).
- Expand localization with additional language files and request-driven locale negotiation.
- Add OpenAPI spec generation and contract-level schema validation in CI.
- Add CI pipeline with automated test/lint checks and container build verification.

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
docker compose exec app php artisan test tests/Feature/ExampleTest.php
```

### Exploring models (optional)

To sanity-check relations in a REPL:

```bash
php artisan tinker
# or: docker compose exec app php artisan tinker
```

Example: `App\Models\Patient::with('submissions.answers')->first()`.

### Background

Wave Health helps patients with chronic conditions track their treatment experiences. Patients periodically complete questionnaires (called "instruments") that capture symptoms, side effects, and quality of life. Clinicians use this data to monitor patients remotely.

### Requirements

#### Data Model

Design and implement a schema to support the following:

- **Patients** — A patient has a name, date of birth, and a medical record number (MRN)
- **Instruments** — A questionnaire template with a title, description, and a set of ordered questions. Each question has a prompt and a response type (one of: `scale_1_5`, `yes_no`, `free_text`)
- **Submissions** — A completed instance of an instrument by a patient at a specific date/time, containing the patient's answers to each question

#### API Endpoints

Implement the following endpoints:

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/api/patients` | Create a new patient |
| `POST` | `/api/instruments` | Create a new instrument with questions |
| `POST` | `/api/patients/{id}/submissions` | Submit a completed instrument for a patient |
| `GET` | `/api/patients/{id}/submissions` | List all submissions for a patient (paginated, newest first) |
| `GET` | `/api/patients/{id}/submissions/{id}` | Get a single submission with all answers |
| `GET` | `/api/patients/{id}/summary` | Aggregate summary (see below) |

#### Summary Endpoint

`GET /api/patients/{id}/summary?instrument_id={id}`

Returns an aggregated view of a patient's responses to a specific instrument over time:

- For `scale_1_5` questions: return the **average score** across all submissions
- For `yes_no` questions: return the **percentage of "yes" responses**
- For `free_text` questions: return the **count of submissions** with a non-empty response
- Include the **total number of submissions** and the **date range** (earliest to latest)

#### Validation Rules

- Submissions must reference a valid patient and instrument
- All questions in the instrument must be answered
- Answers must match the question's response type:
  - `scale_1_5`: integer between 1 and 5
  - `yes_no`: boolean
  - `free_text`: string (may be empty)
- MRN must be unique across patients
- Return appropriate error responses with clear messages

### What We're Evaluating

| Area | What We're Looking For |
|------|----------------------|
| **Database Design** | Normalized schema, appropriate indexes, well-thought-out relationships and migrations |
| **API Design** | RESTful conventions, consistent response structures, proper HTTP status codes |
| **Laravel Proficiency** | Effective use of Eloquent, Form Requests, Resources, and other Laravel patterns |
| **Validation & Error Handling** | Robust input validation, graceful error responses, edge case handling |
| **Code Quality** | Clean, readable code with clear naming, separation of concerns, and SOLID principles |
| **Security Awareness** | Consideration for data sensitivity; mass assignment protection, input sanitization, etc. |

### Bonus (Not Required)

- Automated tests (Feature or Unit) for key endpoints
- API documentation (e.g., OpenAPI/Swagger or a simple markdown doc)
- Rate limiting or authentication scaffolding
- Any performance considerations (query optimization, eager loading, caching)

### Submission Instructions

1. **Fork** the repository
2. Complete the exercise on a feature branch
3. Open a **Pull Request** back to the original repository with:
   - A clear PR description summarizing your approach
   - A `README.md` that includes:
     - Setup instructions (we should be able to run it locally)
     - Any design decisions or trade-offs you made
     - What you would improve or add with more time
4. Include database migrations and a seeder with sample data

### Notes

- This is a simplified version of a real domain we work in. Don't overthink it — we want to see how you approach the problem, not a production-ready system.
- If you have questions or need clarification, email armando@tti.care. Asking good questions is a positive signal.
- We will review your submission before the technical interview and use it as a starting point for discussion. Be prepared to walk through your design decisions and talk about how you'd extend it.