<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
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
        $this->assertMatchesOpenApiContract($response, 'POST', '/api/auth/token');
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
        $this->assertMatchesOpenApiContract($response, 'POST', '/api/auth/token');
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

    #[DataProvider('invalidAuthTokenPayloadProvider')]
    public function test_issue_token_validation_rules(callable $mutatePayload, array $expectedErrors): void
    {
        $payload = $this->validPayload();
        $mutatePayload($payload);

        $response = $this->postJson('/api/auth/token', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('message', __('api.validation_failed'))
            ->assertJsonValidationErrors($expectedErrors);
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

    #[DataProvider('invalidAuthTokenDeviceNameProvider')]
    public function test_issue_token_device_name_validation(callable $mutatePayload): void
    {
        $payload = $this->validPayload();
        $mutatePayload($payload);

        $response = $this->postJson('/api/auth/token', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('message', __('api.validation_failed'))
            ->assertJsonValidationErrors(['device_name']);
    }

    public function test_auth_test_endpoint_requires_sanctum_token(): void
    {
        $response = $this->getJson('/api/auth-test');

        $response->assertStatus(401);
    }

    public function test_auth_test_endpoint_returns_success_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-device')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/auth-test');

        $response->assertOk()
            ->assertJsonPath('message', 'Authenticated');
    }

    public function test_core_pro_route_remains_public_without_sanctum_token(): void
    {
        $response = $this->postJson('/api/patients', [
            'name' => 'Public Route Patient',
            'date_of_birth' => '1991-04-20',
            'mrn' => 'MRN-PUBLIC-'.uniqid(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('name', 'Public Route Patient');
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

    /**
     * @return array<string, array{callable(array<string, mixed>): void, array<int, string>}>
     */
    public static function invalidAuthTokenPayloadProvider(): array
    {
        return [
            'email required' => [
                static function (array &$payload): void {
                    unset($payload['email']);
                },
                ['email'],
            ],
            'email format' => [
                static function (array &$payload): void {
                    $payload['email'] = 'not-an-email';
                },
                ['email'],
            ],
            'password required' => [
                static function (array &$payload): void {
                    unset($payload['password']);
                },
                ['password'],
            ],
            'password string' => [
                static function (array &$payload): void {
                    $payload['password'] = 12345;
                },
                ['password'],
            ],
        ];
    }

    /**
     * @return array<string, array{callable(array<string, mixed>): void}>
     */
    public static function invalidAuthTokenDeviceNameProvider(): array
    {
        return [
            'device name must be string' => [
                static function (array &$payload): void {
                    $payload['device_name'] = ['tablet'];
                },
            ],
            'device name max 255' => [
                static function (array &$payload): void {
                    $payload['device_name'] = str_repeat('d', 256);
                },
            ],
        ];
    }
}
