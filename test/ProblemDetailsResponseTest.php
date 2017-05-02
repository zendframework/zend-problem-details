<?php

namespace ProblemDetailsTest;

use PHPUnit\Framework\TestCase;
use ProblemDetails\ProblemDetailsResponse;
use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\Response\JsonResponse;

class ProblemDetailsResponseTest extends TestCase
{
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

        $body = $response->getBody();
        $json = (string) $body;
        $payload = json_decode($json, true);

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
}
