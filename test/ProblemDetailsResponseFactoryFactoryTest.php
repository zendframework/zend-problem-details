<?php
/**
 * @see       https://github.com/zendframework/zend-problem-details for the canonical source repository
 * @copyright Copyright (c) 2017 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-problem-details/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\ProblemDetails;

use Closure;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use ReflectionProperty;
use RuntimeException;
use stdClass;
use TypeError;
use Zend\ProblemDetails\ProblemDetailsResponseFactory;
use Zend\ProblemDetails\ProblemDetailsResponseFactoryFactory;

class ProblemDetailsResponseFactoryFactoryTest extends TestCase
{
    protected function setUp() : void
    {
        $this->container = $this->prophesize(ContainerInterface::class);
    }

    public function assertResponseFactoryReturns(ResponseInterface $expected, ProblemDetailsResponseFactory $factory)
    {
        $r = new ReflectionProperty($factory, 'responseFactory');
        $r->setAccessible(true);
        $responseFactory = $r->getValue($factory);

        Assert::assertSame($expected, $responseFactory());
    }

    public function testLackOfResponseServiceResultsInException()
    {
        $factory = new ProblemDetailsResponseFactoryFactory();
        $e = new RuntimeException();

        $this->container->has('config')->willReturn(false);
        $this->container->get('config')->shouldNotBeCalled();
        $this->container->get(ResponseInterface::class)->willThrow($e);

        $this->expectException(RuntimeException::class);
        $factory($this->container->reveal());
    }

    public function testNonCallableResponseServiceResultsInException()
    {
        $factory = new ProblemDetailsResponseFactoryFactory();

        $this->container->has('config')->willReturn(false);
        $this->container->get('config')->shouldNotBeCalled();
        $this->container->get(ResponseInterface::class)->willReturn(new stdClass);

        $this->expectException(TypeError::class);
        $factory($this->container->reveal());
    }

    public function testLackOfConfigServiceResultsInFactoryUsingDefaults() : void
    {
        $this->container->has('config')->willReturn(false);

        $response = $this->prophesize(ResponseInterface::class)->reveal();
        $this->container->get(ResponseInterface::class)->willReturn(function () use ($response) {
            return $response;
        });

        $factoryFactory = new ProblemDetailsResponseFactoryFactory();
        $factory = $factoryFactory($this->container->reveal());

        $this->assertInstanceOf(ProblemDetailsResponseFactory::class, $factory);
        $this->assertAttributeSame(ProblemDetailsResponseFactory::EXCLUDE_THROWABLE_DETAILS, 'isDebug', $factory);
        $this->assertAttributeSame(
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
            'jsonFlags',
            $factory
        );

        $this->assertAttributeInstanceOf(Closure::class, 'responseFactory', $factory);
        $this->assertResponseFactoryReturns($response, $factory);
    }

    public function testUsesDebugSettingFromConfigWhenPresent() : void
    {
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn(['debug' => true]);

        $this->container->get(ResponseInterface::class)->willReturn(function () {
        });

        $factoryFactory = new ProblemDetailsResponseFactoryFactory();
        $factory = $factoryFactory($this->container->reveal());

        $this->assertInstanceOf(ProblemDetailsResponseFactory::class, $factory);
        $this->assertAttributeSame(ProblemDetailsResponseFactory::INCLUDE_THROWABLE_DETAILS, 'isDebug', $factory);
        $this->assertAttributeSame(true, 'exceptionDetailsInResponse', $factory);
    }

    public function testUsesJsonFlagsSettingFromConfigWhenPresent() : void
    {
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn(['problem-details' => ['json_flags' => JSON_PRETTY_PRINT]]);

        $this->container->get(ResponseInterface::class)->willReturn(function () {
        });

        $factoryFactory = new ProblemDetailsResponseFactoryFactory();
        $factory = $factoryFactory($this->container->reveal());

        $this->assertInstanceOf(ProblemDetailsResponseFactory::class, $factory);
        $this->assertAttributeSame(JSON_PRETTY_PRINT, 'jsonFlags', $factory);
    }
}
