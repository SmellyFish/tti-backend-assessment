<?php

namespace Tests\Feature\Api;

use App\Models\Instrument;
use App\Models\Patient;
use App\Models\Question;
use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SummaryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_returns_aggregates_for_mixed_question_types(): void
    {
        [$patient, $instrument, $questions] = $this->seedPatientAndInstrument();

        $this->createSubmission($patient, $instrument, $questions, now()->subDays(2), 2, true, '');
        $this->createSubmission($patient, $instrument, $questions, now()->subDay(), 4, false, 'Some notes');

        $response = $this->getJson("/api/patients/{$patient->id}/summary?instrument_id={$instrument->id}");

        $response->assertOk()
            ->assertJsonPath('patient_id', $patient->id)
            ->assertJsonPath('instrument_id', $instrument->id)
            ->assertJsonPath('total_submissions', 2)
            ->assertJsonPath('questions.0.response_type', 'scale_1_5')
            ->assertJsonPath('questions.0.average_score', 3)
            ->assertJsonPath('questions.1.response_type', 'yes_no')
            ->assertJsonPath('questions.1.yes_percentage', 50)
            ->assertJsonPath('questions.2.response_type', 'free_text')
            ->assertJsonPath('questions.2.non_empty_count', 1);

        $this->assertNotNull($response->json('earliest_submission'));
        $this->assertNotNull($response->json('latest_submission'));
    }

    public function test_summary_returns_zero_state_when_no_submissions_exist(): void
    {
        [$patient, $instrument] = $this->seedPatientAndInstrument();

        $response = $this->getJson("/api/patients/{$patient->id}/summary?instrument_id={$instrument->id}");

        $response->assertOk()
            ->assertJsonPath('total_submissions', 0)
            ->assertJsonPath('earliest_submission', null)
            ->assertJsonPath('latest_submission', null)
            ->assertJsonPath('questions.0.average_score', null)
            ->assertJsonPath('questions.1.yes_percentage', null)
            ->assertJsonPath('questions.2.non_empty_count', 0);
    }

    public function test_summary_returns_zero_state_when_patient_only_has_other_instrument_submissions(): void
    {
        [$patient, $instrument, $questions] = $this->seedPatientAndInstrument();
        $otherInstrument = Instrument::query()->create([
            'title' => 'Other instrument',
            'description' => 'Unrelated',
        ]);
        $otherQuestions = [
            $otherInstrument->questions()->create([
                'prompt' => 'Other scale',
                'response_type' => 'scale_1_5',
                'sort_order' => 1,
            ]),
            $otherInstrument->questions()->create([
                'prompt' => 'Other yes no',
                'response_type' => 'yes_no',
                'sort_order' => 2,
            ]),
            $otherInstrument->questions()->create([
                'prompt' => 'Other text',
                'response_type' => 'free_text',
                'sort_order' => 3,
            ]),
        ];
        $this->createSubmission($patient, $otherInstrument, $otherQuestions, now(), 5, true, 'Other');

        $response = $this->getJson("/api/patients/{$patient->id}/summary?instrument_id={$instrument->id}");

        $response->assertOk()
            ->assertJsonPath('instrument_id', $instrument->id)
            ->assertJsonPath('total_submissions', 0)
            ->assertJsonPath('questions.0.average_score', null)
            ->assertJsonPath('questions.1.yes_percentage', null)
            ->assertJsonPath('questions.2.non_empty_count', 0);
    }

    public function test_summary_requires_instrument_id_query_parameter(): void
    {
        [$patient] = $this->seedPatientAndInstrument();

        $response = $this->getJson("/api/patients/{$patient->id}/summary");

        $response->assertStatus(422)
            ->assertJsonPath('message', __('api.validation_failed'))
            ->assertJsonValidationErrors(['instrument_id']);
    }

    public function test_summary_validates_instrument_id_exists(): void
    {
        [$patient] = $this->seedPatientAndInstrument();

        $response = $this->getJson("/api/patients/{$patient->id}/summary?instrument_id=999999");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['instrument_id']);
    }

    public function test_summary_returns_404_when_patient_does_not_exist(): void
    {
        [, $instrument] = $this->seedPatientAndInstrument();

        $response = $this->getJson("/api/patients/999999/summary?instrument_id={$instrument->id}");

        $response->assertNotFound()
            ->assertJsonPath('message', __('api.not_found'));
    }

    /**
     * @return array{Patient, Instrument, array<int, Question>}
     */
    private function seedPatientAndInstrument(): array
    {
        $patient = Patient::query()->create([
            'name' => 'Summary Patient',
            'date_of_birth' => '1990-05-20',
            'mrn' => 'MRN-SUMMARY-'.uniqid(),
        ]);

        $instrument = Instrument::query()->create([
            'title' => 'Summary Instrument',
            'description' => 'For summary tests',
        ]);

        $questions = [
            $instrument->questions()->create([
                'prompt' => 'Scale question',
                'response_type' => 'scale_1_5',
                'sort_order' => 1,
            ]),
            $instrument->questions()->create([
                'prompt' => 'Yes/no question',
                'response_type' => 'yes_no',
                'sort_order' => 2,
            ]),
            $instrument->questions()->create([
                'prompt' => 'Text question',
                'response_type' => 'free_text',
                'sort_order' => 3,
            ]),
        ];

        return [$patient, $instrument, $questions];
    }

    /**
     * @param  array<int, Question>  $questions
     */
    private function createSubmission(
        Patient $patient,
        Instrument $instrument,
        array $questions,
        \DateTimeInterface $submittedAt,
        int $scaleValue,
        bool $yesValue,
        string $textValue,
    ): Submission {
        $submission = Submission::query()->create([
            'patient_id' => $patient->id,
            'instrument_id' => $instrument->id,
            'submitted_at' => $submittedAt,
        ]);

        $submission->answers()->createMany([
            ['question_id' => $questions[0]->id, 'value' => $scaleValue],
            ['question_id' => $questions[1]->id, 'value' => $yesValue],
            ['question_id' => $questions[2]->id, 'value' => $textValue],
        ]);

        return $submission;
    }
}
