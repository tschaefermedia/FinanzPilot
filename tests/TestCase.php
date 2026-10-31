<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        // The whole app sits behind login; tests of the login itself call Auth::forgetGuards().
        $this->actingAs(User::factory()->make());
    }
}
