<?php

namespace ProblemDetailsTest;

use PHPUnit\Framework\TestCase;
use ProblemDetails\ProblemDetailsResponse;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use Zend\Diactoros\Response\JsonResponse;

class ProblemDetailsResponseTest extends TestCase
{
    public function assertExceptionDetails(Throwable $e, array $details)
    {
        $this->assertArrayHasKey('class', $details);
        $this->assertSame(get_class($e), $details['class']);
        $this->assertArrayHasKey('code', $details);
        $this->assertSame($e->getCode(), $details['code']);
        $this->assertArrayHasKey('message', $details);
        $this->assertSame($e->getMessage(), $details['message']);
        $this->assertArrayHasKey('file', $details);
        $this->assertSame($e->getFile(), $details['file']);
        $this->assertArrayHasKey('line', $details);
        $this->assertSame($e->getLine(), $details['line']);

        // PHP does some odd things when creating the trace; individual items
        // may be objects, but once copied, they are arrays. This makes direct
        // comparison impossible; thus, only testing for correct type.
        $this->assertArrayHasKey('trace', $details);
        $this->assertInternalType('array', $details['trace']);
    }

    public function getPayloadFromResponse(ProblemDetailsResponse $response) : array
    {
        $body = $response->getBody();
        $json = (string) $body;
        return json_decode($json, true);
    }

    public function testIsAResponseInterface()
    {
        $response = new ProblemDetailsResponse([]);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testIsAJsonResponse()
    {
        $response = new ProblemDetailsResponse([]);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    public function testCreateReturnsAProblemDetailsResponse()
    {
        $status = 400;
        $detail = 'Error in client submission';
        $title  = 'Bad Request';
        $type   = 'https://httpstatuses.com/400';

        $response = ProblemDetailsResponse::create($status, $detail, $title, $type);

        $this->assertInstanceOf(ProblemDetailsResponse::class, $response);
        return [
            'response' => $response,
            'status'   => $status,
            'detail'   => $detail,
            'title'    => $title,
            'type'     => $type,
        ];
    }

    /**
     * @depends testCreateReturnsAProblemDetailsResponse
     */
    public function testCreatePopulatesResponseWithRequiredElements(array $expectations)
    {
        $response = $expectations['response'];
        unset($expectations['response']);

        $payload = $this->getPayloadFromResponse($response);

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
        $response = ProblemDetailsResponse::create($status, 'Invalid request provided');
        $this->assertSame(500, $response->getStatusCode());
    }

    public function testCreateWillGenerateTitleFromStatusIfNotProvided()
    {
        $response = ProblemDetailsResponse::create(400, 'Invalid request provided');
        $payload = $this->getPayloadFromResponse($response);
        $this->assertEquals('Bad Request', $payload['title']);
    }

    public function testCreateWillGenerateTypeFromStatusIfNotProvided()
    {
        $response = ProblemDetailsResponse::create(400, 'Invalid request provided');
        $payload = $this->getPayloadFromResponse($response);
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

        $response = ProblemDetailsResponse::create($status, $detail, $title, $type, $additional);

        $this->assertInstanceOf(ProblemDetailsResponse::class, $response);

        $payload = $this->getPayloadFromResponse($response);
        $this->assertSame($status, $payload['status']);
        $this->assertSame($detail, $payload['detail']);
        $this->assertSame($title, $payload['title']);
        $this->assertSame($type, $payload['type']);
        $this->assertSame($additional['new'], $payload['new']);
    }

    public function testCreateFromThrowableWillGenerateResponseBasedOnThrowableDetails()
    {
        $e = new TestAsset\RuntimeException('An exception to throw', 424);

        $response = ProblemDetailsResponse::createFromThrowable($e);

        $this->assertSame($e->getCode(), $response->getStatusCode());
        $payload = $this->getPayloadFromResponse($response);
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

        $response = ProblemDetailsResponse::createFromThrowable($e);

        $this->assertSame(500, $response->getStatusCode());
    }

    public function testCreateFromThrowableAllowsOptInToIncludeThrowableDetail()
    {
        $e = new TestAsset\RuntimeException('An exception to throw', 424);

        $response = ProblemDetailsResponse::createFromThrowable($e, true);

        $this->assertSame($e->getCode(), $response->getStatusCode());
        $payload = $this->getPayloadFromResponse($response);
        $this->assertArrayHasKey('exception', $payload);

        $this->assertExceptionDetails($e, $payload['exception']);
    }

    public function testCreateFromThrowableWithDetailIncludesPreviousExceptions()
    {
        $first = new TestAsset\RuntimeException('First exception');
        $second = new TestAsset\RuntimeException('Second exception', 500, $first);
        $thrown = new TestAsset\RuntimeException('An exception to throw', 424, $second);

        $response = ProblemDetailsResponse::createFromThrowable($thrown, true);

        $this->assertSame($thrown->getCode(), $response->getStatusCode());
        $payload = $this->getPayloadFromResponse($response);
        $this->assertArrayHasKey('exception', $payload);

        $exceptionDetails = $payload['exception'];
        $this->assertExceptionDetails($thrown, $exceptionDetails);

        $this->assertArrayHasKey('stack', $exceptionDetails);
        $this->assertInternalType('array', $exceptionDetails['stack']);
        $this->assertCount(2, $exceptionDetails['stack']);

        $this->assertExceptionDetails($second, array_shift($exceptionDetails['stack']));
        $this->assertExceptionDetails($first, array_shift($exceptionDetails['stack']));
    }
}
