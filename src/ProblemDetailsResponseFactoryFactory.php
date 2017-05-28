<?php

namespace ProblemDetails;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;

class ProblemDetailsResponseFactoryFactory
{
    public function __invoke(ContainerInterface $container) : ProblemDetailsResponseFactory
    {
        $config = $container->has('config') ? $container->get('config') : [];
        $includeThrowableDetail = $config['debug'] ?? ProblemDetailsResponseFactory::EXCLUDE_THROWABLE_DETAILS;

        $problemDetailsConfig = $config['problem-details'] ?? [];
        $jsonFlags = $problemDetailsConfig['json_flags'] ?? null;

        $responsePrototype = $container->has(ResponseInterface::class)
            ? $container->get(ResponseInterface::class)
            : null;

        $streamFactory = $container->has('ProblemDetails\StreamFactory')
            ? $container->get('ProblemDetails\StreamFactory')
            : null;

        return new ProblemDetailsResponseFactory(
            $includeThrowableDetail,
            $jsonFlags,
            $responsePrototype,
            $streamFactory
        );
    }
}
