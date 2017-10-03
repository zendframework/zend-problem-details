<?php
/**
 * @see       https://github.com/zendframework/zend-problem-details for the canonical source repository
 * @copyright Copyright (c) 2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-problem-details/blob/master/LICENSE.md New BSD License
 */

namespace Zend\ProblemDetails;

use Psr\Container\ContainerInterface;

class ProblemDetailsNotFoundHandlerFactory
{
    public function __invoke(ContainerInterface $container) : ProblemDetailsNotFoundHandler
    {
        return $container->has(ProblemDetailsResponseFactory::class)
            ? new ProblemDetailsNotFoundHandler($container->get(ProblemDetailsResponseFactory::class))
            : new ProblemDetailsNotFoundHandler();
    }
}
