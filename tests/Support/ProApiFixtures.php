<?php

namespace Tests\Support;

use App\Models\Instrument;
use App\Models\Patient;
use App\Models\Question;
use App\Models\Submission;

trait ProApiFixtures
{
    /**
     * @return array{Patient, Instrument, array<int, Question>}
     */
    protected function seedProPatientWithInstrument(): array
    {
        $patient = Patient::query()->create([
            'name' => 'Fixture Patient',
            'date_of_birth' => '1990-05-20',
            'mrn' => 'MRN-'.uniqid(),
        ]);

        $instrument = Instrument::query()->create([
            'title' => 'Fixture Instrument',
            'description' => 'Shared test fixture',
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
    protected function createProSubmissionWithAnswers(
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
