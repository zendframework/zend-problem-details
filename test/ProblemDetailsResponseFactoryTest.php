<?php

namespace ProblemDetailsTest;

use PHPUnit\Framework\TestCase;
use ProblemDetails\ProblemDetailsJsonResponse;
use ProblemDetails\ProblemDetailsResponse;
use ProblemDetails\ProblemDetailsResponseFactory;
use ProblemDetails\ProblemDetailsXmlResponse;
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
}
