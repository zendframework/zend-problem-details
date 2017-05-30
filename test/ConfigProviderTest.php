<?php

namespace ProblemDetailsTest;

use PHPUnit\Framework\TestCase;
use ProblemDetails\ConfigProvider;
use ProblemDetails\ProblemDetailsMiddleware;
use ProblemDetails\ProblemDetailsMiddlewareFactory;
use ProblemDetails\ProblemDetailsResponseFactory;
use ProblemDetails\ProblemDetailsResponseFactoryFactory;

class ConfigProviderTest extends TestCase
{
    public function testReturnsExpectedDependencies()
    {
        $provider = new ConfigProvider();
        $config = $provider();

        $this->assertArrayHasKey('dependencies', $config);

        $dependencies = $config['dependencies'];
        $this->assertArrayHasKey('factories', $dependencies);

        $factories = $dependencies['factories'];
        $this->assertCount(2, $factories);
        $this->assertArrayHasKey(ProblemDetailsMiddleware::class, $factories);
        $this->assertArrayHasKey(ProblemDetailsResponseFactory::class, $factories);

        $this->assertSame(
            ProblemDetailsMiddlewareFactory::class,
            $factories[ProblemDetailsMiddleware::class]
        );
        $this->assertSame(
            ProblemDetailsResponseFactoryFactory::class,
            $factories[ProblemDetailsResponseFactory::class]
        );
    }
}
