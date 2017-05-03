<?php

namespace ProblemDetails;

use Psr\Container\ContainerInterface;

class ProblemDetailsMiddlewareFactory
{
    public function __invoke(ContainerInterface $container) : ProblemDetailsMiddleware
    {
        $config = $container->has('config') ? $container->get('config') : [];
        $includeThrowableDetail = $config['debug'] ?? ProblemDetailsResponse::EXCLUDE_THROWABLE_DETAILS;
        return new ProblemDetailsMiddleware($includeThrowableDetail);
    }
}
