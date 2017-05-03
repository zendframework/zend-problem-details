<?php

namespace ProblemDetailsTest;

use Psr\Container\ContainerInterface;
use PHPUnit\Framework\TestCase;
use ProblemDetails\ProblemDetailsMiddleware;
use ProblemDetails\ProblemDetailsMiddlewareFactory;
use ProblemDetails\ProblemDetailsResponse;

class ProblemDetailsMiddlewareFactoryTest extends TestCase
{
    protected function setUp()
    {
        $this->container = $this->prophesize(ContainerInterface::class);
        $this->factory = new ProblemDetailsMiddlewareFactory();
    }

    public function testCreatesMiddlewareUsingFalseForIncludeThrowableDetailFlagInAbsenceOfConfigService()
    {
        $this->container->has('config')->willReturn(false);

        $middleware = ($this->factory)($this->container->reveal());
        $this->assertInstanceOf(ProblemDetailsMiddleware::class, $middleware);
        $this->assertAttributeSame(
            ProblemDetailsResponse::EXCLUDE_THROWABLE_DETAILS,
            'includeThrowableDetail',
            $middleware
        );
    }

    public function testCreatesMiddlewareUsingFalseForIncludeThrowableDetailFlagIfDebugConfigFlagIsMissing()
    {
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn([]);

        $middleware = ($this->factory)($this->container->reveal());
        $this->assertInstanceOf(ProblemDetailsMiddleware::class, $middleware);
        $this->assertAttributeSame(
            ProblemDetailsResponse::EXCLUDE_THROWABLE_DETAILS,
            'includeThrowableDetail',
            $middleware
        );
    }

    public function debugFlags()
    {
        return [
            'enabled'  => [true],
            'disabled' => [false],
        ];
    }

    /**
     * @dataProvider debugFlags
     */
    public function testCreatesMiddlewareUsingDebugConfigFlagWhenPresent($flag)
    {
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn(['debug' => $flag]);

        $middleware = ($this->factory)($this->container->reveal());
        $this->assertInstanceOf(ProblemDetailsMiddleware::class, $middleware);
        $this->assertAttributeSame(
            $flag,
            'includeThrowableDetail',
            $middleware
        );
    }
}
