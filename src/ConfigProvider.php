<?php
/**
 * @see       https://github.com/zendframework/zend-problem-details for the canonical source repository
 * @copyright Copyright (c) 2017 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-problem-details/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\ProblemDetails;

/**
 * Configuration provider for the package.
 *
 * @see https://docs.zendframework.com/zend-component-installer/
 */
class ConfigProvider
{
    /**
     * Returns the configuration array.
     */
    public function __invoke() : array
    {
        return [
            'dependencies' => $this->getDependencies(),
        ];
    }

    /**
     * Returns the container dependencies.
     */
    public function getDependencies() : array
    {
        return [
            'factories'  => [
                ProblemDetailsMiddleware::class => ProblemDetailsMiddlewareFactory::class,
                ProblemDetailsNotFoundHandler::class => ProblemDetailsNotFoundHandlerFactory::class,
                ProblemDetailsResponseFactory::class => ProblemDetailsResponseFactoryFactory::class,
            ],
        ];
    }
}
