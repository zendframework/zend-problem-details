<?php
/**
 * @see       https://github.com/zendframework/zend-problem-details for the canonical source repository
 * @copyright Copyright (c) 2017-2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-problem-details/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\ProblemDetails;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;
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

    public function testRaisesExceptionWhenProblemDetailsResponseFactoryServiceIsNotAvailable()
    {
        $e = new RuntimeException();
        $this->container->get(ProblemDetailsResponseFactory::class)->willThrow($e);

        $this->expectException(RuntimeException::class);
        $this->factory->__invoke($this->container->reveal());
    }

    public function testCreatesNotFoundHandlerUsingResponseFactoryService() : void
    {
        $responseFactory = $this->prophesize(ProblemDetailsResponseFactory::class)->reveal();
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
