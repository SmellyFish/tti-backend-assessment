<?php

namespace Tests\Feature\Api;

use App\Models\Instrument;
use App\Models\Patient;
use App\Models\Question;
use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmissionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_submission_creates_submission_and_answers(): void
    {
        [$patient, $instrument, $questions] = $this->seedPatientWithInstrument();

        $response = $this->postJson("/api/patients/{$patient->id}/submissions", [
            'instrument_id' => $instrument->id,
            'answers' => [
                ['question_id' => $questions[0]->id, 'value' => 4],
                ['question_id' => $questions[1]->id, 'value' => true],
                ['question_id' => $questions[2]->id, 'value' => 'Feeling better'],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('patient_id', $patient->id)
            ->assertJsonPath('instrument_id', $instrument->id)
            ->assertJsonCount(3, 'answers');

        $this->assertDatabaseCount('submissions', 1);
        $this->assertDatabaseCount('answers', 3);
    }

    public function test_store_submission_requires_all_questions_answered(): void
    {
        [$patient, $instrument, $questions] = $this->seedPatientWithInstrument();

        $response = $this->postJson("/api/patients/{$patient->id}/submissions", [
            'instrument_id' => $instrument->id,
            'answers' => [
                ['question_id' => $questions[0]->id, 'value' => 3],
                ['question_id' => $questions[1]->id, 'value' => false],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', __('api.validation_failed'))
            ->assertJsonValidationErrors(['answers']);
    }

    public function test_store_submission_validates_answer_types(): void
    {
        [$patient, $instrument, $questions] = $this->seedPatientWithInstrument();

        $response = $this->postJson("/api/patients/{$patient->id}/submissions", [
            'instrument_id' => $instrument->id,
            'answers' => [
                ['question_id' => $questions[0]->id, 'value' => '4'],
                ['question_id' => $questions[1]->id, 'value' => 'yes'],
                ['question_id' => $questions[2]->id, 'value' => ''],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['answers.0.value', 'answers.1.value']);
    }

    public function test_store_submission_requires_instrument_id(): void
    {
        [$patient, $instrument, $questions] = $this->seedPatientWithInstrument();
        $payload = $this->minimalValidSubmissionPayload($instrument->id, $questions);
        unset($payload['instrument_id']);

        $this->postJson("/api/patients/{$patient->id}/submissions", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['instrument_id']);
    }

    public function test_store_submission_requires_instrument_id_to_be_integer(): void
    {
        [$patient, $instrument, $questions] = $this->seedPatientWithInstrument();
        $payload = $this->minimalValidSubmissionPayload($instrument->id, $questions);
        $payload['instrument_id'] = 'not-an-integer';

        $this->postJson("/api/patients/{$patient->id}/submissions", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['instrument_id']);
    }

    public function test_store_submission_requires_existing_instrument_id(): void
    {
        [$patient, $instrument, $questions] = $this->seedPatientWithInstrument();
        $payload = $this->minimalValidSubmissionPayload($instrument->id, $questions);
        $payload['instrument_id'] = 999999;

        $this->postJson("/api/patients/{$patient->id}/submissions", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['instrument_id']);
    }

    public function test_store_submission_requires_answers(): void
    {
        [$patient, $instrument, $questions] = $this->seedPatientWithInstrument();
        $payload = $this->minimalValidSubmissionPayload($instrument->id, $questions);
        unset($payload['answers']);

        $this->postJson("/api/patients/{$patient->id}/submissions", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['answers']);
    }

    public function test_store_submission_requires_answers_to_be_array(): void
    {
        [$patient, $instrument, $questions] = $this->seedPatientWithInstrument();
        $payload = $this->minimalValidSubmissionPayload($instrument->id, $questions);
        $payload['answers'] = 'not-an-array';

        $this->postJson("/api/patients/{$patient->id}/submissions", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['answers']);
    }

    public function test_store_submission_requires_answers_to_have_at_least_one_item(): void
    {
        [$patient, $instrument, $questions] = $this->seedPatientWithInstrument();
        $payload = $this->minimalValidSubmissionPayload($instrument->id, $questions);
        $payload['answers'] = [];

        $this->postJson("/api/patients/{$patient->id}/submissions", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['answers']);
    }

    public function test_store_submission_requires_each_answer_to_be_array(): void
    {
        [$patient, $instrument, $questions] = $this->seedPatientWithInstrument();
        $payload = $this->minimalValidSubmissionPayload($instrument->id, $questions);
        $payload['answers'][0] = 'not-an-array';

        $this->postJson("/api/patients/{$patient->id}/submissions", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['answers.0']);
    }

    public function test_store_submission_requires_question_id(): void
    {
        [$patient, $instrument, $questions] = $this->seedPatientWithInstrument();
        $payload = $this->minimalValidSubmissionPayload($instrument->id, $questions);
        unset($payload['answers'][0]['question_id']);

        $this->postJson("/api/patients/{$patient->id}/submissions", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['answers.0.question_id']);
    }

    public function test_store_submission_requires_question_id_to_be_integer(): void
    {
        [$patient, $instrument, $questions] = $this->seedPatientWithInstrument();
        $payload = $this->minimalValidSubmissionPayload($instrument->id, $questions);
        $payload['answers'][0]['question_id'] = 'abc';

        $this->postJson("/api/patients/{$patient->id}/submissions", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['answers.0.question_id']);
    }

    public function test_store_submission_requires_distinct_question_ids(): void
    {
        [$patient, $instrument, $questions] = $this->seedPatientWithInstrument();
        $payload = $this->minimalValidSubmissionPayload($instrument->id, $questions);
        $payload['answers'][1]['question_id'] = $payload['answers'][0]['question_id'];

        $this->postJson("/api/patients/{$patient->id}/submissions", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['answers.1.question_id']);
    }

    public function test_store_submission_requires_value_key_to_be_present(): void
    {
        [$patient, $instrument, $questions] = $this->seedPatientWithInstrument();
        $payload = $this->minimalValidSubmissionPayload($instrument->id, $questions);
        unset($payload['answers'][0]['value']);

        $this->postJson("/api/patients/{$patient->id}/submissions", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['answers.0.value']);
    }

    public function test_store_submission_rejects_question_from_different_instrument(): void
    {
        [$patient, $instrument, $questions] = $this->seedPatientWithInstrument();
        $otherInstrument = Instrument::query()->create([
            'title' => 'Other instrument',
            'description' => 'Different question set',
        ]);
        $otherQuestion = $otherInstrument->questions()->create([
            'prompt' => 'Other question',
            'response_type' => 'free_text',
            'sort_order' => 1,
        ]);

        $payload = $this->minimalValidSubmissionPayload($instrument->id, $questions);
        $payload['answers'][2] = [
            'question_id' => $otherQuestion->id,
            'value' => 'Cross instrument answer',
        ];

        $this->postJson("/api/patients/{$patient->id}/submissions", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['answers.2.question_id']);
    }

    public function test_store_submission_rejects_extra_unrelated_question_answer(): void
    {
        [$patient, $instrument, $questions] = $this->seedPatientWithInstrument();
        $otherInstrument = Instrument::query()->create([
            'title' => 'Other instrument',
            'description' => 'Different question set',
        ]);
        $otherQuestion = $otherInstrument->questions()->create([
            'prompt' => 'Other question',
            'response_type' => 'free_text',
            'sort_order' => 1,
        ]);

        $payload = $this->minimalValidSubmissionPayload($instrument->id, $questions);
        $payload['answers'][] = [
            'question_id' => $otherQuestion->id,
            'value' => 'Unexpected extra answer',
        ];

        $this->postJson("/api/patients/{$patient->id}/submissions", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['answers']);
    }

    public function test_index_returns_paginated_submissions_newest_first(): void
    {
        [$patient, $instrument, $questions] = $this->seedPatientWithInstrument();

        $oldest = $this->createSubmissionWithAnswers($patient, $instrument, $questions, now()->subHours(2), 2, true, 'Old note');
        $middle = $this->createSubmissionWithAnswers($patient, $instrument, $questions, now()->subHour(), 3, false, 'Middle note');
        $newest = $this->createSubmissionWithAnswers($patient, $instrument, $questions, now(), 5, true, 'Newest note');

        $response = $this->getJson("/api/patients/{$patient->id}/submissions");

        $response->assertOk()
            ->assertJsonPath('data.0.id', $newest->id)
            ->assertJsonPath('data.1.id', $middle->id)
            ->assertJsonPath('data.2.id', $oldest->id)
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_show_returns_submission_with_answers_and_question_details(): void
    {
        [$patient, $instrument, $questions] = $this->seedPatientWithInstrument();
        $submission = $this->createSubmissionWithAnswers($patient, $instrument, $questions, now(), 4, true, 'Notes');

        $response = $this->getJson("/api/patients/{$patient->id}/submissions/{$submission->id}");

        $response->assertOk()
            ->assertJsonPath('id', $submission->id)
            ->assertJsonPath('instrument.id', $instrument->id)
            ->assertJsonCount(3, 'answers')
            ->assertJsonPath('answers.0.question.instrument_id', $instrument->id);
    }

    public function test_show_returns_404_when_submission_belongs_to_another_patient(): void
    {
        [$patientOne, $instrument, $questions] = $this->seedPatientWithInstrument();
        $patientTwo = Patient::query()->create([
            'name' => 'Other Patient',
            'date_of_birth' => '1978-09-17',
            'mrn' => 'MRN-OTHER-001',
        ]);
        $submission = $this->createSubmissionWithAnswers($patientTwo, $instrument, $questions, now(), 2, false, '');

        $response = $this->getJson("/api/patients/{$patientOne->id}/submissions/{$submission->id}");

        $response->assertNotFound()
            ->assertJsonPath('message', __('api.not_found'));
    }

    public function test_store_returns_404_when_patient_does_not_exist(): void
    {
        [, $instrument, $questions] = $this->seedPatientWithInstrument();

        $response = $this->postJson('/api/patients/999999/submissions', [
            'instrument_id' => $instrument->id,
            'answers' => [
                ['question_id' => $questions[0]->id, 'value' => 4],
                ['question_id' => $questions[1]->id, 'value' => true],
                ['question_id' => $questions[2]->id, 'value' => ''],
            ],
        ]);

        $response->assertNotFound()
            ->assertJsonPath('message', __('api.not_found'));
    }

    public function test_store_submission_round_trips_utf8_free_text_in_response_and_database(): void
    {
        [$patient, $instrument, $questions] = $this->seedPatientWithInstrument();
        $unicodeText = '体調は良いです 👍🏽 Прогресс ممتاز';

        $response = $this->postJson("/api/patients/{$patient->id}/submissions", [
            'instrument_id' => $instrument->id,
            'answers' => [
                ['question_id' => $questions[0]->id, 'value' => 5],
                ['question_id' => $questions[1]->id, 'value' => true],
                ['question_id' => $questions[2]->id, 'value' => $unicodeText],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('answers.2.value', $unicodeText);

        $submissionId = $response->json('id');
        $this->assertDatabaseHas('answers', [
            'submission_id' => $submissionId,
            'question_id' => $questions[2]->id,
            'value' => json_encode($unicodeText),
        ]);
    }

    /**
     * @return array{Patient, Instrument, array<int, Question>}
     */
    private function seedPatientWithInstrument(): array
    {
        $patient = Patient::query()->create([
            'name' => 'Alex Chen',
            'date_of_birth' => '1990-05-20',
            'mrn' => 'MRN-'.uniqid(),
        ]);

        $instrument = Instrument::query()->create([
            'title' => 'Weekly check-in',
            'description' => 'PRO instrument',
        ]);

        $q1 = $instrument->questions()->create([
            'prompt' => 'Pain level',
            'response_type' => 'scale_1_5',
            'sort_order' => 1,
        ]);
        $q2 = $instrument->questions()->create([
            'prompt' => 'Took medication?',
            'response_type' => 'yes_no',
            'sort_order' => 2,
        ]);
        $q3 = $instrument->questions()->create([
            'prompt' => 'Any notes?',
            'response_type' => 'free_text',
            'sort_order' => 3,
        ]);

        return [$patient, $instrument, [$q1, $q2, $q3]];
    }

    /**
     * @param  array<int, Question>  $questions
     */
    private function createSubmissionWithAnswers(
        Patient $patient,
        Instrument $instrument,
        array $questions,
        \DateTimeInterface $submittedAt,
        int $scaleValue,
        bool $yesNoValue,
        string $textValue,
    ): Submission {
        $submission = Submission::query()->create([
            'patient_id' => $patient->id,
            'instrument_id' => $instrument->id,
            'submitted_at' => $submittedAt,
        ]);

        $submission->answers()->createMany([
            ['question_id' => $questions[0]->id, 'value' => $scaleValue],
            ['question_id' => $questions[1]->id, 'value' => $yesNoValue],
            ['question_id' => $questions[2]->id, 'value' => $textValue],
        ]);

        return $submission;
    }

    /**
     * @param  array<int, Question>  $questions
     * @return array<string, mixed>
     */
    private function minimalValidSubmissionPayload(int $instrumentId, array $questions): array
    {
        return [
            'instrument_id' => $instrumentId,
            'answers' => [
                ['question_id' => $questions[0]->id, 'value' => 4],
                ['question_id' => $questions[1]->id, 'value' => true],
                ['question_id' => $questions[2]->id, 'value' => ''],
            ],
        ];
    }
}
