<?php

namespace Tests\Feature\Api;

use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
    }

    public function test_name_is_required(): void
    {
        $payload = $this->minimalValidPayload();
        unset($payload['name']);

        $this->postJson('/api/patients', $payload)
            ->assertStatus(422)
            ->assertJsonPath('message', __('api.validation_failed'))
            ->assertJsonValidationErrors(['name']);
    }

    public function test_name_cannot_be_empty_string(): void
    {
        $payload = $this->minimalValidPayload();
        $payload['name'] = '';

        $this->postJson('/api/patients', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_name_must_be_string(): void
    {
        $payload = $this->minimalValidPayload();
        $payload['name'] = 12345;

        $this->postJson('/api/patients', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_name_must_not_exceed_255_characters(): void
    {
        $payload = $this->minimalValidPayload();
        $payload['name'] = str_repeat('a', 256);

        $this->postJson('/api/patients', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_date_of_birth_is_required(): void
    {
        $payload = $this->minimalValidPayload();
        unset($payload['date_of_birth']);

        $this->postJson('/api/patients', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date_of_birth']);
    }

    public function test_date_of_birth_must_match_y_m_d_format(): void
    {
        $payload = $this->minimalValidPayload();
        $payload['date_of_birth'] = '20-05-1990';

        $this->postJson('/api/patients', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date_of_birth']);
    }

    public function test_date_of_birth_rejects_invalid_calendar_date(): void
    {
        $payload = $this->minimalValidPayload();
        $payload['date_of_birth'] = '1990-02-31';

        $this->postJson('/api/patients', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date_of_birth']);
    }

    public function test_mrn_is_required(): void
    {
        $payload = $this->minimalValidPayload();
        unset($payload['mrn']);

        $this->postJson('/api/patients', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['mrn']);
    }

    public function test_mrn_cannot_be_empty_string(): void
    {
        $payload = $this->minimalValidPayload();
        $payload['mrn'] = '';

        $this->postJson('/api/patients', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['mrn']);
    }

    public function test_mrn_must_be_string(): void
    {
        $payload = $this->minimalValidPayload();
        $payload['mrn'] = 12345;

        $this->postJson('/api/patients', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['mrn']);
    }

    public function test_mrn_must_not_exceed_255_characters(): void
    {
        $payload = $this->minimalValidPayload();
        $payload['mrn'] = str_repeat('x', 256);

        $this->postJson('/api/patients', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['mrn']);
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
}
