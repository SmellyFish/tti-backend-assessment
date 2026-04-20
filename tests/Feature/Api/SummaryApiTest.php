<?php

namespace Tests\Feature\Api;

use App\Models\Instrument;
use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ProApiFixtures;
use Tests\TestCase;

class SummaryApiTest extends TestCase
{
    use RefreshDatabase;
    use ProApiFixtures;

    public function test_summary_returns_aggregates_for_mixed_question_types(): void
    {
        [$patient, $instrument, $questions] = $this->seedProPatientWithInstrument();

        $this->createProSubmissionWithAnswers($patient, $instrument, $questions, now()->subDays(2), 2, true, '');
        $this->createProSubmissionWithAnswers($patient, $instrument, $questions, now()->subDay(), 4, false, 'Some notes');

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
        $this->assertMatchesOpenApiContract($response, 'GET', '/api/patients/{patient}/summary');

        $this->assertNotNull($response->json('earliest_submission'));
        $this->assertNotNull($response->json('latest_submission'));
    }

    public function test_summary_returns_zero_state_when_no_submissions_exist(): void
    {
        [$patient, $instrument] = $this->seedProPatientWithInstrument();

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
        [$patient, $instrument, $questions] = $this->seedProPatientWithInstrument();
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
        $this->createProSubmissionWithAnswers($patient, $otherInstrument, $otherQuestions, now(), 5, true, 'Other');

        $response = $this->getJson("/api/patients/{$patient->id}/summary?instrument_id={$instrument->id}");

        $response->assertOk()
            ->assertJsonPath('instrument_id', $instrument->id)
            ->assertJsonPath('total_submissions', 0)
            ->assertJsonPath('questions.0.average_score', null)
            ->assertJsonPath('questions.1.yes_percentage', null)
            ->assertJsonPath('questions.2.non_empty_count', 0);
    }

    public function test_summary_returns_non_integer_average_with_stable_rounding(): void
    {
        [$patient, $instrument, $questions] = $this->seedProPatientWithInstrument();

        $this->createProSubmissionWithAnswers($patient, $instrument, $questions, now()->subHour(), 2, true, 'Low day');
        $this->createProSubmissionWithAnswers($patient, $instrument, $questions, now(), 3, false, 'Better day');

        $response = $this->getJson("/api/patients/{$patient->id}/summary?instrument_id={$instrument->id}");

        $response->assertOk()
            ->assertJsonPath('questions.0.average_score', 2.5);
    }

    public function test_summary_ignores_non_boolean_yes_no_values_in_denominator(): void
    {
        [$patient, $instrument, $questions] = $this->seedProPatientWithInstrument();

        $validSubmission = Submission::query()->create([
            'patient_id' => $patient->id,
            'instrument_id' => $instrument->id,
            'submitted_at' => now()->subMinute(),
        ]);
        $validSubmission->answers()->createMany([
            ['question_id' => $questions[0]->id, 'value' => 4],
            ['question_id' => $questions[1]->id, 'value' => true],
            ['question_id' => $questions[2]->id, 'value' => 'valid boolean'],
        ]);

        $invalidYesNoSubmission = Submission::query()->create([
            'patient_id' => $patient->id,
            'instrument_id' => $instrument->id,
            'submitted_at' => now(),
        ]);
        $invalidYesNoSubmission->answers()->createMany([
            ['question_id' => $questions[0]->id, 'value' => 5],
            ['question_id' => $questions[1]->id, 'value' => 'yes'],
            ['question_id' => $questions[2]->id, 'value' => 'non-boolean yes/no'],
        ]);

        $response = $this->getJson("/api/patients/{$patient->id}/summary?instrument_id={$instrument->id}");

        $response->assertOk()
            ->assertJsonPath('questions.1.yes_percentage', 100);
    }

    public function test_summary_free_text_count_locks_unicode_whitespace_behavior(): void
    {
        [$patient, $instrument, $questions] = $this->seedProPatientWithInstrument();

        $this->createProSubmissionWithAnswers($patient, $instrument, $questions, now()->subMinutes(3), 2, true, '');
        $this->createProSubmissionWithAnswers($patient, $instrument, $questions, now()->subMinutes(2), 3, true, '   ');
        $this->createProSubmissionWithAnswers($patient, $instrument, $questions, now()->subMinute(), 4, true, "\u{00A0}");
        $this->createProSubmissionWithAnswers($patient, $instrument, $questions, now(), 5, true, "\u{3000}");

        $response = $this->getJson("/api/patients/{$patient->id}/summary?instrument_id={$instrument->id}");

        $response->assertOk()
            ->assertJsonPath('questions.2.non_empty_count', 2);
    }

    public function test_summary_requires_instrument_id_query_parameter(): void
    {
        [$patient] = $this->seedProPatientWithInstrument();

        $response = $this->getJson("/api/patients/{$patient->id}/summary");

        $response->assertStatus(422)
            ->assertJsonPath('message', __('api.validation_failed'))
            ->assertJsonValidationErrors(['instrument_id']);
        $this->assertMatchesOpenApiContract($response, 'GET', '/api/patients/{patient}/summary');
    }

    public function test_summary_validates_instrument_id_exists(): void
    {
        [$patient] = $this->seedProPatientWithInstrument();

        $response = $this->getJson("/api/patients/{$patient->id}/summary?instrument_id=999999");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['instrument_id']);
    }

    public function test_summary_returns_404_when_patient_does_not_exist(): void
    {
        [, $instrument] = $this->seedProPatientWithInstrument();

        $response = $this->getJson("/api/patients/999999/summary?instrument_id={$instrument->id}");

        $response->assertNotFound()
            ->assertJsonPath('message', __('api.not_found'));
    }

}
