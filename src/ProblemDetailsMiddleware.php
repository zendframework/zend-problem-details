<?php

namespace ProblemDetails;

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
     * @var bool
     */
    private $includeThrowableDetail;

    public function __construct(bool $includeThrowableDetail = ProblemDetailsResponse::EXCLUDE_THROWABLE_DETAILS)
    {
        $this->includeThrowableDetail = $includeThrowableDetail;
    }

    /**
     * {@inheritDoc}
     */
    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        set_error_handler($this->createErrorHandler());

        try {
            $response = $delegate->process($request);

            if (! $response instanceof ResponseInterface) {
                throw new MissingResponseException('Application did not return a response');
            }
        } catch (Throwable $e) {
            $accept = $request->getHeaderLine('Accept');
            $mediaType = (new Negotiator())->getBest($accept, ProblemDetailsResponseFactory::NEGOTIATION_PRIORITIES);

            // Re-throw if we cannot provide a representation
            if (! $mediaType) {
                restore_error_handler();
                throw $e;
            }

            $response = ProblemDetailsResponseFactory::createResponseFromThrowable(
                $accept,
                $e,
                $this->includeThrowableDetail
            );
        }

        restore_error_handler();

        return $response;
    }

    /**
     * Creates and returns a callable error handler that raises exceptions.
     *
     * Only raises exceptions for errors that are within the error_reporting mask.
     *
     * @return callable
     */
    private function createErrorHandler()
    {
        /**
         * @param int $errno
         * @param string $errstr
         * @param string $errfile
         * @param int $errline
         * @return void
         * @throws ErrorException if error is not within the error_reporting mask.
         */
        return function ($errno, $errstr, $errfile, $errline) {
            if (! (error_reporting() & $errno)) {
                // error_reporting does not include this error
                return;
            }

            throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
        };
    }
}
