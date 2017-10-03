<?php
namespace Zend\ProblemDetails;

use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface as ServerMiddlewareInterface;
use Negotiation\Negotiator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Stratigility\Delegate\CallableDelegateDecorator;

class ProblemDetailsNotFoundHandler implements ServerMiddlewareInterface
{
    /**
     * @var ProblemDetailsResponseFactory
     */
    private $responseFactory;

    /**
     * @param ProblemDetailsResponseFactory $responseFactory Factory to create a response to
     *     update and return when returning an 404 response.
     */
    public function __construct(ProblemDetailsResponseFactory $responseFactory = null)
    {
        $this->responseFactory = $responseFactory ?: new ProblemDetailsResponseFactory();
    }

    /**
     * Creates and returns a 404 response.
     *
     * @param ServerRequestInterface $request Ignored.
     * @param DelegateInterface $delegate Ignored.
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        // If we cannot provide a representation, act as a no-op.
        if (! $this->canActAsErrorHandler($request)) {
            return $delegate->process($request);
        }

        return $this->responseFactory->createResponse(
            $request,
            404,
            sprintf("Cannot %s %s!", $request->getMethod(), (string) $request->getUri())
        );
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
}
