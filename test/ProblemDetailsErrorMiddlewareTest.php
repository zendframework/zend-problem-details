<?php

namespace ProblemDetailsTest;

use Interop\Http\ServerMiddleware\DelegateInterface;
use PHPUnit\Framework\TestCase;
use ProblemDetails\ProblemDetailsErrorMiddleware;
use ProblemDetails\ProblemDetailsResponse;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ProblemDetailsErrorMiddlewareTest extends TestCase
{
    use ProblemDetailsAssertionsTrait;

    public function testSuccessfulDelegationReturnsDelegateResponse()
    {
        $request  = $this->prophesize(ServerRequestInterface::class)->reveal();
        $response = $this->prophesize(ResponseInterface::class);
        $delegate = $this->prophesize(DelegateInterface::class);
        $delegate
            ->process($request)
            ->will([$response, 'reveal']);


        $middleware = new ProblemDetailsErrorMiddleware();
        $result = $middleware->process($request, $delegate->reveal());

        $this->assertSame($response->reveal(), $result);
    }

    public function testDelegateNotReturningResponseResultsInProblemDetails()
    {
        $request  = $this->prophesize(ServerRequestInterface::class)->reveal();
        $delegate = $this->prophesize(DelegateInterface::class);
        $delegate
            ->process($request)
            ->willReturn('Unexpected');


        $middleware = new ProblemDetailsErrorMiddleware();
        $result = $middleware->process($request, $delegate->reveal());

        $this->assertInstanceOf(ProblemDetailsResponse::class, $result);
        $this->assertEquals(500, $result->getStatusCode());

        $payload = $this->getPayloadFromResponse($result);
        $this->assertProblemDetails([
            'title'  => 'Internal Server Error',
            'detail' => 'Application did not return a response',
            'type'   => 'https://httpstatus.es/500',
        ], $payload);

        $this->assertArrayNotHasKey('exception', $payload);
    }

    public function testThrowableRaisedByDelegateResultsInProblemDetails()
    {
        $request   = $this->prophesize(ServerRequestInterface::class)->reveal();
        $exception = new TestAsset\RuntimeException('Thrown!', 507);
        $delegate  = $this->prophesize(DelegateInterface::class);
        $delegate
            ->process($request)
            ->willThrow($exception);


        $middleware = new ProblemDetailsErrorMiddleware();
        $result = $middleware->process($request, $delegate->reveal());

        $this->assertInstanceOf(ProblemDetailsResponse::class, $result);
        $this->assertEquals(507, $result->getStatusCode());

        $payload = $this->getPayloadFromResponse($result);
        $this->assertProblemDetails([
            'title'  => 'Insufficient Storage',
            'detail' => 'Thrown!',
            'type'   => 'https://httpstatus.es/507',
        ], $payload);

        $this->assertArrayNotHasKey('exception', $payload);
    }

    public function testProblemDetailsContainThrowableDetailsWhenMiddlewareConfiguredToDoSo()
    {
        $request   = $this->prophesize(ServerRequestInterface::class)->reveal();
        $exception = new TestAsset\RuntimeException('Thrown!', 507);
        $delegate  = $this->prophesize(DelegateInterface::class);
        $delegate
            ->process($request)
            ->willThrow($exception);


        $middleware = new ProblemDetailsErrorMiddleware(ProblemDetailsResponse::INCLUDE_THROWABLE_DETAILS);
        $result = $middleware->process($request, $delegate->reveal());

        $this->assertInstanceOf(ProblemDetailsResponse::class, $result);
        $this->assertEquals(507, $result->getStatusCode());

        $payload = $this->getPayloadFromResponse($result);
        $this->assertProblemDetails([
            'title'  => 'Insufficient Storage',
            'detail' => 'Thrown!',
            'type'   => 'https://httpstatus.es/507',
        ], $payload);

        $this->assertArrayHasKey('exception', $payload);
    }

    public function testMiddlewareRegistersErrorHandlerToConvertErrorsToProblemDetails()
    {
        $request  = $this->prophesize(ServerRequestInterface::class)->reveal();
        $delegate = $this->prophesize(DelegateInterface::class);
        $delegate
            ->process($request)
            ->will(function () {
                trigger_error('Triggered error!', \E_USER_ERROR);
            });


        $middleware = new ProblemDetailsErrorMiddleware();
        $result = $middleware->process($request, $delegate->reveal());

        $this->assertInstanceOf(ProblemDetailsResponse::class, $result);
        $this->assertEquals(500, $result->getStatusCode());

        $payload = $this->getPayloadFromResponse($result);
        $this->assertProblemDetails([
            'title'  => 'Internal Server Error',
            'detail' => 'Triggered error!',
            'type'   => 'https://httpstatus.es/500',
        ], $payload);

        $this->assertArrayNotHasKey('exception', $payload);
    }
}
