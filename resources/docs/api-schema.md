# PRO API — schema and endpoints

This file is the **canonical API reference** for this project (also rendered at the web root `/`). The repository `README.md` points here instead of duplicating payloads and examples.

For a machine-readable contract, see [`resources/docs/openapi.yaml`](openapi.yaml).
The OpenAPI file also documents the scaffolded auth token endpoint: `POST /api/auth/token`.

All routes are under the `/api` prefix. Request and response bodies are JSON unless noted.

## Data model

### Patient

| Field | Type | Notes |
|-------|------|--------|
| `id` | integer | Primary key |
| `name` | string | |
| `date_of_birth` | date | |
| `mrn` | string | Unique medical record number |

### Instrument

| Field | Type | Notes |
|-------|------|--------|
| `id` | integer | Primary key |
| `title` | string | |
| `description` | string | |

### Question

| Field | Type | Notes |
|-------|------|--------|
| `id` | integer | Primary key |
| `instrument_id` | integer | FK → instruments |
| `prompt` | string | |
| `response_type` | enum | One of: `scale_1_5`, `yes_no`, `free_text` |
| `order` | integer | Display order within the instrument (persisted as `sort_order` in the database) |

### Submission

| Field | Type | Notes |
|-------|------|--------|
| `id` | integer | Primary key |
| `patient_id` | integer | FK → patients |
| `instrument_id` | integer | FK → instruments |
| `submitted_at` | timestamp | When the submission was recorded |

### Answer

| Field | Type | Notes |
|-------|------|--------|
| `id` | integer | Primary key |
| `submission_id` | integer | FK → submissions |
| `question_id` | integer | FK → questions |
| `value` | (typed) | Must match the question’s `response_type` |

## HTTP API

| Method | Path | Description |
|--------|------|-------------|
| `POST` | `/api/patients` | Create a patient |
| `POST` | `/api/instruments` | Create an instrument (with questions) |
| `POST` | `/api/patients/{patient_id}/submissions` | Submit a completed instrument for a patient |
| `GET` | `/api/patients/{patient_id}/submissions` | List submissions (paginated, newest first) |
| `GET` | `/api/patients/{patient_id}/submissions/{submission_id}` | Get one submission with answers |
| `GET` | `/api/patients/{patient_id}/summary?instrument_id={id}` | Aggregated summary for that patient and instrument |
| `GET` | `/api/try-me` | Only for the curious of mind |

## Implemented endpoints — request / response examples

The following match the current Laravel implementation (status codes and JSON shapes).

### `POST /api/patients`

**Request body**

```json
{
  "name": "Alex Chen",
  "date_of_birth": "1990-05-20",
  "mrn": "MRN-10001"
}
```

- `date_of_birth` must be `Y-m-d`.
- `mrn` must be unique across all patients.

**201 Created** — created patient (no `data` wrapper):

```json
{
  "id": 1,
  "name": "Alex Chen",
  "date_of_birth": "1990-05-20",
  "mrn": "MRN-10001",
  "created_at": "2026-04-19T12:00:00+00:00",
  "updated_at": "2026-04-19T12:00:00+00:00"
}
```

(`created_at` / `updated_at` are ISO-8601 strings from the API.)

**422 Unprocessable Entity** — validation failed:

```json
{
  "message": "Validation failed",
  "errors": {
    "mrn": ["The mrn has already been taken."]
  }
}
```

### `POST /api/instruments`

**Request body**

```json
{
  "title": "Weekly symptom check-in",
  "description": "Optional description; may be omitted or null.",
  "questions": [
    {
      "prompt": "Overall, how would you rate your pain this week?",
      "response_type": "scale_1_5",
      "sort_order": 1
    },
    {
      "prompt": "Did you take your medications as directed?",
      "response_type": "yes_no",
      "sort_order": 2
    }
  ]
}
```

- `description` is optional (`nullable`).
- `questions` is required and must contain at least one item.
- `response_type` must be one of: `scale_1_5`, `yes_no`, `free_text`.
- `sort_order` is a non-negative integer (SQL column `sort_order`).

**201 Created** — instrument with nested questions:

```json
{
  "id": 1,
  "title": "Weekly symptom check-in",
  "description": "Optional description; may be omitted or null.",
  "questions": [
    {
      "id": 1,
      "instrument_id": 1,
      "prompt": "Overall, how would you rate your pain this week?",
      "response_type": "scale_1_5",
      "sort_order": 1,
      "created_at": "2026-04-19T12:00:00+00:00",
      "updated_at": "2026-04-19T12:00:00+00:00"
    },
    {
      "id": 2,
      "instrument_id": 1,
      "prompt": "Did you take your medications as directed?",
      "response_type": "yes_no",
      "sort_order": 2,
      "created_at": "2026-04-19T12:00:05+00:00",
      "updated_at": "2026-04-19T12:00:05+00:00"
    }
  ],
  "created_at": "2026-04-19T12:00:00+00:00",
  "updated_at": "2026-04-19T12:00:00+00:00"
}
```

