<?php
/**
 * @see       https://github.com/zendframework/zend-problem-details for the canonical source repository
 * @copyright Copyright (c) 2017 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-problem-details/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\ProblemDetails;

use Psr\Container\ContainerInterface;

class ProblemDetailsNotFoundHandlerFactory
{
    public function __invoke(ContainerInterface $container) : ProblemDetailsNotFoundHandler
    {
        return new ProblemDetailsNotFoundHandler($container->get(ProblemDetailsResponseFactory::class));
    }
}
