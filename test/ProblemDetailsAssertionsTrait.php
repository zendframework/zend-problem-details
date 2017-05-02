<?php

namespace ProblemDetailsTest;

use ProblemDetails\ProblemDetailsJsonResponse;
use Throwable;

trait ProblemDetailsAssertionsTrait
{
    public function assertProblemDetails(array $expected, array $details)
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

    public function getPayloadFromJsonResponse(ProblemDetailsJsonResponse $response) : array
    {
        $body = $response->getBody();
        $json = (string) $body;
        return json_decode($json, true);
    }
}