**422 Unprocessable Entity** — example when `questions` is empty:

```json
{
  "message": "Validation failed",
  "errors": {
    "questions": ["The questions field must have at least 1 items."]
  }
}
```

### `POST /api/patients/{patient_id}/submissions`

**Request body**

```json
{
  "instrument_id": 1,
  "answers": [
    { "question_id": 1, "value": 4 },
    { "question_id": 2, "value": true },
    { "question_id": 3, "value": "Mild nausea, otherwise fine." }
  ]
}
```

- `instrument_id` must exist.
- `answers` must include exactly one answer per question on the instrument.
- `value` must match the target question type:
  - `scale_1_5` => integer `1..5`
  - `yes_no` => boolean
  - `free_text` => string (empty string allowed)

**201 Created** — submission with nested instrument and answers:

```json
{
  "id": 10,
  "patient_id": 1,
  "instrument_id": 1,
  "submitted_at": "2026-04-20T16:45:00+00:00",
  "instrument": {
    "id": 1,
    "title": "Weekly symptom check-in",
    "description": "Short PRO questionnaire",
    "questions": [
      {
        "id": 1,
        "instrument_id": 1,
        "prompt": "Overall, how would you rate your pain this week?",
        "response_type": "scale_1_5",
        "sort_order": 1,
        "created_at": "2026-04-20T16:00:00+00:00",
        "updated_at": "2026-04-20T16:00:00+00:00"
      }
    ],
    "created_at": "2026-04-20T16:00:00+00:00",
    "updated_at": "2026-04-20T16:00:00+00:00"
  },
  "answers": [
    {
      "id": 100,
      "question_id": 1,
      "value": 4,
      "question": {
        "id": 1,
        "instrument_id": 1,
        "prompt": "Overall, how would you rate your pain this week?",
        "response_type": "scale_1_5",
        "sort_order": 1,
        "created_at": "2026-04-20T16:00:00+00:00",
        "updated_at": "2026-04-20T16:00:00+00:00"
      },
      "created_at": "2026-04-20T16:45:00+00:00",
      "updated_at": "2026-04-20T16:45:00+00:00"
    }
  ],
  "created_at": "2026-04-20T16:45:00+00:00",
  "updated_at": "2026-04-20T16:45:00+00:00"
}
```

**422 Unprocessable Entity** — example invalid payload:

```json
{
  "message": "Validation failed",
  "errors": {
    "answers.0.value": ["The value must be an integer between 1 and 5."]
  }
}
```

### `GET /api/patients/{patient_id}/submissions`

Returns the patient's submissions ordered by `submitted_at` descending.

**200 OK** — paginated response:

```json
{
  "data": [
    {
      "id": 12,
      "patient_id": 1,
      "instrument_id": 1,
      "submitted_at": "2026-04-20T17:00:00+00:00",
      "instrument": {
        "id": 1,
        "title": "Weekly symptom check-in",
        "description": "Short PRO questionnaire",
        "questions": []
      },
      "answers": [],
      "created_at": "2026-04-20T17:00:00+00:00",
      "updated_at": "2026-04-20T17:00:00+00:00"
    }
  ],
  "links": {
    "first": "http://localhost:8000/api/patients/1/submissions?page=1",
    "last": "http://localhost:8000/api/patients/1/submissions?page=1",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "path": "http://localhost:8000/api/patients/1/submissions",
    "per_page": 15,
    "to": 1,
    "total": 1
  }
}
```

### `GET /api/patients/{patient_id}/submissions/{submission_id}`

Returns one submission. If the `submission_id` exists but belongs to a different patient, the API responds with **404 Not Found**.

**404 Not Found** — example scoped miss:

```json
{
  "message": "Not found"
}
```

## Sample curl commands

Base URL (local Docker setup): `http://localhost:8000`. Run these in your normal terminal, **not** inside `php artisan tinker`.

### Get a bearer token (Sanctum)

If you do not already have a user, create one in tinker first:

```bash
docker compose exec app php artisan tinker
```

```php
App\Models\User::create([
  'name' => 'Test User',
  'email' => 'auth@example.com',
  'password' => 'password',
]);
```

Request a token:

```bash
curl -sS -X POST http://localhost:8000/api/auth/token \
  -H "Content-Type: application/json" \
  -d '{
    "email": "auth@example.com",
    "password": "password",
    "device_name": "postman"
  }'
```

Use the returned token in authenticated requests:

```bash
curl -sS \
  -H "Authorization: Bearer <your_token_here>" \
  "http://localhost:8000/api/patients/1/summary?instrument_id=1"
```

Test the protected auth route (dev-only):

