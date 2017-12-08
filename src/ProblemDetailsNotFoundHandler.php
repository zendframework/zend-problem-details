<?php
/**
 * @see       https://github.com/zendframework/zend-problem-details for the canonical source repository
 * @copyright Copyright (c) 2017 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-problem-details/blob/master/LICENSE.md New BSD License
 */

namespace Zend\ProblemDetails;

use Interop\Http\Server\MiddlewareInterface;
use Interop\Http\Server\RequestHandlerInterface;
use Negotiation\Negotiator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ProblemDetailsNotFoundHandler implements MiddlewareInterface
{
    /**
     * @var ProblemDetailsResponseFactory
     */
    private $responseFactory;

    /**
     * @param null|ProblemDetailsResponseFactory $responseFactory Factory to create a response to
     *     update and return when returning an 404 response.
     */
    public function __construct(ProblemDetailsResponseFactory $responseFactory = null)
    {
        $this->responseFactory = $responseFactory ?: new ProblemDetailsResponseFactory();
    }

    /**
     * Creates and returns a 404 response.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        // If we cannot provide a representation, act as a no-op.
        if (! $this->canActAsErrorHandler($request)) {
            return $handler->handle($request);
        }

        return $this->responseFactory->createResponse(
            $request,
            404,
            sprintf('Cannot %s %s!', $request->getMethod(), (string) $request->getUri())
        );
    }

    /**
     * Can the middleware act as an error handler?
     */
    private function canActAsErrorHandler(ServerRequestInterface $request) : bool
    {
        $accept = $request->getHeaderLine('Accept') ?: '*/*';

        return null !== (new Negotiator())
            ->getBest($accept, ProblemDetailsResponseFactory::NEGOTIATION_PRIORITIES);
    }
}
