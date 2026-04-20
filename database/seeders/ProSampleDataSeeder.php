<?php

namespace Database\Seeders;

use App\Enums\ResponseType;
use App\Models\Answer;
use App\Models\Instrument;
use App\Models\Patient;
use App\Models\Question;
use App\Models\Submission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class ProSampleDataSeeder extends Seeder
{
    private const PATIENT_COUNT = 50;

    private const INSTRUMENT_COUNT = 60;

    private const MIN_QUESTIONS_PER_INSTRUMENT = 3;

    private const MAX_QUESTIONS_PER_INSTRUMENT = 6;

    private const SUBMISSION_COUNT = 240;

    public function run(): void
    {
        Answer::query()->delete();
        Submission::query()->delete();
        Question::query()->delete();
        Instrument::query()->delete();
        Patient::query()->delete();

        $patients = $this->seedPatients();
        $instruments = $this->seedInstrumentsWithQuestions();
        $this->seedSubmissions($patients, $instruments);
    }

    /**
     * @return Collection<int, Patient>
     */
    private function seedPatients(): Collection
    {
        $patients = collect();

        for ($i = 1; $i <= self::PATIENT_COUNT; $i++) {
            $patients->push(Patient::query()->create([
                'name' => fake()->name(),
                'date_of_birth' => fake()->dateTimeBetween('-85 years', '-18 years')->format('Y-m-d'),
                'mrn' => sprintf('MRN-%05d-%03d', $i, fake()->numberBetween(100, 999)),
            ]));
        }

        return $patients;
    }

    /**
     * @return Collection<int, Instrument>
     */
    private function seedInstrumentsWithQuestions(): Collection
    {
        $instruments = collect();

        for ($i = 1; $i <= self::INSTRUMENT_COUNT; $i++) {
            $instrument = Instrument::query()->create([
                'title' => sprintf('Instrument %02d - %s', $i, fake()->words(3, true)),
                'description' => fake()->optional()->sentence(12),
            ]);

            $questionCount = fake()->numberBetween(
                self::MIN_QUESTIONS_PER_INSTRUMENT,
                self::MAX_QUESTIONS_PER_INSTRUMENT
            );

            for ($sortOrder = 1; $sortOrder <= $questionCount; $sortOrder++) {
                $responseType = fake()->randomElement([
                    ResponseType::Scale1To5,
                    ResponseType::YesNo,
                    ResponseType::FreeText,
                ]);

                $instrument->questions()->create([
                    'prompt' => sprintf(
                        '[%s] %s',
                        $responseType->value,
                        fake()->sentence(fake()->numberBetween(6, 12))
                    ),
                    'response_type' => $responseType,
                    'sort_order' => $sortOrder,
                ]);
            }

            $instruments->push($instrument->load('questions'));
        }

        return $instruments;
    }

    /**
     * @param  Collection<int, Patient>  $patients
     * @param  Collection<int, Instrument>  $instruments
     */
    private function seedSubmissions(
        Collection $patients,
        Collection $instruments
    ): void {
        $randomCount = self::SUBMISSION_COUNT - (self::PATIENT_COUNT * 2);

        foreach ($patients as $patient) {
            /** @var Instrument $chosen */
            $chosen = $instruments->random();

            $this->createSubmissionWithAnswers(
                $patient,
                $chosen,
                fake()->dateTimeBetween('-150 days', '-30 days')
            );
            $this->createSubmissionWithAnswers(
                $patient,
                $chosen,
                fake()->dateTimeBetween('-29 days', 'now')
            );
        }

        for ($i = 0; $i < $randomCount; $i++) {
            /** @var Patient $patient */
            $patient = $patients->random();
            /** @var Instrument $instrument */
            $instrument = $instruments->random();

            $this->createSubmissionWithAnswers(
                $patient,
                $instrument,
                fake()->dateTimeBetween('-180 days', 'now')
            );
        }
    }

    private function createSubmissionWithAnswers(
        Patient $patient,
        Instrument $instrument,
        \DateTimeInterface $submittedAt
    ): void {
        $submission = Submission::query()->create([
            'patient_id' => $patient->id,
            'instrument_id' => $instrument->id,
            'submitted_at' => $submittedAt,
        ]);

        foreach ($instrument->questions as $question) {
            $value = match ($question->response_type) {
                ResponseType::Scale1To5 => fake()->numberBetween(1, 5),
                ResponseType::YesNo => fake()->boolean(),
                ResponseType::FreeText => fake()->boolean(20) ? '' : fake()->sentence(fake()->numberBetween(4, 12)),
            };

            $submission->answers()->create([
                'question_id' => $question->id,
                'value' => $value,
            ]);
        }
    }
}
