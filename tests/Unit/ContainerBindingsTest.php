<?php

namespace Tests\Unit;

use App\Providers\AppServiceProvider;
use Tests\TestCase;

class ContainerBindingsTest extends TestCase
{
    public function test_every_registered_binding_resolves_to_its_concrete_class(): void
    {
        $bindings = (new AppServiceProvider($this->app))->singletons;

        foreach ($bindings as $abstract => $concrete) {
            $this->assertTrue(interface_exists($abstract), "{$abstract} is not an interface");
            $this->assertInstanceOf($abstract, $this->app->make($abstract));
            $this->assertInstanceOf($concrete, $this->app->make($abstract));
            $this->assertSame($this->app->make($abstract), $this->app->make($abstract), "{$abstract} is not a singleton");
        }

        $this->addToAssertionCount(1);
    }
}
