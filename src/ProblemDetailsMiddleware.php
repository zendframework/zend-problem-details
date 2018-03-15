<?php
/**
 * @see       https://github.com/zendframework/zend-problem-details for the canonical source repository
 * @copyright Copyright (c) 2017 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-problem-details/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\ProblemDetails;

use ErrorException;
use Negotiation\Negotiator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

use function array_walk;
use function error_reporting;
use function in_array;
use function restore_error_handler;
use function set_error_handler;

/**
 * Middleware that ensures a Problem Details response is returned
 * for all errors and Exceptions/Throwables.
 */
class ProblemDetailsMiddleware implements MiddlewareInterface
{
    /**
     * @var callable[]
     */
    private $listeners = [];

    /**
     * @var ProblemDetailsResponseFactory
     */
    private $responseFactory;

    public function __construct(ProblemDetailsResponseFactory $responseFactory)
    {
        $this->responseFactory = $responseFactory;
    }

    /**
     * {@inheritDoc}
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        // If we cannot provide a representation, act as a no-op.
        if (! $this->canActAsErrorHandler($request)) {
            return $handler->handle($request);
        }

        try {
            set_error_handler($this->createErrorHandler());
            $response = $handler->handle($request);
        } catch (Throwable $e) {
            $response = $this->responseFactory->createResponseFromThrowable($request, $e);
            $this->triggerListeners($e, $request, $response);
        } finally {
            restore_error_handler();
        }

        return $response;
    }

    /**
     * Attach an error listener.
     *
     * Each listener receives the following three arguments:
     *
     * - Throwable $error
     * - ServerRequestInterface $request
     * - ResponseInterface $response
     *
     * These instances are all immutable, and the return values of
     * listeners are ignored; use listeners for reporting purposes
     * only.
     */
    public function attachListener(callable $listener) : void
    {
        if (in_array($listener, $this->listeners, true)) {
            return;
        }

        $this->listeners[] = $listener;
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

    /**
     * Trigger all error listeners.
     */
    private function triggerListeners(
        Throwable $error,
        ServerRequestInterface $request,
        ResponseInterface $response
    ) : void {
        array_walk($this->listeners, function ($listener) use ($error, $request, $response) {
            $listener($error, $request, $response);
        });
    }
}
