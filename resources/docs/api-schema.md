# PRO API — schema and endpoints

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

## Implemented endpoints — request / response examples

The following match the current Laravel implementation (status codes and JSON shapes). Other rows in the table above are not implemented yet.

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

## Summary endpoint (`GET .../summary`)

Query parameter: `instrument_id` (required).

Per question type (across all submissions for that patient + instrument):

- **`scale_1_5`** — average score
- **`yes_no`** — percentage of “yes” responses
- **`free_text`** — count of non-empty responses

Also includes: `total_submissions`, `earliest_submission`, `latest_submission`.

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
