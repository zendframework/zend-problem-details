<?php
/**
 * @see       https://github.com/zendframework/zend-problem-details for the canonical source repository
 * @copyright Copyright (c) 2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-problem-details/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\ProblemDetails;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Webimpress\HttpMiddlewareCompatibility\HandlerInterface as DelegateInterface;
use Zend\ProblemDetails\ProblemDetailsNotFoundHandler;
use Zend\ProblemDetails\ProblemDetailsResponseFactory;

use const Webimpress\HttpMiddlewareCompatibility\HANDLER_METHOD;

class ProblemDetailsNotFoundHandlerTest extends TestCase
{
    use ProblemDetailsAssertionsTrait;

    public function acceptHeaders() : array
    {
        return [
            'application/json' => ['application/json', 'application/problem+json'],
            'application/xml'  => ['application/xml', 'application/problem+xml'],
        ];
    }

    /**
     * @dataProvider acceptHeaders
     */
    public function testReturnsResponseWith404StatusAndErrorMessageInBodyWithDefaultFactory(
        string $acceptHeader,
        string $expectedHeader
    ) : void {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getMethod()->willReturn('POST');
        $request->getHeaderLine('Accept')->willReturn($acceptHeader);
        $request->getUri()->willReturn('https://example.com/foo');

        $notFoundHandler = new ProblemDetailsNotFoundHandler();

        $returnedResponse = $notFoundHandler->process(
            $request->reveal(),
            $this->prophesize(DelegateInterface::class)->reveal()
        );

        $expectedBody = [
            'title' => 'Not Found',
            'type' => 'https://httpstatus.es/404',
            'status' => 404,
            'detail' => 'Cannot POST https://example.com/foo!',
        ];

        $this->assertEquals($expectedBody, $this->getPayloadFromResponse($returnedResponse));
        $this->assertSame(404, $returnedResponse->getStatusCode());
        $this->assertSame($expectedHeader, $returnedResponse->getHeaderLine('Content-Type'));
    }

    /**
     * @dataProvider acceptHeaders
     */
    public function testResponseFactoryPassedInConstructorGeneratesTheReturnedResponse(string $acceptHeader) : void
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getMethod()->willReturn('POST');
        $request->getHeaderLine('Accept')->willReturn($acceptHeader);
        $request->getUri()->willReturn('https://example.com/foo');

        $response = $this->prophesize(ResponseInterface::class);

        $responseFactory = $this->prophesize(ProblemDetailsResponseFactory::class);
        $responseFactory->createResponse(
            Argument::that([$request, 'reveal']),
            404,
            'Cannot POST https://example.com/foo!'
        )->will([$response, 'reveal']);

        $notFoundHandler = new ProblemDetailsNotFoundHandler($responseFactory->reveal());

        $this->assertSame(
            $response->reveal(),
            $notFoundHandler->process($request->reveal(), $this->prophesize(DelegateInterface::class)->reveal())
        );
    }

    public function testDelegateIsCalledIfAcceptHeaderIsUnacceptable() : void
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getMethod()->willReturn('POST');
        $request->getHeaderLine('Accept')->willReturn('text/html');
        $request->getUri()->willReturn('https://example.com/foo');

        $response = $this->prophesize(ResponseInterface::class);

        $delegate = $this->prophesize(DelegateInterface::class);
        $delegate->{HANDLER_METHOD}($request->reveal())->will([$response, 'reveal']);

        $responseFactory = $this->prophesize(ProblemDetailsResponseFactory::class);

        $notFoundHandler = new ProblemDetailsNotFoundHandler($responseFactory->reveal());

        $this->assertSame(
            $response->reveal(),
            $notFoundHandler->process($request->reveal(), $delegate->reveal())
        );
    }
}
