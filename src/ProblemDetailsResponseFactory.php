<?php

namespace ProblemDetails;

use Negotiation\Negotiator;
use Throwable;

/**
 * Create an appropriate ProblemDetailsResponse based on Accept header.
 *
 * If negotiation fails, a ProblemDetailsXmlResponse is created. Otherwise,
 * a response based on the results of negotiation is created.
 */
class ProblemDetailsResponseFactory
{
    private static $negotiationPriorities = [
        'application/json',
        'application/*+json',
        'application/xml',
        'application/*+xml',
    ];

    public static function createResponse(
        string $accept,
        int $status,
        string $detail,
        string $title = '',
        string $type = '',
        array $additional = []
    ) : ProblemDetailsResponse {
        $factory = sprintf('%s::create', self::discoverResponseClass($accept));
        return $factory($status, $detail, $title, $type, $additional);
    }

    public static function createResponseFromThrowable(
        string $accept,
        Throwable $e,
        bool $includeThrowable = ProblemDetailsResponse::EXCLUDE_THROWABLE_DETAILS
    ) : ProblemDetailsResponse {
        $factory = sprintf('%s::createFromThrowable', self::discoverResponseClass($accept));
        return $factory($e, $includeThrowable);
    }

    private static function discoverResponseClass(string $accept) : string
    {
        $mediaType = (new Negotiator())->getBest($accept, self::$negotiationPriorities);

        if (! $mediaType) {
            return ProblemDetailsXmlResponse::class;
        }

        $value = $mediaType->getValue();
        return strstr($value, 'json')
            ? ProblemDetailsJsonResponse::class
            : ProblemDetailsXmlResponse::class;
    }
}
