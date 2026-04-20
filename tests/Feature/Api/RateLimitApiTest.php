<?php

namespace Tests\Feature\Api;

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
            return Limit::perMinute(60)->by($request->ip() ?? 'unknown-ip');
        });
    }
}
