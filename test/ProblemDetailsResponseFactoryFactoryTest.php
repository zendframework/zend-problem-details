<?php
/**
 * @see       https://github.com/zendframework/zend-problem-details for the canonical source repository
 * @copyright Copyright (c) 2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-problem-details/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\ProblemDetails;

use Closure;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\Response;
use Zend\ProblemDetails\ProblemDetailsResponseFactory;
use Zend\ProblemDetails\ProblemDetailsResponseFactoryFactory;

class ProblemDetailsResponseFactoryFactoryTest extends TestCase
{
    protected function setUp() : void
    {
        $this->container = $this->prophesize(ContainerInterface::class);
    }

    public function testLackOfOptionalServicesResultsInFactoryUsingDefaults() : void
    {
        $this->container->has('config')->willReturn(false);
        $this->container->has(ResponseInterface::class)->willReturn(false);
        $this->container->has('Zend\ProblemDetails\StreamFactory')->willReturn(false);

        $factoryFactory = new ProblemDetailsResponseFactoryFactory();
        $factory = $factoryFactory($this->container->reveal());

        $this->assertInstanceOf(ProblemDetailsResponseFactory::class, $factory);
        $this->assertAttributeSame(ProblemDetailsResponseFactory::EXCLUDE_THROWABLE_DETAILS, 'isDebug', $factory);
        $this->assertAttributeSame(
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
            'jsonFlags',
            $factory
        );

        $this->assertAttributeInstanceOf(Response::class, 'response', $factory);
        $this->assertAttributeInstanceOf(Closure::class, 'bodyFactory', $factory);
    }

    public function testUsesDebugSettingFromConfigWhenPresent() : void
    {
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn(['debug' => true]);

        $this->container->has(ResponseInterface::class)->willReturn(false);
        $this->container->has('Zend\ProblemDetails\StreamFactory')->willReturn(false);

        $factoryFactory = new ProblemDetailsResponseFactoryFactory();
        $factory = $factoryFactory($this->container->reveal());

        $this->assertInstanceOf(ProblemDetailsResponseFactory::class, $factory);
        $this->assertAttributeSame(ProblemDetailsResponseFactory::INCLUDE_THROWABLE_DETAILS, 'isDebug', $factory);
    }

    public function testUsesJsonFlagsSettingFromConfigWhenPresent() : void
    {
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn(['problem-details' => ['json_flags' => JSON_PRETTY_PRINT]]);

        $this->container->has(ResponseInterface::class)->willReturn(false);
        $this->container->has('Zend\ProblemDetails\StreamFactory')->willReturn(false);

        $factoryFactory = new ProblemDetailsResponseFactoryFactory();
        $factory = $factoryFactory($this->container->reveal());

        $this->assertInstanceOf(ProblemDetailsResponseFactory::class, $factory);
        $this->assertAttributeSame(JSON_PRETTY_PRINT, 'jsonFlags', $factory);
    }

    public function testUsesResponseServiceFromContainerWhenPresent() : void
    {
        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $this->container->has('config')->willReturn(false);
        $this->container->has(ResponseInterface::class)->willReturn(true);
        $this->container->get(ResponseInterface::class)->willReturn($response);
        $this->container->has('Zend\ProblemDetails\StreamFactory')->willReturn(false);

        $factoryFactory = new ProblemDetailsResponseFactoryFactory();
        $factory = $factoryFactory($this->container->reveal());

        $this->assertInstanceOf(ProblemDetailsResponseFactory::class, $factory);
        $this->assertAttributeSame($response, 'response', $factory);
    }

    public function testUsesStreamFactoryServiceFromContainerWhenPresent() : void
    {
        // @codingStandardsIgnoreStart
        $streamFactory = function () { };
        // @codingStandardsIgnoreEnd

        $this->container->has('config')->willReturn(false);
        $this->container->has(ResponseInterface::class)->willReturn(false);
        $this->container->has('Zend\ProblemDetails\StreamFactory')->willReturn(true);
        $this->container->get('Zend\ProblemDetails\StreamFactory')->willReturn($streamFactory);

        $factoryFactory = new ProblemDetailsResponseFactoryFactory();
        $factory = $factoryFactory($this->container->reveal());

        $this->assertInstanceOf(ProblemDetailsResponseFactory::class, $factory);
        $this->assertAttributeSame($streamFactory, 'bodyFactory', $factory);
    }
}
