<?php

namespace ProblemDetailsTest;

use PHPUnit\Framework\TestCase;
use ProblemDetails\ProblemDetailsJsonResponse;
use ProblemDetails\ProblemDetailsResponse;
use ProblemDetails\ProblemDetailsResponseFactory;
use ProblemDetails\ProblemDetailsXmlResponse;
use RuntimeException;

class ProblemDetailsResponseFactoryTest extends TestCase
{
    use ProblemDetailsAssertionsTrait;

    public function acceptHeaders()
    {
        return [
            'application/xml'          => ['application/xml', ProblemDetailsXmlResponse::class],
            'application/vnd.api+xml'  => ['application/vnd.api+xml', ProblemDetailsXmlResponse::class],
            'application/json'         => ['application/json', ProblemDetailsJsonResponse::class],
            'application/vnd.api+json' => ['application/vnd.api+json', ProblemDetailsJsonResponse::class],
        ];
    }

    /**
     * @dataProvider acceptHeaders
     */
    public function testCreateResponseCreatesExpectedType(string $header, string $expectedType)
    {
        $response = ProblemDetailsResponseFactory::createResponse(
            $header,
            500,
            'Unknown error occurred'
        );

        $this->assertInstanceOf($expectedType, $response);
    }

    /**
     * @dataProvider acceptHeaders
     */
    public function testCreateResponseFromThrowableCreatesExpectedType(string $header, string $expectedType)
    {
        $exception = new RuntimeException();
        $response = ProblemDetailsResponseFactory::createResponseFromThrowable(
            $header,
            $exception
        );

        $this->assertInstanceOf($expectedType, $response);
    }

    /**
     * @dataProvider acceptHeaders
     */
    public function testCreateResponseFromThrowableCreatesExpectedTypeWithExtraInformation(
        string $header,
        string $expectedType
    ) {
        $exception = new RuntimeException();
        $response = ProblemDetailsResponseFactory::createResponseFromThrowable(
            $header,
            $exception,
            ProblemDetailsResponse::INCLUDE_THROWABLE_DETAILS
        );

        $this->assertInstanceOf($expectedType, $response);

        $payload = ($expectedType === ProblemDetailsJsonResponse::class)
            ? $this->getPayloadFromJsonResponse($response)
            : $this->getPayloadFromXmlResponse($response);

        $this->assertArrayHasKey('exception', $payload);
    }
}
