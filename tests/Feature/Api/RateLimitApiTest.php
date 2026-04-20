<?php

namespace Tests\Feature\Api;

use App\Providers\AppServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RateLimitApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_rate_limiter_returns_429_after_threshold(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(2)->by($request->ip() ?? 'unknown-ip');
        });

        $path = '/api/patients/999999/submissions';

        $this->getJson($path)->assertStatus(404);
        $this->getJson($path)->assertStatus(404);
        $this->getJson($path)->assertStatus(429);

        // Restore production policy for subsequent tests in this process.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(AppServiceProvider::API_RATE_LIMIT_PER_MINUTE)->by($request->ip() ?? 'unknown-ip');
        });
    }

    public function test_local_api_rate_limit_can_be_bypassed_with_env_toggle(): void
    {
        $originalEnv = getenv('API_RATE_LIMIT_ENABLED');
        $this->app->detectEnvironment(fn () => 'local');

        putenv('API_RATE_LIMIT_ENABLED=false');
        $_ENV['API_RATE_LIMIT_ENABLED'] = 'false';
        $_SERVER['API_RATE_LIMIT_ENABLED'] = 'false';

        RateLimiter::for('api', function (Request $request) {
            $rateLimitEnabled = filter_var(env('API_RATE_LIMIT_ENABLED', true), FILTER_VALIDATE_BOOLEAN);
            if (app()->environment('local') && ! $rateLimitEnabled) {
                return Limit::none();
            }

            return Limit::perMinute(2)->by($request->ip() ?? 'unknown-ip');
        });

        $path = '/api/patients/999999/submissions';

        $this->getJson($path)->assertStatus(404);
        $this->getJson($path)->assertStatus(404);
        $this->getJson($path)->assertStatus(404);

        if ($originalEnv === false) {
            putenv('API_RATE_LIMIT_ENABLED');
            unset($_ENV['API_RATE_LIMIT_ENABLED'], $_SERVER['API_RATE_LIMIT_ENABLED']);
        } else {
            putenv("API_RATE_LIMIT_ENABLED={$originalEnv}");
            $_ENV['API_RATE_LIMIT_ENABLED'] = $originalEnv;
            $_SERVER['API_RATE_LIMIT_ENABLED'] = $originalEnv;
        }

        // Restore production policy for subsequent tests in this process.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(AppServiceProvider::API_RATE_LIMIT_PER_MINUTE)->by($request->ip() ?? 'unknown-ip');
        });

        $this->app->detectEnvironment(fn () => 'testing');
    }
}
