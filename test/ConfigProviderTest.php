<?php
/**
 * @see       https://github.com/zendframework/zend-problem-details for the canonical source repository
 * @copyright Copyright (c) 2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-problem-details/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\ProblemDetails;

use PHPUnit\Framework\TestCase;
use Zend\ProblemDetails\ConfigProvider;
use Zend\ProblemDetails\ProblemDetailsMiddleware;
use Zend\ProblemDetails\ProblemDetailsMiddlewareFactory;
use Zend\ProblemDetails\ProblemDetailsResponseFactory;
use Zend\ProblemDetails\ProblemDetailsResponseFactoryFactory;

class ConfigProviderTest extends TestCase
{
    public function testReturnsExpectedDependencies() : void
    {
        $provider = new ConfigProvider();
        $config = $provider();

        $this->assertArrayHasKey('dependencies', $config);

        $dependencies = $config['dependencies'];
        $this->assertArrayHasKey('factories', $dependencies);

        $factories = $dependencies['factories'];
        $this->assertCount(2, $factories);
        $this->assertArrayHasKey(ProblemDetailsMiddleware::class, $factories);
        $this->assertArrayHasKey(ProblemDetailsResponseFactory::class, $factories);

        $this->assertSame(
            ProblemDetailsMiddlewareFactory::class,
            $factories[ProblemDetailsMiddleware::class]
        );
        $this->assertSame(
            ProblemDetailsResponseFactoryFactory::class,
            $factories[ProblemDetailsResponseFactory::class]
        );
    }
}