```bash
# Without token (expects 401)
curl -i http://localhost:8000/api/auth-test

# With token (expects 200)
curl -sS \
  -H "Authorization: Bearer <your_token_here>" \
  http://localhost:8000/api/auth-test
```

`/api/auth-test` is only registered in `local` and `testing` environments. In non-dev environments it is unavailable (404).

### Create patients

```bash
curl -sS -X POST http://localhost:8000/api/patients \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Alex Chen",
    "date_of_birth": "1990-05-20",
    "mrn": "MRN-DEMO-001"
  }'
```

Use a **different** `mrn` for each patient:

```bash
curl -sS -X POST http://localhost:8000/api/patients \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Sam Okonkwo",
    "date_of_birth": "1975-11-02",
    "mrn": "MRN-DEMO-002"
  }'
```

### Create an instrument (with questions)

```bash
curl -sS -X POST http://localhost:8000/api/instruments \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Weekly symptom check-in",
    "description": "Short PRO questionnaire",
    "questions": [
      {
        "prompt": "Overall, how would you rate your pain this week?",
        "response_type": "scale_1_5",
        "sort_order": 1
      },
      {
        "prompt": "Did you take your medications as directed?",
        "response_type": "yes_no",
        "sort_order": 2
      },
      {
        "prompt": "Anything else for your care team?",
        "response_type": "free_text",
        "sort_order": 3
      }
    ]
  }'
```

`description` is optional (omit it or set to `null`). Successful responses are **201** with JSON; validation errors are **422** with `message` and `errors`.

### Create a submission

```bash
curl -sS -X POST http://localhost:8000/api/patients/1/submissions \
  -H "Content-Type: application/json" \
  -d '{
    "instrument_id": 1,
    "answers": [
      {
        "question_id": 1,
        "value": 4
      },
      {
        "question_id": 2,
        "value": true
      },
      {
        "question_id": 3,
        "value": "Mild nausea, otherwise fine."
      }
    ]
  }'
```

### List submissions for a patient

```bash
curl -sS http://localhost:8000/api/patients/1/submissions
```

### Get one submission for a patient

```bash
curl -sS http://localhost:8000/api/patients/1/submissions/10
```

## `GET /api/patients/{patient_id}/summary?instrument_id={id}`

Returns an aggregated summary for one patient and one instrument.

**Query parameter**

- `instrument_id` (required, integer, must exist)

**200 OK** — example with submissions:

```json
{
  "patient_id": 1,
  "instrument_id": 1,
  "total_submissions": 2,
  "earliest_submission": "2026-04-20T10:00:00+00:00",
  "latest_submission": "2026-04-21T10:00:00+00:00",
  "questions": [
    {
      "question_id": 1,
      "prompt": "Overall, how would you rate your pain this week?",
      "response_type": "scale_1_5",
      "average_score": 3
    },
    {
      "question_id": 2,
      "prompt": "Did you take your medications as directed?",
      "response_type": "yes_no",
      "yes_percentage": 50
    },
    {
      "question_id": 3,
      "prompt": "Anything else for your care team?",
      "response_type": "free_text",
      "non_empty_count": 1
    }
  ]
}
```

**200 OK** — example when no submissions exist for that patient + instrument:

```json
{
  "patient_id": 1,
  "instrument_id": 1,
  "total_submissions": 0,
  "earliest_submission": null,
  "latest_submission": null,
  "questions": [
    {
      "question_id": 1,
      "prompt": "Overall, how would you rate your pain this week?",
      "response_type": "scale_1_5",
      "average_score": null
    },
    {
      "question_id": 2,
      "prompt": "Did you take your medications as directed?",
      "response_type": "yes_no",
      "yes_percentage": null
    },
    {
      "question_id": 3,
      "prompt": "Anything else for your care team?",
      "response_type": "free_text",
      "non_empty_count": 0
    }
  ]
}
```

**422 Unprocessable Entity** — missing/invalid query parameter:

```json
{
  "message": "Validation failed",
  "errors": {
    "instrument_id": ["The instrument id field is required."]
  }
}
```

**404 Not Found** — unknown patient:

```json
{
  "message": "Not found"
}
```

### Summary curl example

```bash
curl -sS "http://localhost:8000/api/patients/1/summary?instrument_id=1"
```

### `GET /api/try-me`

Only for the curious of mind.

```bash
curl -sS "http://localhost:8000/api/try-me"
```

## Validation (overview)

- Patient and instrument must exist where referenced.
- Submissions: every question on the instrument must be answered.
- **MRN** must be unique across patients.
- Answer types: `scale_1_5` → integer 1–5; `yes_no` → boolean; `free_text` → string (may be empty).

Validation error shape:

```json
{
  "message": "Validation failed",
  "errors": {
    "field_name": ["Error message"]
  }
}
```

Rate limiting is enabled for all `/api/*` routes. See [`openapi.yaml`](openapi.yaml) for `429 Too Many Requests` response contracts.
