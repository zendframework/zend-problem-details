<?php
/**
 * @see       https://github.com/zendframework/zend-problem-details for the canonical source repository
 * @copyright Copyright (c) 2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-problem-details/blob/master/LICENSE.md New BSD License
 */

namespace Zend\ProblemDetails;

use ErrorException;
use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface;
use Negotiation\Negotiator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Middleware that ensures a Problem Details response is returned
 * for all errors and Exceptions/Throwables.
 */
class ProblemDetailsMiddleware implements MiddlewareInterface
{
    /**
     * @var ProblemDetailsResponseFactory
     */
    private $responseFactory;

    public function __construct(ProblemDetailsResponseFactory $responseFactory = null)
    {
        $this->responseFactory = $responseFactory ?: new ProblemDetailsResponseFactory();
    }

    /**
     * {@inheritDoc}
     */
    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        // If we cannot provide a representation, act as a no-op.
        if (! $this->canActAsErrorHandler($request)) {
            return $delegate->process($request);
        }

        try {
            set_error_handler($this->createErrorHandler());
            $response = $delegate->process($request);

            if (! $response instanceof ResponseInterface) {
                throw new Exception\MissingResponseException('Application did not return a response');
            }
        } catch (Throwable $e) {
            $response = $this->responseFactory->createResponseFromThrowable($request, $e);
        } finally {
            restore_error_handler();
        }

        return $response;
    }

    /**
     * Can the middleware act as an error handler?
     *
     * Returns a boolean false if negotiation fails.
     */
    private function canActAsErrorHandler(ServerRequestInterface $request) : bool
    {
        $accept = $request->getHeaderLine('Accept') ?: '*/*';

        return null !== (new Negotiator())
            ->getBest($accept, ProblemDetailsResponseFactory::NEGOTIATION_PRIORITIES);
    }

    /**
     * Creates and returns a callable error handler that raises exceptions.
     *
     * Only raises exceptions for errors that are within the error_reporting mask.
     *
     * @return callable
     */
    private function createErrorHandler() : callable
    {
        /**
         * @param int $errno
         * @param string $errstr
         * @param string $errfile
         * @param int $errline
         * @return void
         * @throws ErrorException if error is not within the error_reporting mask.
         */
        return function (int $errno, string $errstr, string $errfile, int $errline) : void {
            if (! (error_reporting() & $errno)) {
                // error_reporting does not include this error
                return;
            }

            throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
        };
    }
}
