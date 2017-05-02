<?php

namespace ProblemDetails;

use Zend\Diactoros\Response\JsonResponse;

/**
 * Model a Problem Details response.
 *
 * @see https://tools.ietf.org/html/rfc7807
 */
class ProblemDetailsResponse extends JsonResponse
{
    public static function create(
        int $status,
        string $detail,
        string $title,
        string $type,
        array $additional = []
    ) : self {
        $status = self::normalizeStatus($status);

        $payload = [
            'title'  => $title,
            'type'   => $type,
            'status' => $status,
            'detail' => $detail,
        ];

        if ($additional) {
            $payload = array_merge($additional, $payload);
        }

        return new self($payload, $status, ['Content-Type' => 'application/problem+json']);
    }

    private static function normalizeStatus(int $status) : int
    {
        if ($status < 100 || $status > 599) {
            return 500;
        }

        return $status;
    }
}
