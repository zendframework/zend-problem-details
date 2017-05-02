<?php

namespace ProblemDetails;

use Throwable;

interface ProblemDetailsResponse
{
    const INCLUDE_THROWABLE_DETAILS = true;
    const EXCLUDE_THROWABLE_DETAILS = false;

    public static function create(
        int $status,
        string $detail,
        string $title = '',
        string $type = '',
        array $additional = []
    ) : ProblemDetailsResponse;

    public static function createFromThrowable(
        Throwable $e,
        bool $includeThrowable = self::EXCLUDE_THROWABLE_DETAILS
    ) : ProblemDetailsResponse;
}
