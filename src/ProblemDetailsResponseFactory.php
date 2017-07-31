<?php
/**
 * @see       https://github.com/zendframework/zend-problem-details for the canonical source repository
 * @copyright Copyright (c) 2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-problem-details/blob/master/LICENSE.md New BSD License
 */

namespace Zend\ProblemDetails;

use Closure;
use Fig\Http\Message\StatusCodeInterface as StatusCode;
use Negotiation\Negotiator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Spatie\ArrayToXml\ArrayToXml;
use Throwable;
use Zend\Diactoros\Response;
use Zend\Diactoros\Stream;

/**
 * Create a Problem Details response.
 *
 * Factory for creating and returning a response representing problem details.
 *
 * Each public method accepts PSR-7 server request instance, as well as values
 * that can be used to create the problem details for the response.
 *
 * The Accept request header is used to determine what serialization format to
 * use. If negotiation fails, an XML response is created; otherwise, a response
 * based on the results of negotiation is created.
 *
 * If no title is provided, a title appropriate for the specified status will
 * be used.
 *
 * If no type is provided, a URI to httpstatus.es based on the specified status
 * will be used.
 */
class ProblemDetailsResponseFactory
{
    /**
     * @var string Content-Type header for JSON responses
     */
    const CONTENT_TYPE_JSON = 'application/problem+json';

    /**
     * @var string Content-Type header for XML responses
     */
    const CONTENT_TYPE_XML = 'application/problem+xml';

    /**
     * @var string[] Default problem detail titles based on status code
     */
    const DEFAULT_TITLE_MAP = [
        // 4×× Client Error
        StatusCode::STATUS_BAD_REQUEST                        => 'Bad Request',
        StatusCode::STATUS_UNAUTHORIZED                       => 'Unauthorized',
        StatusCode::STATUS_PAYMENT_REQUIRED                   => 'Payment Required',
        StatusCode::STATUS_FORBIDDEN                          => 'Forbidden',
        StatusCode::STATUS_NOT_FOUND                          => 'Not Found',
        StatusCode::STATUS_METHOD_NOT_ALLOWED                 => 'Method Not Allowed',
        StatusCode::STATUS_NOT_ACCEPTABLE                     => 'Not Acceptable',
        StatusCode::STATUS_PROXY_AUTHENTICATION_REQUIRED      => 'Proxy Authentication Required',
        StatusCode::STATUS_REQUEST_TIMEOUT                    => 'Request Timeout',
        StatusCode::STATUS_CONFLICT                           => 'Conflict',
        StatusCode::STATUS_GONE                               => 'Gone',
        StatusCode::STATUS_LENGTH_REQUIRED                    => 'Length Required',
        StatusCode::STATUS_PRECONDITION_FAILED                => 'Precondition Failed',
        StatusCode::STATUS_PAYLOAD_TOO_LARGE                  => 'Payload Too Large',
        StatusCode::STATUS_URI_TOO_LONG                       => 'Request-URI Too Long',
        StatusCode::STATUS_UNSUPPORTED_MEDIA_TYPE             => 'Unsupported Media Type',
        StatusCode::STATUS_RANGE_NOT_SATISFIABLE              => 'Requested Range Not Satisfiable',
        StatusCode::STATUS_EXPECTATION_FAILED                 => 'Expectation Failed',
        StatusCode::STATUS_IM_A_TEAPOT                        => 'I\'m a teapot',
        StatusCode::STATUS_MISDIRECTED_REQUEST                => 'Misdirected Request',
        StatusCode::STATUS_UNPROCESSABLE_ENTITY               => 'Unprocessable Entity',
        StatusCode::STATUS_LOCKED                             => 'Locked',
        StatusCode::STATUS_FAILED_DEPENDENCY                  => 'Failed Dependency',
        StatusCode::STATUS_UPGRADE_REQUIRED                   => 'Upgrade Required',
        StatusCode::STATUS_PRECONDITION_REQUIRED              => 'Precondition Required',
        StatusCode::STATUS_TOO_MANY_REQUESTS                  => 'Too Many Requests',
        StatusCode::STATUS_REQUEST_HEADER_FIELDS_TOO_LARGE    => 'Request Header Fields Too Large',
        444                                                   => 'Connection Closed Without Response',
        StatusCode::STATUS_UNAVAILABLE_FOR_LEGAL_REASONS      => 'Unavailable For Legal Reasons',
        499                                                   => 'Client Closed Request',
        // 5×× Server Error
        StatusCode::STATUS_INTERNAL_SERVER_ERROR           => 'Internal Server Error',
        StatusCode::STATUS_NOT_IMPLEMENTED                 => 'Not Implemented',
        StatusCode::STATUS_BAD_GATEWAY                     => 'Bad Gateway',
        StatusCode::STATUS_SERVICE_UNAVAILABLE             => 'Service Unavailable',
        StatusCode::STATUS_GATEWAY_TIMEOUT                 => 'Gateway Timeout',
        StatusCode::STATUS_VERSION_NOT_SUPPORTED           => 'HTTP Version Not Supported',
        StatusCode::STATUS_VARIANT_ALSO_NEGOTIATES         => 'Variant Also Negotiates',
        StatusCode::STATUS_INSUFFICIENT_STORAGE            => 'Insufficient Storage',
        StatusCode::STATUS_LOOP_DETECTED                   => 'Loop Detected',
        StatusCode::STATUS_NOT_EXTENDED                    => 'Not Extended',
        StatusCode::STATUS_NETWORK_AUTHENTICATION_REQUIRED => 'Network Authentication Required',
        599                                                => 'Network Connect Timeout Error',
    ];

