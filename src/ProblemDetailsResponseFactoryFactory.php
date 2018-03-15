<?php
/**
 * @see       https://github.com/zendframework/zend-problem-details for the canonical source repository
 * @copyright Copyright (c) 2017-2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-problem-details/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

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

        return new ProblemDetailsResponseFactory(
            $container->get(ResponseInterface::class),
            $includeThrowableDetail,
            $jsonFlags,
            $includeThrowableDetail
        );
    }
}
