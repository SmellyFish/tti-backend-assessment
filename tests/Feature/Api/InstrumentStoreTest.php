<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstrumentStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_instrument_creates_questions(): void
    {
        $response = $this->postJson('/api/instruments', [
            'title' => 'Daily check-in',
            'description' => 'Quick questions',
            'questions' => [
                [
                    'prompt' => 'Rate your mood',
                    'response_type' => 'scale_1_5',
                    'sort_order' => 1,
                ],
                [
                    'prompt' => 'Any notes?',
                    'response_type' => 'free_text',
                    'sort_order' => 2,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('title', 'Daily check-in')
            ->assertJsonCount(2, 'questions');

        $this->assertDatabaseHas('instruments', ['title' => 'Daily check-in']);
        $this->assertDatabaseCount('questions', 2);
    }

    public function test_store_instrument_without_description_succeeds(): void
    {
        $response = $this->postJson('/api/instruments', $this->minimalValidPayload());

        $response->assertCreated()
            ->assertJsonPath('title', 'Minimal instrument')
            ->assertJsonPath('description', null);
    }

    public function test_title_is_required(): void
    {
        $payload = $this->minimalValidPayload();
        unset($payload['title']);

        $this->postJson('/api/instruments', $payload)
            ->assertStatus(422)
            ->assertJsonPath('message', __('api.validation_failed'))
            ->assertJsonValidationErrors(['title']);
    }

    public function test_title_must_not_exceed_255_characters(): void
    {
        $payload = $this->minimalValidPayload();
        $payload['title'] = str_repeat('a', 256);

        $this->postJson('/api/instruments', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    public function test_description_must_be_string_when_present(): void
    {
        $payload = $this->minimalValidPayload();
        $payload['description'] = ['not-a-string'];

        $this->postJson('/api/instruments', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['description']);
    }

    public function test_questions_is_required(): void
    {
        $payload = $this->minimalValidPayload();
        unset($payload['questions']);

        $this->postJson('/api/instruments', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['questions']);
    }

    public function test_questions_must_be_an_array(): void
    {
        $payload = $this->minimalValidPayload();
        $payload['questions'] = 'not-an-array';

        $this->postJson('/api/instruments', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['questions']);
    }

    public function test_questions_must_have_at_least_one_item(): void
    {
        $payload = $this->minimalValidPayload();
        $payload['questions'] = [];

        $this->postJson('/api/instruments', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['questions']);
    }

    public function test_each_question_requires_prompt(): void
    {
        $payload = $this->minimalValidPayload();
        $payload['questions'][0] = [
            'response_type' => 'yes_no',
            'sort_order' => 0,
        ];

        $this->postJson('/api/instruments', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['questions.0.prompt']);
    }

    public function test_each_question_prompt_cannot_be_empty_string(): void
    {
        $payload = $this->minimalValidPayload();
        $payload['questions'][0]['prompt'] = '';

        $this->postJson('/api/instruments', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['questions.0.prompt']);
    }

    public function test_each_question_prompt_must_be_string(): void
    {
        $payload = $this->minimalValidPayload();
        $payload['questions'][0]['prompt'] = 999;

        $this->postJson('/api/instruments', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['questions.0.prompt']);
    }

    public function test_each_question_requires_response_type(): void
    {
        $payload = $this->minimalValidPayload();
        unset($payload['questions'][0]['response_type']);

        $this->postJson('/api/instruments', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['questions.0.response_type']);
    }

    public function test_each_question_response_type_must_be_valid_enum(): void
    {
        $payload = $this->minimalValidPayload();
        $payload['questions'][0]['response_type'] = 'not_a_valid_type';

        $this->postJson('/api/instruments', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['questions.0.response_type']);
    }

    public function test_each_question_requires_sort_order(): void
    {
        $payload = $this->minimalValidPayload();
        unset($payload['questions'][0]['sort_order']);

        $this->postJson('/api/instruments', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['questions.0.sort_order']);
    }

    public function test_each_question_sort_order_must_be_integer(): void
    {
        $payload = $this->minimalValidPayload();
        $payload['questions'][0]['sort_order'] = 'not-integer';

        $this->postJson('/api/instruments', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['questions.0.sort_order']);
    }

    public function test_each_question_sort_order_must_be_at_least_zero(): void
    {
        $payload = $this->minimalValidPayload();
        $payload['questions'][0]['sort_order'] = -1;

        $this->postJson('/api/instruments', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['questions.0.sort_order']);
    }

    public function test_sort_order_zero_is_accepted(): void
    {
        $payload = $this->minimalValidPayload();
        $payload['questions'][0]['sort_order'] = 0;

        $response = $this->postJson('/api/instruments', $payload);

        $response->assertCreated()
            ->assertJsonPath('questions.0.sort_order', 0);
    }

    public function test_store_instrument_round_trips_utf8_fields_in_response_and_database(): void
    {
        $payload = [
            'title' => '日次チェック 🩺',
            'description' => 'Обратная связь للمريض',
            'questions' => [
                [
                    'prompt' => '最近の体調はどうですか؟ 😊',
                    'response_type' => 'free_text',
                    'sort_order' => 1,
                ],
            ],
        ];

        $response = $this->postJson('/api/instruments', $payload);

        $response->assertCreated()
            ->assertJsonPath('title', $payload['title'])
            ->assertJsonPath('description', $payload['description'])
            ->assertJsonPath('questions.0.prompt', $payload['questions'][0]['prompt']);

        $this->assertDatabaseHas('instruments', [
            'title' => $payload['title'],
            'description' => $payload['description'],
        ]);
    }

    public function test_title_accepts_255_multibyte_characters(): void
    {
        $payload = $this->minimalValidPayload();
        $payload['title'] = str_repeat('界', 255);

        $this->postJson('/api/instruments', $payload)
            ->assertCreated()
            ->assertJsonPath('title', $payload['title']);
    }

    public function test_title_rejects_256_multibyte_characters(): void
    {
        $payload = $this->minimalValidPayload();
        $payload['title'] = str_repeat('界', 256);

        $this->postJson('/api/instruments', $payload)
            ->assertStatus(422)
            ->assertJsonPath('message', __('api.validation_failed'))
            ->assertJsonValidationErrors(['title']);
    }

    /**
     * @return array<string, mixed>
     */
    private function minimalValidPayload(): array
    {
        return [
            'title' => 'Minimal instrument',
            'questions' => [
                [
                    'prompt' => 'A question',
                    'response_type' => 'yes_no',
                    'sort_order' => 1,
                ],
            ],
        ];
    }
}
