<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class TryMeApiTest extends TestCase
{
    public function test_try_me_endpoint_returns_localized_teaser_and_chorus(): void
    {
        $response = $this->getJson('/api/try-me');

        $response->assertOk()
            ->assertJsonPath('message', __('api.try_me_teaser'))
            ->assertJsonPath('chorus', __('api.try_me_chorus'));
    }
}
