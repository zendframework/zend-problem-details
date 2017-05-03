<?php

namespace ProblemDetails;

use Zend\Diactoros\Response\JsonResponse;
use Throwable;

/**
 * Model a Problem Details response.
 *
 * @see https://tools.ietf.org/html/rfc7807
 */
class ProblemDetailsJsonResponse extends JsonResponse implements ProblemDetailsResponse
{
    use CommonProblemDetails;

    private static $contentType = 'application/problem+json';

    /**
     * Generate the payload for the response.
     *
     * No-op; JsonResponse uses arrays directly during instantiation.
     *
     * @return array
     */
    protected static function generatePayload(array $payload)
    {
        return $payload;
    }
}
