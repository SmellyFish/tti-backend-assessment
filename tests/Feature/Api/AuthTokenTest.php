<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_issue_token_returns_token_for_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'auth@example.com',
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/auth/token', [
            'email' => 'auth@example.com',
            'password' => 'password',
            'device_name' => 'postman',
        ]);

        $response->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', 'auth@example.com')
            ->assertJsonStructure(['token']);
    }

    public function test_issue_token_returns_401_for_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'auth@example.com',
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/auth/token', [
            'email' => 'auth@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('message', __('api.invalid_credentials'));
    }

    public function test_issue_token_returns_422_for_invalid_payload(): void
    {
        $response = $this->postJson('/api/auth/token', [
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', __('api.validation_failed'))
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_issue_token_requires_email(): void
    {
        $payload = $this->validPayload();
        unset($payload['email']);

        $response = $this->postJson('/api/auth/token', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('message', __('api.validation_failed'))
            ->assertJsonValidationErrors(['email']);
    }

    public function test_issue_token_requires_valid_email_format(): void
    {
        $payload = $this->validPayload();
        $payload['email'] = 'not-an-email';

        $response = $this->postJson('/api/auth/token', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('message', __('api.validation_failed'))
            ->assertJsonValidationErrors(['email']);
    }

    public function test_issue_token_requires_password(): void
    {
        $payload = $this->validPayload();
        unset($payload['password']);

        $response = $this->postJson('/api/auth/token', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('message', __('api.validation_failed'))
            ->assertJsonValidationErrors(['password']);
    }

    public function test_issue_token_password_must_be_string(): void
    {
        $payload = $this->validPayload();
        $payload['password'] = 12345;

        $response = $this->postJson('/api/auth/token', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('message', __('api.validation_failed'))
            ->assertJsonValidationErrors(['password']);
    }

    public function test_issue_token_allows_missing_device_name(): void
    {
        User::factory()->create([
            'email' => 'auth@example.com',
            'password' => 'password',
        ]);

        $payload = $this->validPayload();
        unset($payload['device_name']);

        $response = $this->postJson('/api/auth/token', $payload);

        $response->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonStructure(['token']);
    }

    public function test_issue_token_allows_null_device_name(): void
    {
        User::factory()->create([
            'email' => 'auth@example.com',
            'password' => 'password',
        ]);

        $payload = $this->validPayload();
        $payload['device_name'] = null;

        $response = $this->postJson('/api/auth/token', $payload);

        $response->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonStructure(['token']);
    }

    public function test_issue_token_device_name_must_be_string(): void
    {
        $payload = $this->validPayload();
        $payload['device_name'] = ['tablet'];

        $response = $this->postJson('/api/auth/token', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('message', __('api.validation_failed'))
            ->assertJsonValidationErrors(['device_name']);
    }

    public function test_issue_token_device_name_must_not_exceed_255_characters(): void
    {
        $payload = $this->validPayload();
        $payload['device_name'] = str_repeat('d', 256);

        $response = $this->postJson('/api/auth/token', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('message', __('api.validation_failed'))
            ->assertJsonValidationErrors(['device_name']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'email' => 'auth@example.com',
            'password' => 'password',
            'device_name' => 'postman',
        ];
    }
}
