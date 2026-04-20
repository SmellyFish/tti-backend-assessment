<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[DataProvider('invalidInstrumentPayloadProvider')]
    public function test_instrument_store_validation_rules(callable $mutatePayload, array $expectedErrors): void
    {
        $payload = $this->minimalValidPayload();
        $mutatePayload($payload);

        $this->postJson('/api/instruments', $payload)
            ->assertStatus(422)
            ->assertJsonPath('message', __('api.validation_failed'))
            ->assertJsonValidationErrors($expectedErrors);
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

    /**
     * @return array<string, array{callable(array<string, mixed>): void, array<int, string>}>
     */
    public static function invalidInstrumentPayloadProvider(): array
    {
        return [
            'title required' => [
                static function (array &$payload): void {
                    unset($payload['title']);
                },
                ['title'],
            ],
            'title max 255' => [
                static function (array &$payload): void {
                    $payload['title'] = str_repeat('a', 256);
                },
                ['title'],
            ],
            'description must be string' => [
                static function (array &$payload): void {
                    $payload['description'] = ['not-a-string'];
                },
                ['description'],
            ],
            'questions required' => [
                static function (array &$payload): void {
                    unset($payload['questions']);
                },
                ['questions'],
            ],
            'questions must be array' => [
                static function (array &$payload): void {
                    $payload['questions'] = 'not-an-array';
                },
                ['questions'],
            ],
            'questions min items' => [
                static function (array &$payload): void {
                    $payload['questions'] = [];
                },
                ['questions'],
            ],
            'question prompt required' => [
                static function (array &$payload): void {
                    $payload['questions'][0] = [
                        'response_type' => 'yes_no',
                        'sort_order' => 0,
                    ];
                },
                ['questions.0.prompt'],
            ],
            'question prompt not empty' => [
                static function (array &$payload): void {
                    $payload['questions'][0]['prompt'] = '';
                },
                ['questions.0.prompt'],
            ],
            'question prompt must be string' => [
                static function (array &$payload): void {
                    $payload['questions'][0]['prompt'] = 999;
                },
                ['questions.0.prompt'],
            ],
            'question response type required' => [
                static function (array &$payload): void {
                    unset($payload['questions'][0]['response_type']);
                },
                ['questions.0.response_type'],
            ],
            'question response type enum' => [
                static function (array &$payload): void {
                    $payload['questions'][0]['response_type'] = 'not_a_valid_type';
                },
                ['questions.0.response_type'],
            ],
            'question sort order required' => [
                static function (array &$payload): void {
                    unset($payload['questions'][0]['sort_order']);
                },
                ['questions.0.sort_order'],
            ],
            'question sort order integer' => [
                static function (array &$payload): void {
                    $payload['questions'][0]['sort_order'] = 'not-integer';
                },
                ['questions.0.sort_order'],
            ],
            'question sort order min zero' => [
                static function (array &$payload): void {
                    $payload['questions'][0]['sort_order'] = -1;
                },
                ['questions.0.sort_order'],
            ],
        ];
    }
}
