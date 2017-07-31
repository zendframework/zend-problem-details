<?php
/**
 * @see       https://github.com/zendframework/zend-problem-details for the canonical source repository
 * @copyright Copyright (c) 2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-problem-details/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\ProblemDetails;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Zend\ProblemDetails\ProblemDetailsMiddleware;
use Zend\ProblemDetails\ProblemDetailsMiddlewareFactory;
use Zend\ProblemDetails\ProblemDetailsResponseFactory;

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
