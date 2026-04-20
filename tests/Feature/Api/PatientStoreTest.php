<?php

namespace Tests\Feature\Api;

use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PatientStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_patient_returns_201_and_body(): void
    {
        $response = $this->postJson('/api/patients', [
            'name' => 'Alex Chen',
            'date_of_birth' => '1990-05-20',
            'mrn' => 'MRN-API-001',
        ]);

        $response->assertCreated()
            ->assertJsonPath('name', 'Alex Chen')
            ->assertJsonPath('mrn', 'MRN-API-001')
            ->assertJsonPath('date_of_birth', '1990-05-20');
        $this->assertMatchesOpenApiContract($response, 'POST', '/api/patients');

        $this->assertDatabaseHas('patients', ['mrn' => 'MRN-API-001']);
    }

    public function test_store_patient_validation_error_shape(): void
    {
        $response = $this->postJson('/api/patients', [
            'name' => '',
            'date_of_birth' => 'not-a-date',
            'mrn' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', __('api.validation_failed'))
            ->assertJsonStructure(['errors']);
        $this->assertMatchesOpenApiContract($response, 'POST', '/api/patients');
    }

    #[DataProvider('invalidPatientPayloadProvider')]
    public function test_patient_store_validation_rules(callable $mutatePayload, array $expectedErrors): void
    {
        $payload = $this->minimalValidPayload();
        $mutatePayload($payload);

        $this->postJson('/api/patients', $payload)
            ->assertStatus(422)
            ->assertJsonPath('message', __('api.validation_failed'))
            ->assertJsonValidationErrors($expectedErrors);
    }

    public function test_mrn_must_be_unique(): void
    {
        Patient::query()->create([
            'name' => 'Existing',
            'date_of_birth' => '1980-01-01',
            'mrn' => 'MRN-DUPLICATE',
        ]);

        $payload = $this->minimalValidPayload();
        $payload['mrn'] = 'MRN-DUPLICATE';

        $this->postJson('/api/patients', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['mrn']);
    }

    public function test_store_patient_round_trips_utf8_strings_in_response_and_database(): void
    {
        $payload = [
            'name' => 'Мария 李 😊',
            'date_of_birth' => '1992-11-03',
            'mrn' => 'MRN-患者-🌍-001',
        ];

        $response = $this->postJson('/api/patients', $payload);

        $response->assertCreated()
            ->assertJsonPath('name', $payload['name'])
            ->assertJsonPath('mrn', $payload['mrn']);

        $this->assertDatabaseHas('patients', [
            'name' => $payload['name'],
            'mrn' => $payload['mrn'],
        ]);
    }

    public function test_store_patient_accepts_255_multibyte_characters_for_name_and_mrn(): void
    {
        $payload = $this->minimalValidPayload();
        $payload['name'] = str_repeat('界', 255);
        $payload['mrn'] = str_repeat('測', 255);

        $this->postJson('/api/patients', $payload)
            ->assertCreated()
            ->assertJsonPath('name', $payload['name'])
            ->assertJsonPath('mrn', $payload['mrn']);
    }

    public function test_store_patient_rejects_256_multibyte_characters_for_name_and_mrn(): void
    {
        $payload = $this->minimalValidPayload();
        $payload['name'] = str_repeat('界', 256);
        $payload['mrn'] = str_repeat('測', 256);

        $this->postJson('/api/patients', $payload)
            ->assertStatus(422)
            ->assertJsonPath('message', __('api.validation_failed'))
            ->assertJsonValidationErrors(['name', 'mrn']);
    }

    public function test_store_patient_rejects_malformed_utf16_declared_json_payload(): void
    {
        $response = $this->call(
            'POST',
            '/api/patients',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json; charset=UTF-16',
                'HTTP_ACCEPT' => 'application/json',
            ],
            "\xFF\xFE{\x00\"\x00n\x00a\x00m\x00e\x00\"\x00:\x00}",
        );

        $this->assertContains($response->getStatusCode(), [400, 422]);
    }

    /**
     * @return array<string, mixed>
     */
    private function minimalValidPayload(): array
    {
        return [
            'name' => 'Jordan Test',
            'date_of_birth' => '1988-03-15',
            'mrn' => 'MRN-UNIQUE-'.uniqid(),
        ];
    }

    /**
     * @return array<string, array{callable(array<string, mixed>): void, array<int, string>}>
     */
    public static function invalidPatientPayloadProvider(): array
    {
        return [
            'name required' => [
                static function (array &$payload): void {
                    unset($payload['name']);
                },
                ['name'],
            ],
            'name empty string' => [
                static function (array &$payload): void {
                    $payload['name'] = '';
                },
                ['name'],
            ],
            'name must be string' => [
                static function (array &$payload): void {
                    $payload['name'] = 12345;
                },
                ['name'],
            ],
            'name max 255' => [
                static function (array &$payload): void {
                    $payload['name'] = str_repeat('a', 256);
                },
                ['name'],
            ],
            'date of birth required' => [
                static function (array &$payload): void {
                    unset($payload['date_of_birth']);
                },
                ['date_of_birth'],
            ],
            'date of birth format' => [
                static function (array &$payload): void {
                    $payload['date_of_birth'] = '20-05-1990';
                },
                ['date_of_birth'],
            ],
            'date of birth invalid calendar date' => [
                static function (array &$payload): void {
                    $payload['date_of_birth'] = '1990-02-31';
                },
                ['date_of_birth'],
            ],
            'mrn required' => [
                static function (array &$payload): void {
                    unset($payload['mrn']);
                },
                ['mrn'],
            ],
            'mrn empty string' => [
                static function (array &$payload): void {
                    $payload['mrn'] = '';
                },
                ['mrn'],
            ],
            'mrn must be string' => [
                static function (array &$payload): void {
                    $payload['mrn'] = 12345;
                },
                ['mrn'],
            ],
            'mrn max 255' => [
                static function (array &$payload): void {
                    $payload['mrn'] = str_repeat('x', 256);
                },
                ['mrn'],
            ],
        ];
    }
}
