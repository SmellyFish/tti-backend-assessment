# Patient Reported Outcomes (PRO) API  
## Product Requirements Document (PRD)

---

## 1. Overview

The **Patient Reported Outcomes (PRO) API** enables patients to submit structured health-related feedback and allows clinicians to retrieve and analyze this data over time.

This API simulates a core capability of the Wave Health platform: collecting and aggregating patient-reported data to support remote care for chronic conditions.

---

## 2. Goals & Objectives

### Primary Goals
- Enable creation and management of patients and instruments
- Allow submission and retrieval of patient-reported outcomes
- Provide aggregated summaries over time
- Ensure easy, reproducible local development via Docker

### Success Criteria
- Fully functional REST API with required endpoints
- Strong validation and data integrity
- Clean, maintainable architecture
- One-command local environment setup (`docker compose up`)

---

## 3. Scope

### In Scope
- REST API implementation using:
  - PHP 8.4+
  - Laravel 11+
  - MySQL 8+
- Dockerized development environment
- Core data models and relationships
- Required endpoints
- Validation and aggregation logic

### Out of Scope
- Frontend/UI
- Production-grade infrastructure
- Advanced authentication (optional)

---

## 4. Users

### Primary Users
- Patients (data submission)
- Clinicians (data retrieval via API)

### Secondary Users
- Developers reviewing/running the project locally

---

## 5. Functional Requirements

### 5.1 Data Model

#### Patient
- id
- name
- date_of_birth
- mrn (unique)

#### Instrument
- id
- title
- description

#### Question
- id
- instrument_id
- prompt
- response_type (enum):
  - scale_1_5
  - yes_no
  - free_text
- order (integer)

#### Submission
- id
- patient_id
- instrument_id
- submitted_at (timestamp)

#### Answer
- id
- submission_id
- question_id
- value

---

### 5.2 API Endpoints

#### Create Patient
```

POST /api/patients

```

#### Create Instrument
```

POST /api/instruments

```

#### Submit Instrument
```

POST /api/patients/{patient_id}/submissions

```

#### List Submissions
```

GET /api/patients/{patient_id}/submissions

```
- Paginated
- Newest first

#### Get Submission
```

GET /api/patients/{patient_id}/submissions/{submission_id}

```

#### Summary Endpoint
```

GET /api/patients/{patient_id}/summary?instrument_id={instrument_id}

````

---

### 5.3 Dockerized Development Environment (Core Requirement)

The system must include a fully functional Docker setup to allow reviewers to run the application with minimal setup.

#### Docker Compose Setup
Provide a `docker-compose.yml` with:
- app (Laravel PHP container)
- web server (Nginx or Apache)
- db (MySQL 8)

---

### Services

#### App Container
- PHP 8.4 with extensions:
  - pdo_mysql
  - mbstring
  - bcmath
- Composer installed
- Runs Laravel app

#### Web Server
- Nginx (preferred)
- Routes traffic to Laravel app

#### Database
- MySQL 8
- Pre-configured database

---

### Developer Experience Requirements

Run the project with:

```bash
docker compose up -d
````

Setup steps:

```bash
docker compose exec app composer install
docker compose exec app php artisan migrate --seed
```

Access app:

```
http://localhost:8000
```

---

### Environment Configuration

Include `.env.example` with:

* DB connection (Docker service name)
* App key placeholder

Setup:

```bash
cp .env.example .env
php artisan key:generate
```

---

### Data Persistence

* MySQL must use a Docker volume

---

## 6. Summary Logic

For a given patient and instrument:

### scale_1_5

* Return average score

### yes_no

* Return percentage of "yes" responses

### free_text

* Return count of non-empty responses

### Additional Fields

* total_submissions
* earliest_submission
* latest_submission

---

## 7. Validation Rules

### General

* Patient must exist
* Instrument must exist

### Submission Rules

* All questions must be answered
* Answers must match type:

  * scale_1_5 → integer (1–5)
  * yes_no → boolean
  * free_text → string (can be empty)

### Patient

* MRN must be unique

### Error Format

```json
{
  "message": "Validation failed",
  "errors": {
    "field": ["Error message"]
  }
}
```

---

## 8. Non-Functional Requirements

### Performance

* Use eager loading
* Add indexes:

  * patients.mrn
  * submissions.patient_id
  * submissions.instrument_id

### Scalability

* Normalized schema

### Security

* Mass assignment protection
* Input validation and sanitization

### Developer Experience

* Docker-based setup required
* Setup time under 5 minutes
* Clear README instructions

---

## 9. API Design Standards

* RESTful conventions
* Proper HTTP status codes:

  * 201 Created
  * 200 OK
  * 422 Validation Error
  * 404 Not Found
* Consistent response structure

---

## 10. Technical Approach

### Laravel Features

* Eloquent ORM
* Form Requests
* API Resources
* Migrations & Seeders

### Relationships

* Patient → hasMany Submissions
* Instrument → hasMany Questions
* Submission → hasMany Answers
* Answer → belongsTo Question

---

## 11. Edge Cases

* Missing answers
* Invalid response types
* Empty free_text handling
* No submissions for summary
* Duplicate MRN

---

## 12. Bonus Opportunities

* Automated tests
* API documentation (Swagger/OpenAPI)
* Authentication (Laravel Sanctum)
* Rate limiting
* Caching summary results

---

## 13. Trade-offs / Constraints

* Time-boxed (3–4 hours)
* Prioritize clarity over completeness
* Keep Docker setup simple

---

## 14. Future Enhancements

* CI/CD pipeline
* Production Docker optimization
* Instrument versioning
* Advanced analytics

---

## 15. Deliverables

* Laravel application
* Docker setup:

  * docker-compose.yml
  * Dockerfiles (if needed)
* Database migrations + seeders
* README with:

  * Setup instructions
  * Design decisions
  * Trade-offs
  * Future improvements

---

## 16. Implementation progress (phased build)

Tracking the build plan used alongside this PRD (not every PRD item maps 1:1 to a phase).

| Phase | Scope | Status |
|-------|--------|--------|
| 1 | Docker + Laravel skeleton, README bring-up | Done |
| 2 | Migrations, `ResponseType` enum, models, seed data | Done |
| 3 | `routes/api.php`, Form Requests, API Resources, JSON error format, POST patients/instruments | Done |
| 4 | Submissions: create, list, show | Not started |
| 5 | Summary aggregation endpoint | Not started |
| 6 | Polish: eager loading audit, README design/trade-offs | Not started |
| 7 | Optional: extra tests, OpenAPI, Sanctum, rate limiting | Not started |