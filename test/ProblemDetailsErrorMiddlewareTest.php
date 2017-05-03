<?php

namespace ProblemDetailsTest;

use Interop\Http\ServerMiddleware\DelegateInterface;
use PHPUnit\Framework\TestCase;
use ProblemDetails\ProblemDetailsErrorMiddleware;
use ProblemDetails\ProblemDetailsJsonResponse;
use ProblemDetails\ProblemDetailsXmlResponse;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ProblemDetailsErrorMiddlewareTest extends TestCase
{
    use ProblemDetailsAssertionsTrait;

    protected function setUp()
    {
        $this->request = $this->prophesize(ServerRequestInterface::class);
    }

    public function acceptHeaders()
    {
        return [
            'application/xml'          => ['application/xml', ProblemDetailsXmlResponse::class],
            'application/vnd.api+xml'  => ['application/vnd.api+xml', ProblemDetailsXmlResponse::class],
            'application/json'         => ['application/json', ProblemDetailsJsonResponse::class],
            'application/vnd.api+json' => ['application/vnd.api+json', ProblemDetailsJsonResponse::class],
        ];
    }

    public function testSuccessfulDelegationReturnsDelegateResponse()
    {
        $response = $this->prophesize(ResponseInterface::class);
        $delegate = $this->prophesize(DelegateInterface::class);
        $delegate
            ->process(Argument::that([$this->request, 'reveal']))
            ->will([$response, 'reveal']);


        $middleware = new ProblemDetailsErrorMiddleware();
        $result = $middleware->process($this->request->reveal(), $delegate->reveal());

        $this->assertSame($response->reveal(), $result);
    }

    /**
     * @dataProvider acceptHeaders
     */
    public function testDelegateNotReturningResponseResultsInProblemDetails(string $accept, string $expectedType)
    {
        $this->request->getHeaderLine('Accept')->willReturn($accept);
        $delegate = $this->prophesize(DelegateInterface::class);
        $delegate
            ->process(Argument::that([$this->request, 'reveal']))
            ->willReturn('Unexpected');


        $middleware = new ProblemDetailsErrorMiddleware();
        $result = $middleware->process($this->request->reveal(), $delegate->reveal());

        $this->assertInstanceOf($expectedType, $result);
        $this->assertEquals(500, $result->getStatusCode());

        $payload = $this->getPayloadFromResponse($result);
        $this->assertProblemDetails([
            'title'  => 'Internal Server Error',
            'detail' => 'Application did not return a response',
            'type'   => 'https://httpstatus.es/500',
        ], $payload);

        $this->assertArrayNotHasKey('exception', $payload);
    }

    /**
     * @dataProvider acceptHeaders
     */
    public function testThrowableRaisedByDelegateResultsInProblemDetails(string $accept, string $expectedType)
    {
        $this->request->getHeaderLine('Accept')->willReturn($accept);
        $exception = new TestAsset\RuntimeException('Thrown!', 507);
        $delegate  = $this->prophesize(DelegateInterface::class);
        $delegate
            ->process(Argument::that([$this->request, 'reveal']))
            ->willThrow($exception);


        $middleware = new ProblemDetailsErrorMiddleware();
        $result = $middleware->process($this->request->reveal(), $delegate->reveal());

        $this->assertInstanceOf($expectedType, $result);
        $this->assertEquals(507, $result->getStatusCode());

        $payload = $this->getPayloadFromResponse($result);
        $this->assertProblemDetails([
            'title'  => 'Insufficient Storage',
            'detail' => 'Thrown!',
            'type'   => 'https://httpstatus.es/507',
        ], $payload);

        $this->assertArrayNotHasKey('exception', $payload);
    }

    /**
     * @dataProvider acceptHeaders
     */
    public function testProblemDetailsContainThrowableDetailsWhenMiddlewareConfiguredToDoSo(
        string $accept,
        string $expectedType
    ) {
        $this->request->getHeaderLine('Accept')->willReturn($accept);
        $exception = new TestAsset\RuntimeException('Thrown!', 507);
        $delegate  = $this->prophesize(DelegateInterface::class);
        $delegate
            ->process(Argument::that([$this->request, 'reveal']))
            ->willThrow($exception);


        $middleware = new ProblemDetailsErrorMiddleware(ProblemDetailsJsonResponse::INCLUDE_THROWABLE_DETAILS);
        $result = $middleware->process($this->request->reveal(), $delegate->reveal());

        $this->assertInstanceOf($expectedType, $result);
        $this->assertEquals(507, $result->getStatusCode());

        $payload = $this->getPayloadFromResponse($result);
        $this->assertProblemDetails([
            'title'  => 'Insufficient Storage',
            'detail' => 'Thrown!',
            'type'   => 'https://httpstatus.es/507',
        ], $payload);

        $this->assertArrayHasKey('exception', $payload);
    }

    /**
     * @dataProvider acceptHeaders
     */
    public function testMiddlewareRegistersErrorHandlerToConvertErrorsToProblemDetails(
        string $accept,
        string $expectedType
    ) {
        $this->request->getHeaderLine('Accept')->willReturn($accept);
        $delegate = $this->prophesize(DelegateInterface::class);
        $delegate
            ->process(Argument::that([$this->request, 'reveal']))
            ->will(function () {
                trigger_error('Triggered error!', \E_USER_ERROR);
            });


        $middleware = new ProblemDetailsErrorMiddleware();
        $result = $middleware->process($this->request->reveal(), $delegate->reveal());

        $this->assertInstanceOf($expectedType, $result);
        $this->assertEquals(500, $result->getStatusCode());

        $payload = $this->getPayloadFromResponse($result);
        $this->assertProblemDetails([
            'title'  => 'Internal Server Error',
            'detail' => 'Triggered error!',
            'type'   => 'https://httpstatus.es/500',
        ], $payload);

        $this->assertArrayNotHasKey('exception', $payload);
    }

    public function testRethrowsCaughtExceptionIfUnableToNegotiateAcceptHeader()
    {
        $this->request->getHeaderLine('Accept')->willReturn('text/html');
        $exception = new TestAsset\RuntimeException('Thrown!', 507);
        $delegate  = $this->prophesize(DelegateInterface::class);
        $delegate
            ->process(Argument::that([$this->request, 'reveal']))
            ->willThrow($exception);


        $middleware = new ProblemDetailsErrorMiddleware();

        $this->expectException(TestAsset\RuntimeException::class);
        $this->expectExceptionMessage('Thrown!');
        $this->expectExceptionCode(507);
        $result = $middleware->process($this->request->reveal(), $delegate->reveal());
    }
}
