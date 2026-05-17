<?php

namespace Tests\Feature;

use Tests\TestCase;

class DangerousRoutesRemovedTest extends TestCase
{
    public function test_dangerous_routes_are_removed(): void
    {
        $routes = [
            '/migrate',
            '/migrate-fresh',
            '/clear-config',
            '/api/phpinfo',
        ];

        foreach ($routes as $route) {
            $response = $this->get($route);
            $this->assertEquals(
                404,
                $response->status(),
                "Dangerous route {$route} should return 404 but returned {$response->status()}"
            );
        }
    }
}
