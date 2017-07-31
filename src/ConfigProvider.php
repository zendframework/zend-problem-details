<?php

namespace ProblemDetails;

/**
 * The configuration provider for the ProblemDetails module
 *
 * @see https://docs.zendframework.com/zend-component-installer/
 */
class ConfigProvider
{
    /**
     * Returns the configuration array
     *
     * To add a bit of a structure, each section is defined in a separate
     * method which returns an array with its configuration.
     *
     * @return array
     */
    public function __invoke() : array
    {
        return [
            'dependencies' => $this->getDependencies(),
        ];
    }

    /**
     * Returns the container dependencies
     *
     * @return array
     */
    public function getDependencies() : array
    {
        return [
            'factories'  => [
                ProblemDetailsMiddleware::class => ProblemDetailsMiddlewareFactory::class,
                ProblemDetailsResponseFactory::class => ProblemDetailsResponseFactoryFactory::class,
            ],
        ];
    }
}
