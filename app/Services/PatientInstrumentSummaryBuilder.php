<?php

namespace App\Services;

use App\Enums\ResponseType;
use App\Models\Instrument;
use App\Models\Patient;
use Illuminate\Support\Collection;

class PatientInstrumentSummaryBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(Patient $patient, int $instrumentId): array
    {
        $instrument = Instrument::query()
            ->with('questions')
            ->findOrFail($instrumentId);

        $submissions = $patient->submissions()
            ->where('instrument_id', $instrument->id)
            ->with('answers.question')
            ->orderBy('submitted_at')
            ->get();

        $totalSubmissions = $submissions->count();
        $earliestSubmission = $submissions->first()?->submitted_at?->toIso8601String();
        $latestSubmission = $submissions->last()?->submitted_at?->toIso8601String();

        $questions = $instrument->questions->map(function ($question) use ($submissions): array {
            $responseType = $question->response_type;
            if (! $responseType instanceof ResponseType) {
                $responseType = ResponseType::from((string) $responseType);
            }

            $questionAnswers = $submissions
                ->flatMap->answers
                ->where('question_id', $question->id)
                ->pluck('value')
                ->values();

            $basePayload = [
                'question_id' => $question->id,
                'prompt' => $question->prompt,
                'response_type' => $responseType->value,
            ];

            return match ($responseType) {
                ResponseType::Scale1To5 => [
                    ...$basePayload,
                    'average_score' => $this->computeAverageScore($questionAnswers),
                ],
                ResponseType::YesNo => [
                    ...$basePayload,
                    'yes_percentage' => $this->computeYesPercentage($questionAnswers),
                ],
                ResponseType::FreeText => [
                    ...$basePayload,
                    'non_empty_count' => $this->computeNonEmptyCount($questionAnswers),
                ],
            };
        })->values()->all();

        return [
            'patient_id' => $patient->id,
            'instrument_id' => $instrument->id,
            'total_submissions' => $totalSubmissions,
            'earliest_submission' => $earliestSubmission,
            'latest_submission' => $latestSubmission,
            'questions' => $questions,
        ];
    }

    private function computeAverageScore(Collection $values): ?float
    {
        $numericValues = $values
            ->filter(static fn ($value): bool => is_int($value) || is_float($value))
            ->map(static fn ($value): float => (float) $value)
            ->values();

        if ($numericValues->isEmpty()) {
            return null;
        }

        return round($numericValues->avg(), 2);
    }

    private function computeYesPercentage(Collection $values): ?float
    {
        $booleanValues = $values
            ->filter(static fn ($value): bool => is_bool($value))
            ->values();

        if ($booleanValues->isEmpty()) {
            return null;
        }

        $yesCount = $booleanValues
            ->filter(static fn (bool $value): bool => $value)
            ->count();

        return round(($yesCount / $booleanValues->count()) * 100, 2);
    }

    private function computeNonEmptyCount(Collection $values): int
    {
        return $values
            ->filter(static fn ($value): bool => is_string($value) && trim($value) !== '')
            ->count();
    }
}
