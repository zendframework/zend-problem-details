<?php

namespace ProblemDetails;

use Psr\Container\ContainerInterface;

class ProblemDetailsMiddlewareFactory
{
    public function __invoke(ContainerInterface $container) : ProblemDetailsMiddleware
    {
        return $container->has(ProblemDetailsResponseFactory::class)
            ? new ProblemDetailsMiddleware($container->get(ProblemDetailsResponseFactory::class))
            : new ProblemDetailsMiddleware();
    }
}
