<?php

namespace App\Http\Controllers\Api;

use App\Enums\ResponseType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInstrumentRequest;
use App\Http\Resources\InstrumentResource;
use App\Models\Instrument;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class InstrumentController extends Controller
{
    public function store(StoreInstrumentRequest $request): JsonResponse
    {
        $instrument = DB::transaction(function () use ($request) {
            $validated = $request->validated();
            $instrument = Instrument::query()->create([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
            ]);

            foreach ($validated['questions'] as $row) {
                $responseType = $row['response_type'];
                if (! $responseType instanceof ResponseType) {
                    $responseType = ResponseType::from((string) $responseType);
                }
                $instrument->questions()->create([
                    'prompt' => $row['prompt'],
                    'response_type' => $responseType,
                    'sort_order' => $row['sort_order'],
                ]);
            }

            return $instrument->load('questions');
        });

        return (new InstrumentResource($instrument))
            ->response()
            ->setStatusCode(201);
    }
}
