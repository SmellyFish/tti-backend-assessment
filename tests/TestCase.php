<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\OpenApiContractAsserts;

abstract class TestCase extends BaseTestCase
{
    use OpenApiContractAsserts;
}
