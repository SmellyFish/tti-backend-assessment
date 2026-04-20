<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'patient_id' => $this['patient_id'],
            'instrument_id' => $this['instrument_id'],
            'total_submissions' => $this['total_submissions'],
            'earliest_submission' => $this['earliest_submission'],
            'latest_submission' => $this['latest_submission'],
            'questions' => $this['questions'],
        ];
    }
}