    /**
     * Constant value to indicate throwable details (backtrace, previous
     * exceptions, etc.) should be excluded when generating a response from a
     * Throwable.
     *
     * @var bool
     */
    const EXCLUDE_THROWABLE_DETAILS = false;

    /**
     * Constant value to indicate throwable details (backtrace, previous
     * exceptions, etc.) should be included when generating a response from a
     * Throwable.
     *
     * @var bool
     */
    const INCLUDE_THROWABLE_DETAILS = true;

    /**
     * @var string[] Accept header types to match.
     */
    const NEGOTIATION_PRIORITIES = [
        'application/json',
        'application/*+json',
        'application/xml',
        'application/*+xml',
    ];

    /**
     * Factory for generating an empty response body.
     *
     * If none is provided, defaults to a closure that returns an empty
     * zend-diactoros Stream instance using a php://temp stream.
     *
     * The factory MUST return a StreamInterface
     *
     * @var callable
     */
    private $bodyFactory;

    /**
     * Whether or not to include debug details.
     *
     * Debug details are only included for responses created from throwables,
     * and include full exception details and previous exceptions and their
     * details.
     *
     * @var bool
     */
    private $isDebug;

    /**
     * JSON flags to use when generating JSON response payload.
     *
     * Defaults to JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
     *
     * @var int
     */
    private $jsonFlags;

    /**
     * Response prototype to use when generating Problem Details responses.
     *
     * Defaults to a zend-diactoros response if none is injected.
     *
     * @var ResponseInterface
     */
    private $response;

    public function __construct(
        bool $isDebug = self::EXCLUDE_THROWABLE_DETAILS,
        int $jsonFlags = null,
        ResponseInterface $response = null,
        callable $bodyFactory = null
    ) {
        $this->isDebug = $isDebug;
        $this->jsonFlags = $jsonFlags
            ?: JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION;
        $this->response = $response ?: new Response();
        $this->bodyFactory = $bodyFactory ?: Closure::fromCallable([$this, 'generateStream']);
    }

