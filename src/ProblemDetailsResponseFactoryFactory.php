<?php
/**
 * @see       https://github.com/zendframework/zend-problem-details for the canonical source repository
 * @copyright Copyright (c) 2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-problem-details/blob/master/LICENSE.md New BSD License
 */

namespace Zend\ProblemDetails;

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

        $streamFactory = $container->has('Zend\ProblemDetails\StreamFactory')
            ? $container->get('Zend\ProblemDetails\StreamFactory')
            : null;

        return new ProblemDetailsResponseFactory(
            $includeThrowableDetail,
            $jsonFlags,
            $responsePrototype,
            $streamFactory
        );
    }
}
