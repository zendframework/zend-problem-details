<?php

namespace ProblemDetailsTest;

use ErrorException;
use Interop\Http\ServerMiddleware\DelegateInterface;
use PHPUnit\Framework\TestCase;
use ProblemDetails\MissingResponseException;
use ProblemDetails\ProblemDetailsMiddleware;
use ProblemDetails\ProblemDetailsResponseFactory;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ProblemDetailsMiddlewareTest extends TestCase
{
    use ProblemDetailsAssertionsTrait;

    protected function setUp()
    {
        $this->request = $this->prophesize(ServerRequestInterface::class);
        $this->responseFactory = $this->prophesize(ProblemDetailsResponseFactory::class);
        $this->middleware = new ProblemDetailsMiddleware($this->responseFactory->reveal());
    }

    public function acceptHeaders()
    {
        return [
            'empty'                    => [''],
            'application/xml'          => ['application/xml'],
            'application/vnd.api+xml'  => ['application/vnd.api+xml'],
            'application/json'         => ['application/json'],
            'application/vnd.api+json' => ['application/vnd.api+json'],
        ];
    }

    public function testSuccessfulDelegationReturnsDelegateResponse()
    {
        $response = $this->prophesize(ResponseInterface::class);
        $delegate = $this->prophesize(DelegateInterface::class);
        $delegate
            ->process(Argument::that([$this->request, 'reveal']))
            ->will([$response, 'reveal']);


        $middleware = new ProblemDetailsMiddleware();
        $result = $middleware->process($this->request->reveal(), $delegate->reveal());

        $this->assertSame($response->reveal(), $result);
    }

    /**
     * @dataProvider acceptHeaders
     */
    public function testDelegateNotReturningResponseResultsInProblemDetails(string $accept)
    {
        $this->request->getHeaderLine('Accept')->willReturn($accept);

        $delegate = $this->prophesize(DelegateInterface::class);
        $delegate
            ->process(Argument::that([$this->request, 'reveal']))
            ->willReturn('Unexpected');

        $expected = $this->prophesize(ResponseInterface::class)->reveal();
        $this->responseFactory
            ->createResponseFromThrowable($this->request->reveal(), Argument::type(MissingResponseException::class))
            ->willReturn($expected);

        $result = $this->middleware->process($this->request->reveal(), $delegate->reveal());

        $this->assertSame($expected, $result);
    }

    /**
     * @dataProvider acceptHeaders
     */
    public function testThrowableRaisedByDelegateResultsInProblemDetails(string $accept)
    {
        $this->request->getHeaderLine('Accept')->willReturn($accept);

        $exception = new TestAsset\RuntimeException('Thrown!', 507);

        $delegate  = $this->prophesize(DelegateInterface::class);
        $delegate
            ->process(Argument::that([$this->request, 'reveal']))
            ->willThrow($exception);

        $expected = $this->prophesize(ResponseInterface::class)->reveal();
        $this->responseFactory
            ->createResponseFromThrowable($this->request->reveal(), $exception)
            ->willReturn($expected);

        $result = $this->middleware->process($this->request->reveal(), $delegate->reveal());

        $this->assertSame($expected, $result);
    }

    /**
     * @dataProvider acceptHeaders
     */
    public function testMiddlewareRegistersErrorHandlerToConvertErrorsToProblemDetails(string $accept)
    {
        $this->request->getHeaderLine('Accept')->willReturn($accept);

        $delegate = $this->prophesize(DelegateInterface::class);
        $delegate
            ->process(Argument::that([$this->request, 'reveal']))
            ->will(function () {
                trigger_error('Triggered error!', \E_USER_ERROR);
            });

        $expected = $this->prophesize(ResponseInterface::class)->reveal();
        $this->responseFactory
            ->createResponseFromThrowable($this->request->reveal(), Argument::that(function ($e) {
                $this->assertInstanceOf(ErrorException::class, $e);
                $this->assertEquals(\E_USER_ERROR, $e->getSeverity());
                $this->assertEquals('Triggered error!', $e->getMessage());
                return true;
            }))
            ->willReturn($expected);

        $result = $this->middleware->process($this->request->reveal(), $delegate->reveal());

        $this->assertSame($expected, $result);
    }

    public function testRethrowsCaughtExceptionIfUnableToNegotiateAcceptHeader()
    {
        $this->request->getHeaderLine('Accept')->willReturn('text/html');
        $exception = new TestAsset\RuntimeException('Thrown!', 507);
        $delegate  = $this->prophesize(DelegateInterface::class);
        $delegate
            ->process(Argument::that([$this->request, 'reveal']))
            ->willThrow($exception);

        $middleware = new ProblemDetailsMiddleware();

        $this->expectException(TestAsset\RuntimeException::class);
        $this->expectExceptionMessage('Thrown!');
        $this->expectExceptionCode(507);
        $result = $middleware->process($this->request->reveal(), $delegate->reveal());
    }
}
