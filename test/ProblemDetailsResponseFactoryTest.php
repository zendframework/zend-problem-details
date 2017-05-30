<?php

namespace ProblemDetailsTest;

use PHPUnit\Framework\TestCase;
use ProblemDetails\InvalidResponseBodyException;
use ProblemDetails\ProblemDetailsException;
use ProblemDetails\ProblemDetailsResponse;
use ProblemDetails\ProblemDetailsResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

class ProblemDetailsResponseFactoryTest extends TestCase
{
    use ProblemDetailsAssertionsTrait;

    protected function setUp()
    {
        $this->request = $this->prophesize(ServerRequestInterface::class);
        $this->factory = new ProblemDetailsResponseFactory();
    }

    public function acceptHeaders()
    {
        return [
            'application/xml'          => ['application/xml', 'application/problem+xml'],
            'application/vnd.api+xml'  => ['application/vnd.api+xml', 'application/problem+xml'],
            'application/json'         => ['application/json', 'application/problem+json'],
            'application/vnd.api+json' => ['application/vnd.api+json', 'application/problem+json'],
        ];
    }

    /**
     * @dataProvider acceptHeaders
     */
    public function testCreateResponseCreatesExpectedType(string $header, string $expectedType)
    {
        $this->request->getHeaderLine('Accept', 'application/xhtml+xml')->willReturn($header);

        $response = $this->factory->createResponse(
            $this->request->reveal(),
            500,
            'Unknown error occurred'
        );

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals($expectedType, $response->getHeaderLine('Content-Type'));
    }

    /**
     * @dataProvider acceptHeaders
     */
    public function testCreateResponseFromThrowableCreatesExpectedType(string $header, string $expectedType)
    {
        $this->request->getHeaderLine('Accept', 'application/xhtml+xml')->willReturn($header);

        $exception = new RuntimeException();
        $response = $this->factory->createResponseFromThrowable(
            $this->request->reveal(),
            $exception
        );

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals($expectedType, $response->getHeaderLine('Content-Type'));
    }

    /**
     * @dataProvider acceptHeaders
     */
    public function testCreateResponseFromThrowableCreatesExpectedTypeWithExtraInformation(
        string $header,
        string $expectedType
    ) {
        $this->request->getHeaderLine('Accept', 'application/xhtml+xml')->willReturn($header);

        $factory = new ProblemDetailsResponseFactory(ProblemDetailsResponseFactory::INCLUDE_THROWABLE_DETAILS);

        $exception = new RuntimeException();
        $response = $factory->createResponseFromThrowable(
            $this->request->reveal(),
            $exception
        );

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals($expectedType, $response->getHeaderLine('Content-Type'));

        $payload = $this->getPayloadFromResponse($response);
        $this->assertArrayHasKey('exception', $payload);
    }

    public function testCreateResponseFromThrowableWillPullDetailsFromProblemDetailsException()
    {
        $e = $this->prophesize(RuntimeException::class)->willImplement(ProblemDetailsException::class);
        $e->getStatus()->willReturn(400);
        $e->getDetail()->willReturn('Exception details');
        $e->getTitle()->willReturn('Invalid client request');
        $e->getType()->willReturn('https://example.com/api/doc/invalid-client-request');
        $e->getAdditionalData()->willReturn(['foo' => 'bar']);

        $this->request->getHeaderLine('Accept', 'application/xhtml+xml')->willReturn('application/json');

        $factory = new ProblemDetailsResponseFactory();

        $exception = new RuntimeException();
        $response = $factory->createResponseFromThrowable(
            $this->request->reveal(),
            $e->reveal()
        );

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals('application/problem+json', $response->getHeaderLine('Content-Type'));

        $payload = $this->getPayloadFromResponse($response);
        $this->assertSame(400, $payload['status']);
        $this->assertSame('Exception details', $payload['detail']);
        $this->assertSame('Invalid client request', $payload['title']);
        $this->assertSame('https://example.com/api/doc/invalid-client-request', $payload['type']);
        $this->assertSame('bar', $payload['foo']);
    }

    public function testFactoryRaisesExceptionIfBodyFactoryDoesNotReturnStream()
    {
        $this->request->getHeaderLine('Accept', 'application/xhtml+xml')->willReturn('application/json');

        $factory = new ProblemDetailsResponseFactory(false, null, null, function () {
            return null;
        });

        $this->expectException(InvalidResponseBodyException::class);
        $factory->createResponse($this->request->reveal(), '500', 'This is an error');
    }

    public function testFactoryGeneratesXmlResponseIfNegotiationFails()
    {
        $this->request->getHeaderLine('Accept', 'application/xhtml+xml')->willReturn('text/plain');

        $response = $this->factory->createResponse(
            $this->request->reveal(),
            500,
            'Unknown error occurred'
        );

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals('application/problem+xml', $response->getHeaderLine('Content-Type'));
    }

    public function testFactoryRendersPreviousExceptionsInDebugMode()
    {
        $this->request->getHeaderLine('Accept', 'application/xhtml+xml')->willReturn('application/json');

        $first = new RuntimeException('first', 101010);
        $second = new RuntimeException('second', 101011, $first);

        $factory = new ProblemDetailsResponseFactory(ProblemDetailsResponseFactory::INCLUDE_THROWABLE_DETAILS);

        $exception = new RuntimeException();
        $response = $factory->createResponseFromThrowable(
            $this->request->reveal(),
            $second
        );

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals('application/problem+json', $response->getHeaderLine('Content-Type'));

        $payload = $this->getPayloadFromResponse($response);
        $this->assertArrayHasKey('exception', $payload);
        $this->assertEquals(101011, $payload['exception']['code']);
        $this->assertEquals('second', $payload['exception']['message']);
        $this->assertArrayHasKey('stack', $payload['exception']);
        $this->assertInternalType('array', $payload['exception']['stack']);
        $this->assertEquals(101010, $payload['exception']['stack'][0]['code']);
        $this->assertEquals('first', $payload['exception']['stack'][0]['message']);
    }
}
