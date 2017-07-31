<?php

namespace ProblemDetailsTest;

use PHPUnit\Framework\TestCase;
use ProblemDetails\ProblemDetailsMiddleware;
use ProblemDetails\ProblemDetailsMiddlewareFactory;
use ProblemDetails\ProblemDetailsResponseFactory;
use Psr\Container\ContainerInterface;

class ProblemDetailsMiddlewareFactoryTest extends TestCase
{
    protected function setUp() : void
    {
        $this->container = $this->prophesize(ContainerInterface::class);
        $this->factory = new ProblemDetailsMiddlewareFactory();
    }

    public function testCreatesMiddlewareWithoutResponseFactoryIfServiceDoesNotExist() : void
    {
        $this->container->has(ProblemDetailsResponseFactory::class)->willReturn(false);
        $this->container->get(ProblemDetailsResponseFactory::class)->shouldNotBeCalled();

        $middleware = ($this->factory)($this->container->reveal());

        $this->assertInstanceOf(ProblemDetailsMiddleware::class, $middleware);
        $this->assertAttributeInstanceOf(
            ProblemDetailsResponseFactory::class,
            'responseFactory',
            $middleware
        );
    }

    public function testCreatesMiddlewareUsingResponseFactoryService() : void
    {
        $responseFactory = $this->prophesize(ProblemDetailsResponseFactory::class)->reveal();
        $this->container->has(ProblemDetailsResponseFactory::class)->willReturn(true);
        $this->container->get(ProblemDetailsResponseFactory::class)->willReturn($responseFactory);

        $middleware = ($this->factory)($this->container->reveal());

        $this->assertInstanceOf(ProblemDetailsMiddleware::class, $middleware);
        $this->assertAttributeSame(
            $responseFactory,
            'responseFactory',
            $middleware
        );
    }
}
