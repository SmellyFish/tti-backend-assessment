<?php

namespace App\Http\Requests;

use App\Enums\ResponseType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInstrumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.prompt' => ['required', 'string'],
            'questions.*.response_type' => ['required', Rule::enum(ResponseType::class)],
            'questions.*.sort_order' => ['required', 'integer', 'min:0'],
        ];
    }
}
