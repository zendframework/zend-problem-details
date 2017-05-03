<?php

namespace ProblemDetailsTest;

use PHPUnit\Framework\TestCase;
use ProblemDetails\CommonProblemDetailsException;
use ProblemDetails\ProblemDetailsException;
use ProblemDetails\ProblemDetailsJsonResponse;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Zend\Diactoros\Response\JsonResponse;

class ProblemDetailsJsonResponseTest extends TestCase
{
    use ProblemDetailsAssertionsTrait;

    public function testIsAResponseInterface()
    {
        $response = new ProblemDetailsJsonResponse([]);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testIsAJsonResponse()
    {
        $response = new ProblemDetailsJsonResponse([]);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    public function testCreateReturnsAProblemDetailsJsonResponse()
    {
        $status = 400;
        $detail = 'Error in client submission';
        $title  = 'Bad Request';
        $type   = 'https://httpstatuses.com/400';

        $response = ProblemDetailsJsonResponse::create($status, $detail, $title, $type);

        $this->assertInstanceOf(ProblemDetailsJsonResponse::class, $response);
        return [
            'response' => $response,
            'status'   => $status,
            'detail'   => $detail,
            'title'    => $title,
            'type'     => $type,
        ];
    }

    /**
     * @depends testCreateReturnsAProblemDetailsJsonResponse
     */
    public function testCreatePopulatesResponseWithCorrectContentTypeHeader(array $expectations)
    {
        $response = $expectations['response'];
        $this->assertEquals('application/problem+json', $response->getHeaderLine('Content-Type'));
    }

    /**
     * @depends testCreateReturnsAProblemDetailsJsonResponse
     */
    public function testCreatePopulatesResponseWithRequiredElements(array $expectations)
    {
        $response = $expectations['response'];
        unset($expectations['response']);

        $payload = $this->getPayloadFromJsonResponse($response);

        $expectedKeys = array_keys($expectations);
        sort($expectedKeys);
        $payloadKeys = array_keys($payload);
        sort($payloadKeys);

        $this->assertSame($expectedKeys, $payloadKeys);
        $this->assertSame($expectations['status'], $payload['status']);
        $this->assertSame($expectations['detail'], $payload['detail']);
        $this->assertSame($expectations['title'], $payload['title']);
        $this->assertSame($expectations['type'], $payload['type']);
    }

    public function outOfRangeStatusCodes()
    {
        return [
            99       => [99],
            600      => [600],
            0        => [0],
            'string' => ['string'],
        ];
    }

    /**
     * @dataProvider outOfRangeStatusCodes
     */
    public function testCreateWillReturn500StatusForOutOfRangeStatus($status)
    {
        $status = is_int($status) ? $status : 0;
        $response = ProblemDetailsJsonResponse::create($status, 'Invalid request provided');
        $this->assertSame(500, $response->getStatusCode());
    }

    public function testCreateWillGenerateTitleFromStatusIfNotProvided()
    {
        $response = ProblemDetailsJsonResponse::create(400, 'Invalid request provided');
        $payload = $this->getPayloadFromJsonResponse($response);
        $this->assertEquals('Bad Request', $payload['title']);
    }

    public function testCreateWillGenerateTypeFromStatusIfNotProvided()
    {
        $response = ProblemDetailsJsonResponse::create(400, 'Invalid request provided');
        $payload = $this->getPayloadFromJsonResponse($response);
        $this->assertEquals('https://httpstatus.es/400', $payload['type']);
    }

    public function testPassingAdditionalDetailsToCreateWillNotOverwriteRequiredDetails()
    {
        $status = 400;
        $detail = 'Error in client submission';
        $title  = 'Bad Request';
        $type   = 'https://httpstatuses.com/400';

        $additional = [
            'status' => 500,
            'detail' => 'Overwritten!',
            'title'  => 'Invalid!',
            'type'   => 'http://example.com/invalid',
            'new'    => 'Expected',
        ];

        $response = ProblemDetailsJsonResponse::create($status, $detail, $title, $type, $additional);

        $this->assertInstanceOf(ProblemDetailsJsonResponse::class, $response);

        $payload = $this->getPayloadFromJsonResponse($response);
        $this->assertSame($status, $payload['status']);
        $this->assertSame($detail, $payload['detail']);
        $this->assertSame($title, $payload['title']);
        $this->assertSame($type, $payload['type']);
        $this->assertSame($additional['new'], $payload['new']);
    }

    public function testCreateFromThrowableWillGenerateResponseBasedOnThrowableDetails()
    {
        $e = new TestAsset\RuntimeException('An exception to throw', 424);

        $response = ProblemDetailsJsonResponse::createFromThrowable($e);

        $this->assertSame($e->getCode(), $response->getStatusCode());
        $payload = $this->getPayloadFromJsonResponse($response);
        $this->assertEquals('Failed Dependency', $payload['title']);
        $this->assertEquals('https://httpstatus.es/424', $payload['type']);
        $this->assertEquals($e->getMessage(), $payload['detail']);
        $this->assertArrayNotHasKey('throwable', $payload);
    }

    /**
     * @dataProvider outOfRangeStatusCodes
     */
    public function testCreateFromThrowableUses500StatusForOutOfRangeCode($code)
    {
        $e = new TestAsset\RuntimeException('An exception to throw', $code);

        $response = ProblemDetailsJsonResponse::createFromThrowable($e);

        $this->assertSame(500, $response->getStatusCode());
    }

    public function testCreateFromThrowableAllowsOptInToIncludeThrowableDetail()
    {
        $e = new TestAsset\RuntimeException('An exception to throw', 424);

        $response = ProblemDetailsJsonResponse::createFromThrowable(
            $e,
            ProblemDetailsJsonResponse::INCLUDE_THROWABLE_DETAILS
        );

        $this->assertSame($e->getCode(), $response->getStatusCode());
        $payload = $this->getPayloadFromJsonResponse($response);
        $this->assertArrayHasKey('exception', $payload);

        $this->assertExceptionDetails($e, $payload['exception']);
    }

    public function testCreateFromThrowableWithDetailIncludesPreviousExceptions()
    {
        $first = new TestAsset\RuntimeException('First exception');
        $second = new TestAsset\RuntimeException('Second exception', 500, $first);
        $thrown = new TestAsset\RuntimeException('An exception to throw', 424, $second);

        $response = ProblemDetailsJsonResponse::createFromThrowable(
            $thrown,
            ProblemDetailsJsonResponse::INCLUDE_THROWABLE_DETAILS
        );

        $this->assertSame($thrown->getCode(), $response->getStatusCode());
        $payload = $this->getPayloadFromJsonResponse($response);
        $this->assertArrayHasKey('exception', $payload);

        $exceptionDetails = $payload['exception'];
        $this->assertExceptionDetails($thrown, $exceptionDetails);

        $this->assertArrayHasKey('stack', $exceptionDetails);
        $this->assertInternalType('array', $exceptionDetails['stack']);
        $this->assertCount(2, $exceptionDetails['stack']);

        $this->assertExceptionDetails($second, array_shift($exceptionDetails['stack']));
        $this->assertExceptionDetails($first, array_shift($exceptionDetails['stack']));
    }

    public function testCreateFromThrowableWillUseProblemDetailExceptionDetails()
    {
        $status = 400;
        $detail = 'Error in client submission';
        $title  = 'Bad Request';
        $type   = 'https://httpstatuses.com/400';

        $additional = [
            'other' => 'Expected',
        ];

        $e = new class (
            $status,
            $detail,
            $title,
            $type,
            $additional
        ) extends RuntimeException implements ProblemDetailsException {
            use CommonProblemDetailsException;

            private $status;
            private $type;
            private $title;
            private $detail;
            private $additional;

            public function __construct(int $status, string $detail, string $title, string $type, array $additional)
            {
                $this->status = $status;
                $this->detail = $detail;
                $this->title = $title;
                $this->type = $type;
                $this->additional = $additional;
            }
        };

        $response = ProblemDetailsJsonResponse::createFromThrowable($e);

        $this->assertSame($status, $response->getStatusCode());
        $payload = $this->getPayloadFromResponse($response);
        $this->assertEquals($status, $payload['status']);
        $this->assertEquals($title, $payload['title']);
        $this->assertEquals($type, $payload['type']);
        $this->assertEquals($detail, $payload['detail']);
        $this->assertEquals($additional['other'], $payload['other']);
    }
}
