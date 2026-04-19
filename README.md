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

3. Open the app at [http://localhost:8000](http://localhost:8000). Laravel’s health check responds at `GET /up` (HTTP 200 when the application is booting correctly).

Services:

| Service | Role |
|--------|------|
| `app` | PHP 8.4-FPM, Composer, extensions: `pdo_mysql`, `mbstring`, `bcmath`, `zip` |
| `web` | Nginx → forwards PHP to `app:9000`, document root `public/` |
| `db` | MySQL 8.4, database `laravel`, user `laravel` / password `secret` (see `.env.example`) |

Default database settings in `.env.example` use `DB_HOST=db` (the Compose service name). MySQL data is stored in the `mysql_data` Docker volume.

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