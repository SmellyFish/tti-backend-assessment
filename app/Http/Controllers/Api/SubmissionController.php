<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SummaryRequest;
use App\Http\Requests\StoreSubmissionRequest;
use App\Http\Resources\PatientSummaryResource;
use App\Http\Resources\SubmissionResource;
use App\Models\Patient;
use App\Models\Submission;
use App\Services\PatientInstrumentSummaryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class SubmissionController extends Controller
{
    public function store(Patient $patient, StoreSubmissionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $submission = DB::transaction(function () use ($patient, $validated) {
            $submission = Submission::query()->create([
                'patient_id' => $patient->id,
                'instrument_id' => $validated['instrument_id'],
                'submitted_at' => now(),
            ]);

            $answers = collect($validated['answers'])
                ->map(fn (array $answer): array => [
                    'question_id' => $answer['question_id'],
                    'value' => $answer['value'],
                ])
                ->all();

            $submission->answers()->createMany($answers);

            return $submission;
        });

        $submission->load(['instrument.questions', 'answers.question']);

        return (new SubmissionResource($submission))
            ->response()
            ->setStatusCode(201);
    }

    public function index(Patient $patient): AnonymousResourceCollection
    {
        $submissions = $patient->submissions()
            ->with(['instrument.questions', 'answers.question'])
            ->orderByDesc('submitted_at')
            ->paginate();

        return SubmissionResource::collection($submissions);
    }

    public function show(Patient $patient, int $submission): SubmissionResource
    {
        $submissionModel = $patient->submissions()
            ->with(['instrument.questions', 'answers.question'])
            ->whereKey($submission)
            ->firstOrFail();

        return new SubmissionResource($submissionModel);
    }

    public function summary(
        Patient $patient,
        SummaryRequest $request,
        PatientInstrumentSummaryBuilder $summaryBuilder
    ): PatientSummaryResource {
        return new PatientSummaryResource(
            $summaryBuilder->build($patient, $request->integer('instrument_id'))
        );
    }
}
