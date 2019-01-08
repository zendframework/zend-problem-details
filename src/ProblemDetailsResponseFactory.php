<?php
/**
 * @see       https://github.com/zendframework/zend-problem-details for the canonical source repository
 * @copyright Copyright (c) 2017-2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-problem-details/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\ProblemDetails;

use Closure;
use Fig\Http\Message\StatusCodeInterface as StatusCode;
use Negotiation\Negotiator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Spatie\ArrayToXml\ArrayToXml;
use Throwable;

use function array_merge;
use function array_walk_recursive;
use function get_class;
use function get_resource_type;
use function is_array;
use function is_int;
use function is_resource;
use function json_decode;
use function json_encode;
use function preg_replace;
use function print_r;
use function sprintf;
use function strpos;

use const JSON_PARTIAL_OUTPUT_ON_ERROR;
use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

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
    public const CONTENT_TYPE_JSON = 'application/problem+json';

    /**
     * @var string Content-Type header for XML responses
     */
    public const CONTENT_TYPE_XML = 'application/problem+xml';

    /**
     * @var string Default detail message to use for exceptions when the
     *     $exceptionDetailsInResponse flag is false.
     */
    public const DEFAULT_DETAIL_MESSAGE = 'An unknown error occurred.';

    /**
     * @var string[] Default problem detail titles based on status code
     */
    public const DEFAULT_TITLE_MAP = [
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
    public const EXCLUDE_THROWABLE_DETAILS = false;

    /**
     * Constant value to indicate throwable details (backtrace, previous
     * exceptions, etc.) should be included when generating a response from a
     * Throwable.
     *
     * @var bool
     */
    public const INCLUDE_THROWABLE_DETAILS = true;

    /**
     * @var string[] Accept header types to match.
     */
    public const NEGOTIATION_PRIORITIES = [
        'application/json',
        'application/*+json',
        'application/xml',
        'application/*+xml',
    ];

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
     * On non-debug mode:
     * defaults to JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
     * | JSON_PARTIAL_OUTPUT_ON_ERROR
     *
     * On debug mode:
     * defaults to JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
     * | JSON_PARTIAL_OUTPUT_ON_ERROR
     *
     * @var int
     */
    private $jsonFlags;

    /**
     * Factory to use to generate prototype response used when generating a
     * problem details response.
     *
     * @var callable
     */
    private $responseFactory;

    /**
     * Flag to enable show exception details in detail field.
     *
     * Disabled by default for security reasons.
     *
     * @var bool
     */
    private $exceptionDetailsInResponse;

    /**
     * Default detail field value. Will be visible when
     * $exceptionDetailsInResponse disabled.
     *
     * Empty string by default
     *
     * @var string
     */
    private $defaultDetailMessage;

    public function __construct(
        callable $responseFactory,
        bool $isDebug = self::EXCLUDE_THROWABLE_DETAILS,
        int $jsonFlags = null,
        bool $exceptionDetailsInResponse = false,
        string $defaultDetailMessage = self::DEFAULT_DETAIL_MESSAGE
    ) {
        // Ensures type safety of the composed factory
        $this->responseFactory = function () use ($responseFactory) : ResponseInterface {
            return $responseFactory();
        };
        $this->isDebug = $isDebug;
        if (! $jsonFlags) {
            $jsonFlags = JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_PRESERVE_ZERO_FRACTION
                | JSON_PARTIAL_OUTPUT_ON_ERROR;
            if ($isDebug) {
                $jsonFlags = JSON_PRETTY_PRINT | $jsonFlags;
            }
        }
        $this->jsonFlags = $jsonFlags;
        $this->exceptionDetailsInResponse = $exceptionDetailsInResponse;
        $this->defaultDetailMessage = $defaultDetailMessage;
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
            // ensure payload can be json_encoded
            array_walk_recursive($additional, function (&$value) {
                if (is_resource($value)) {
                    $value = print_r($value, true) . ' of type ' . get_resource_type($value);
                }
            });
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
        if ($e instanceof Exception\ProblemDetailsExceptionInterface) {
            return $this->createResponse(
                $request,
                $e->getStatus(),
                $e->getDetail(),
                $e->getTitle(),
                $e->getType(),
                $e->getAdditionalData()
            );
        }

        $detail = $this->isDebug || $this->exceptionDetailsInResponse ? $e->getMessage() : $this->defaultDetailMessage;
        $additionalDetails = $this->isDebug ? $this->createThrowableDetail($e) : [];
        $code = $this->isDebug || $this->exceptionDetailsInResponse ? $this->getThrowableCode($e) : 500;

        return $this->createResponse(
            $request,
            $code,
            $detail,
            '',
            '',
            $additionalDetails
        );
    }

    protected function getThrowableCode(Throwable $e) : int
    {
        $code = $e->getCode();

        return is_int($code) ? $code : 0;
    }

    protected function generateJsonResponse(array $payload) : ResponseInterface
    {
        return $this->generateResponse(
            $payload['status'],
            self::CONTENT_TYPE_JSON,
            json_encode($payload, $this->jsonFlags)
        );
    }

    /**
     * Ensure all keys in this associative array are valid XML tag names by replacing invalid
     * characters with an `_`.
     */
    private function cleanKeysForXml(array $input): array
    {
        $return = [];
        foreach ($input as $key => $value) {
            $key = str_replace("\n", '_', $key);
            $startCharacterPattern =
                '[A-Z]|_|[a-z]|[\xC0-\xD6]|[\xD8-\xF6]|[\xF8-\x{2FF}]|[\x{370}-\x{37D}]|[\x{37F}-\x{1FFF}]|'
                . '[\x{200C}-\x{200D}]|[\x{2070}-\x{218F}]|[\x{2C00}-\x{2FEF}]|[\x{3001}-\x{D7FF}]|[\x{F900}-\x{FDCF}]'
                . '|[\x{FDF0}-\x{FFFD}]';
            $characterPattern = $startCharacterPattern . '|\-|\.|[0-9]|\xB7|[\x{300}-\x{36F}]|[\x{203F}-\x{2040}]';

            $key = preg_replace('/(?!'.$characterPattern.')./u', '_', $key);
            $key = preg_replace('/^(?!'.$startCharacterPattern.')./u', '_', $key);

            if (is_array($value)) {
                $value = $this->cleanKeysForXml($value);
            }
            $return[$key] = $value;
        }
        return $return;
    }

    protected function generateXmlResponse(array $payload) : ResponseInterface
    {
        // Ensure any objects are flattened to arrays first
        $content = json_decode(json_encode($payload), true);

        // ensure all keys are valid XML can be json_encoded
        $cleanedContent = $this->cleanKeysForXml($content);

        $converter = new ArrayToXml($cleanedContent, 'problem');
        $dom = $converter->toDom();
        $root = $dom->firstChild;
        $root->setAttribute('xmlns', 'urn:ietf:rfc:7807');

        return $this->generateResponse(
            $payload['status'],
            self::CONTENT_TYPE_XML,
            $dom->saveXML()
        );
    }

    protected function generateResponse(int $status, string $contentType, string $payload) : ResponseInterface
    {
        $response = ($this->responseFactory)();
        $response->getBody()->write($payload);

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', $contentType);
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

        if (! empty($previous)) {
            $detail['stack'] = $previous;
        }

        return ['exception' => $detail];
    }
}
