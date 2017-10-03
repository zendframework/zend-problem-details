<?php
/**
 * @see       https://github.com/zendframework/zend-problem-details for the canonical source repository
 * @copyright Copyright (c) 2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-problem-details/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\ProblemDetails;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Zend\ProblemDetails\ProblemDetailsNotFoundHandler;
use Zend\ProblemDetails\ProblemDetailsNotFoundHandlerFactory;
use Zend\ProblemDetails\ProblemDetailsResponseFactory;

class ProblemDetailsNotFoundHandlerFactoryTest extends TestCase
{
    protected function setUp() : void
    {
        $this->container = $this->prophesize(ContainerInterface::class);
        $this->factory = new ProblemDetailsNotFoundHandlerFactory();
    }

    public function testCreatesNotFoundHandlerWithoutResponseFactoryIfServiceDoesNotExist() : void
    {
        $this->container->has(ProblemDetailsResponseFactory::class)->willReturn(false);
        $this->container->get(ProblemDetailsResponseFactory::class)->shouldNotBeCalled();

        $notFoundHandler = ($this->factory)($this->container->reveal());

        $this->assertInstanceOf(ProblemDetailsNotFoundHandler::class, $notFoundHandler);
        $this->assertAttributeInstanceOf(
            ProblemDetailsResponseFactory::class,
            'responseFactory',
            $notFoundHandler
        );
    }

    public function testCreatesNotFoundHandlerUsingResponseFactoryService() : void
    {
        $responseFactory = $this->prophesize(ProblemDetailsResponseFactory::class)->reveal();
        $this->container->has(ProblemDetailsResponseFactory::class)->willReturn(true);
        $this->container->get(ProblemDetailsResponseFactory::class)->willReturn($responseFactory);

        $notFoundHandler = ($this->factory)($this->container->reveal());

        $this->assertInstanceOf(ProblemDetailsNotFoundHandler::class, $notFoundHandler);
        $this->assertAttributeSame(
            $responseFactory,
            'responseFactory',
            $notFoundHandler
        );
    }
}
