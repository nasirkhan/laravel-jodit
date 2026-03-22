<?php

namespace Nasirkhan\LaravelJodit\Tests\Feature;

use Nasirkhan\LaravelJodit\Tests\TestCase;

class JoditConnectorRateLimitTest extends TestCase
{
    public function test_throttle_middleware_is_in_default_config(): void
    {
        $middleware = config('jodit.route.middleware');

        $this->assertContains('throttle:60,1', $middleware);
    }

    public function test_connector_route_applies_throttle_middleware(): void
    {
        $route = app('router')->getRoutes()->getByName('jodit.connector');

        $this->assertNotNull($route, 'jodit.connector route must exist');
        $this->assertContains('throttle:60,1', $route->middleware());
    }
}