    public function createResponse(
        ServerRequestInterface $request,
        int $status,
        string $detail,
        string $title = '',
        string $type = '',
        array $additional = []
    ) : ResponseInterface {
        $status = $this->normalizeStatus($status);
        $title  = $title ?: $this->createTitleFromStatus($status);
        $type   = $type ?: $this->createTypeFromStatus($status);

        $payload = [
            'title'  => $title,
            'type'   => $type,
            'status' => $status,
            'detail' => $detail,
        ];

        if ($additional) {
            $payload = array_merge($additional, $payload);
        }

        return $this->getResponseGenerator($request)($payload);
    }

    /**
     * Create a problem-details response from a Throwable.
     */
    public function createResponseFromThrowable(
        ServerRequestInterface $request,
        Throwable $e
    ) : ResponseInterface {
        if ($e instanceof Exception\ProblemDetailsException) {
            return $this->createResponse(
                $request,
                $e->getStatus(),
                $e->getDetail(),
                $e->getTitle(),
                $e->getType(),
                $e->getAdditionalData()
            );
        }

        $additionalDetails = $this->isDebug ? $this->createThrowableDetail($e) : [];
        $code = is_int($e->getCode()) ? $e->getCode() : 0;
        return $this->createResponse(
            $request,
            $code,
            $e->getMessage(),
            '',
            '',
            $additionalDetails
        );
    }

    protected function generateJsonResponse(array $payload) : ResponseInterface
    {
        return $this->generateResponse(
            $payload['status'],
            self::CONTENT_TYPE_JSON,
            json_encode($payload, $this->jsonFlags)
        );
    }

    protected function generateXmlResponse(array $payload) : ResponseInterface
    {
        // Ensure any objects are flattened to arrays first
        $content = json_decode(json_encode($payload), true);

        $converter = new ArrayToXml($content, 'problem');
        $dom = $converter->toDom();
        $root = $dom->firstChild;
        $root->setAttribute('xmlns', 'urn:ietf:rfc:7807');

        return $this->generateResponse(
            $payload['status'],
            self::CONTENT_TYPE_XML,
            $dom->saveXML()
        );
    }

    /**
     * @throws Exception\InvalidResponseBodyException
     */
    protected function generateResponse(int $status, string $contentType, string $payload) : ResponseInterface
    {
        $body = ($this->bodyFactory)();
        if (! $body instanceof StreamInterface) {
            throw new Exception\InvalidResponseBodyException(sprintf(
                'The factory for generating a problem details response body stream did not return a %s',
                StreamInterface::class
            ));
        }

        $body->write($payload);

        return $this->response
            ->withStatus($status)
            ->withHeader('Content-Type', $contentType)
            ->withBody($body);
    }

    private function generateStream() : StreamInterface
    {
        return new Stream('php://temp', 'wb+');
    }

    private function getResponseGenerator(ServerRequestInterface $request) : callable
    {
        $accept    = $request->getHeaderLine('Accept') ?: '*/*';
        $mediaType = (new Negotiator())->getBest($accept, self::NEGOTIATION_PRIORITIES);

        return ! $mediaType || false === strpos($mediaType->getValue(), 'json')
            ? Closure::fromCallable([$this, 'generateXmlResponse'])
            : Closure::fromCallable([$this, 'generateJsonResponse']);
    }

    private function normalizeStatus(int $status) : int
    {
        if ($status < 400 || $status > 599) {
            return 500;
        }

        return $status;
    }

    private function createTitleFromStatus(int $status) : string
    {
        return self::DEFAULT_TITLE_MAP[$status] ?? 'Unknown Error';
    }

    private function createTypeFromStatus(int $status) : string
    {
        return sprintf('https://httpstatus.es/%s', $status);
    }

    private function createThrowableDetail(Throwable $e) : array
    {
        $detail = [
            'class'   => get_class($e),
            'code'    => $e->getCode(),
            'message' => $e->getMessage(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
            'trace'   => $e->getTrace(),
        ];

        $previous = [];
        while ($e = $e->getPrevious()) {
            $previous[] = [
                'class'   => get_class($e),
                'code'    => $e->getCode(),
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTrace(),
            ];
        }

        if (count($previous) > 0) {
            $detail['stack'] = $previous;
        }

        return ['exception' => $detail];
    }
}
