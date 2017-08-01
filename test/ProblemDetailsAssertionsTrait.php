<?php
/**
 * @see       https://github.com/zendframework/zend-problem-details for the canonical source repository
 * @copyright Copyright (c) 2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-problem-details/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\ProblemDetails;

use Psr\Http\Message\ResponseInterface;
use Throwable;
use Zend\ProblemDetails\ProblemDetailsJsonResponse;
use Zend\ProblemDetails\ProblemDetailsResponse;
use Zend\ProblemDetails\ProblemDetailsXmlResponse;

trait ProblemDetailsAssertionsTrait
{
    public function assertProblemDetails(array $expected, array $details) : void
    {
        foreach ($expected as $key => $value) {
            $this->assertArrayHasKey(
                $key,
                $details,
                sprintf('Did not find key %s in problem details', $key)
            );

            $this->assertEquals($value, $details[$key], sprintf(
                'Did not find expected value for "%s" key of details; expected "%s", received "%s"',
                $key,
                var_export($value, true),
                var_export($details[$key], true)
            ));
        }
    }

    public function assertExceptionDetails(Throwable $e, array $details) : void
    {
        $this->assertArrayHasKey('class', $details);
        $this->assertSame(get_class($e), $details['class']);
        $this->assertArrayHasKey('code', $details);
        $this->assertSame($e->getCode(), (int) $details['code']);
        $this->assertArrayHasKey('message', $details);
        $this->assertSame($e->getMessage(), $details['message']);
        $this->assertArrayHasKey('file', $details);
        $this->assertSame($e->getFile(), $details['file']);
        $this->assertArrayHasKey('line', $details);
        $this->assertSame($e->getLine(), (int) $details['line']);

        // PHP does some odd things when creating the trace; individual items
        // may be objects, but once copied, they are arrays. This makes direct
        // comparison impossible; thus, only testing for correct type.
        $this->assertArrayHasKey('trace', $details);
        $this->assertInternalType('array', $details['trace']);
    }

    public function getPayloadFromResponse(ResponseInterface $response) : array
    {
        $contentType = $response->getHeaderLine('Content-Type');

        if ('application/problem+json' === $contentType) {
            return $this->getPayloadFromJsonResponse($response);
        }

        if ('application/problem+xml' === $contentType) {
            return $this->getPayloadFromXmlResponse($response);
        }
    }

    public function getPayloadFromJsonResponse(ResponseInterface $response) : array
    {
        $body = $response->getBody();
        $json = (string) $body;
        return json_decode($json, true);
    }

    public function getPayloadFromXmlResponse(ResponseInterface $response) : array
    {
        $body = $response->getBody();
        $xml = simplexml_load_string((string) $body);
        $json = json_encode($xml);
        return json_decode($json, true);
    }
}
