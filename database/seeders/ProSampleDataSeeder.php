<?php

namespace Database\Seeders;

use App\Enums\ResponseType;
use App\Models\Instrument;
use App\Models\Patient;
use App\Models\Submission;
use Illuminate\Database\Seeder;

class ProSampleDataSeeder extends Seeder
{
    public function run(): void
    {
        $patientA = Patient::query()->create([
            'name' => 'Jordan Rivera',
            'date_of_birth' => '1988-03-15',
            'mrn' => 'MRN-10001',
        ]);

        $patientB = Patient::query()->create([
            'name' => 'Sam Okonkwo',
            'date_of_birth' => '1975-11-02',
            'mrn' => 'MRN-10002',
        ]);

        $instrument = Instrument::query()->create([
            'title' => 'Weekly symptom check-in',
            'description' => 'Short questionnaire for chronic care follow-up and remote monitoring.',
        ]);

        $questionPain = $instrument->questions()->create([
            'prompt' => 'Overall, how would you rate your pain this week?',
            'response_type' => ResponseType::Scale1To5,
            'sort_order' => 1,
        ]);

        $questionMeds = $instrument->questions()->create([
            'prompt' => 'Did you take your prescribed medications as directed?',
            'response_type' => ResponseType::YesNo,
            'sort_order' => 2,
        ]);

        $questionNotes = $instrument->questions()->create([
            'prompt' => 'Anything else you want your care team to know?',
            'response_type' => ResponseType::FreeText,
            'sort_order' => 3,
        ]);

        $this->seedSubmission(
            $patientA,
            $instrument,
            now()->subDays(5),
            [
                $questionPain->id => 3,
                $questionMeds->id => true,
                $questionNotes->id => 'Slight morning stiffness.',
            ]
        );

        $this->seedSubmission(
            $patientA,
            $instrument,
            now()->subDay(),
            [
                $questionPain->id => 2,
                $questionMeds->id => true,
                $questionNotes->id => '',
            ]
        );

        $this->seedSubmission(
            $patientB,
            $instrument,
            now()->subHours(6),
            [
                $questionPain->id => 4,
                $questionMeds->id => false,
                $questionNotes->id => 'Pharmacy delay on refill.',
            ]
        );
    }

    /**
     * @param  array<int, int|bool|string>  $answersByQuestionId
     */
    private function seedSubmission(
        Patient $patient,
        Instrument $instrument,
        \DateTimeInterface $submittedAt,
        array $answersByQuestionId
    ): void {
        $submission = Submission::query()->create([
            'patient_id' => $patient->id,
            'instrument_id' => $instrument->id,
            'submitted_at' => $submittedAt,
        ]);

        foreach ($answersByQuestionId as $questionId => $value) {
            $submission->answers()->create([
                'question_id' => $questionId,
                'value' => $value,
            ]);
        }
    }
}
