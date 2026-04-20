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
}
