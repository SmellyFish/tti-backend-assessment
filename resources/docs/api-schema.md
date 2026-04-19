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
| `order` | integer | Display order within the instrument |

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
