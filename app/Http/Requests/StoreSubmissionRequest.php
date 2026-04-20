<?php

namespace App\Http\Requests;

use App\Enums\ResponseType;
use App\Models\Instrument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSubmissionRequest extends FormRequest
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
            'instrument_id' => ['required', 'integer', Rule::exists('instruments', 'id')],
            'answers' => ['required', 'array', 'min:1'],
            'answers.*' => ['required', 'array'],
            'answers.*.question_id' => ['required', 'integer', 'distinct'],
            'answers.*.value' => ['present'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $instrumentId = $this->integer('instrument_id');
            $instrument = Instrument::query()
                ->with('questions')
                ->find($instrumentId);

            if (! $instrument) {
                return;
            }

            $expectedQuestions = $instrument->questions->keyBy('id');
            $providedAnswers = collect($this->input('answers', []));
            $providedQuestionIds = $providedAnswers
                ->pluck('question_id')
                ->filter(static fn ($id): bool => is_int($id) || (is_string($id) && is_numeric($id)))
                ->map(static fn ($id): int => (int) $id)
                ->values();

            if ($providedQuestionIds->count() !== $expectedQuestions->count()) {
                $validator->errors()->add('answers', __('api.answers_all_questions_required'));
                return;
            }

            $missing = $expectedQuestions->keys()->diff($providedQuestionIds);
            $extra = $providedQuestionIds->diff($expectedQuestions->keys());
            if ($missing->isNotEmpty() || $extra->isNotEmpty()) {
                $validator->errors()->add('answers', __('api.answers_must_match_instrument_questions'));
            }

            foreach ($providedAnswers as $index => $answer) {
                $questionId = $answer['question_id'] ?? null;
                if (! (is_int($questionId) || (is_string($questionId) && is_numeric($questionId)))) {
                    continue;
                }
                $questionId = (int) $questionId;

                $question = $expectedQuestions->get($questionId);
                if (! $question) {
                    $validator->errors()->add("answers.$index.question_id", __('api.answer_question_invalid_for_instrument'));
                    continue;
                }

                $value = $answer['value'];
                $responseType = $question->response_type;
                if (! $responseType instanceof ResponseType) {
                    $responseType = ResponseType::from((string) $responseType);
                }

                if ($responseType === ResponseType::Scale1To5 && (! is_int($value) || $value < 1 || $value > 5)) {
                    $validator->errors()->add("answers.$index.value", __('api.answer_value_scale_1_5_invalid'));
                }

                if ($responseType === ResponseType::YesNo && ! is_bool($value)) {
                    $validator->errors()->add("answers.$index.value", __('api.answer_value_yes_no_invalid'));
                }

                if ($responseType === ResponseType::FreeText && ! is_string($value)) {
                    $validator->errors()->add("answers.$index.value", __('api.answer_value_free_text_invalid'));
                }
            }
        });
    }
}
