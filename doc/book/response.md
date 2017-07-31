# Generating Problem Details Responses

When writing middleware, you will often be able to detect error conditions
within the middleware logic. When you do, you can immediately return a problem
details response.

## ProblemDetailsResponseFactory

This library provides a factory named `Zend\ProblemDetails\ProblemDetailsResponseFactory`.
It defines two static methods, `createResponse()` and `createResponseFromThrowable()`.
Each accepts the PSR-7 `ServerRequestInterface` instance as its first argument,
and then additional arguments in order to create the response itself:

For `createResponse()`, the signature is:

```php
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

public function createResponse(
    ServerRequestInterface $request,
    int $status,
    string $detail,
    string $title = '',
    string $type = '',
    array $additional = []
) : ResponseInterface {
```

where:

- `ServerRequestInterface $request` is the current request.
- `int $status` indicates the HTTP status to return.
- `string $detail` is a short message describing the specifics of the problem.
- `string $title = ''` is a title for the general category of problem. This
  should be the same for all problems of the same type, and defaults to the
  HTTP reason phrase associated with the `$status`.
- `string $type = ''` is, generally, a URI to a human readable description of
  the general category of problem.
- `array $additional` is an associative array of additional data relevant to the
  specific problem being raised. This might be validation messages,
  transaction data, etc.

The signature of `createResponseFromThrowable()` is:

```php
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

public function createResponseFromThrowable(
    ServerRequestInterface $request,
    Throwable $e
) : ResponseInterface {
```

where:

- `ServerRequestInterface $request` is the current request.
- `Throwable $e` is an exception or throwable to use when generating problem
  details. By default, it will use the exception code for the HTTP status if it
  is in the 400-599 range, and the exception message for the detail. If the
  exception is a `ProblemDetailsException`, it will pull data via its exposed
  methods to populate the response; see the [chapter on
  exceptions](exception.md) for more details.

Normal usage of the factory will use a response and a stream from
[zend-diactoros](https://docs.zendframework.com/zend-diactoros/) for the
response prototype and response body, respectively; additionally, responses will
not include exception details (file, line number, backtrace, etc.), and JSON
responses will use a set of flags for generating human-readable JSON. If these
defaults work for your needs, you can instantiate the factory directly in your
code in order to generate a response:

```php
// From scalar data:
$response = (new ProblemDetailsResponseFactory())->createResponse(
    $request,
    400,
    'Unrecognized fields present in request'
);

// From a throwable:
$response = (new ProblemDetailsResponseFactory())
    ->createResponseFromThrowable($request, $e);
```

More often, you will want to customize behavior of the factory; for instance,
you may want it to act differently in development than in production, or provide
an alternate PSR-7 implementation. As such, the constructor has the following
signature:

```php
use Psr\Http\Message\ResponseInterface;

public function __construct(
    bool $isDebug = ProblemDetailsResponseFactory::EXCLUDE_THROWABLE_DETAILS,
    int $jsonFlags = null,
    ResponseInterface $response = null,
    callable $bodyFactory = null
) {
```

where:

- `bool $isDebug` is a flag indicating whether or not the factory should operate
  in debug mode; the default is not to. You may use the class constants
  `INCLUDE_THROWABLE_DETAILS` or `EXCLUDE_THROWABLE_DETAILS` if desired.
- `int $jsonFlags` is an integer bitmask of [JSON encoding
  constants](http://php.net/manual/en/json.constants.php) to use with
  `json_encode()` when generating JSON problem details. If you pass a `null`
  value, `JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE |
  JSON_PRESERVE_ZERO_FRACTION` will be used.
- `ResponseInterface $response` is a PSR-7 response instance to use as the base
  for any generated response.
- `callable $bodyFactory` is a PHP callable that will return a PSR-7
  `StreamInterface` instance. Since some stream implementations are mutable (for
  instance, those backed by a resource), a factory is necessary in order to
  ensure a new instance is returned. If you provide such a factory, the stream
  must be writable. The default will return a zend-diactoros `Stream` instance
  backed by a PHP temp stream in `wb+` mode.

## ProblemDetailsResponseFactoryFactory

This package also provides a factory for generating the
`ProblemDetailsResponseFactory` for usage within dependency injection containers:
`Zend\ProblemDetails\ProblemDetailsResponseFactoryFactory`. It does the following:

- If a `config` service is present:
  - If the service contains a `debug` key with a boolean value, that value is
    provided as the `$isDebug` parameter.
  - If the service contains a `problem-details` key with an array value
    containing a `json_flags` key, and that value is an integer, that value is
    provided as the `$jsonFlags` parameter.
- If a `Psr\Http\Message\ResponseInterface` service is present, that service
  will be provided as the `$response` parameter.
- If a `ProblemDetails\StreamFactory` service is present, that service will be
  provided as the `$bodyFactory` parameter.

If any of the above are not present, a `null` value will be passed, allowing the
default value to be used.

If you are using [Expressive](https://docs.zendframework.com/zend-expressive/)
and have installed [zend-component-installer](https://docs.zendframework.com/zend-component-installer)
in your application, the above factory will be wired already to the
`Zend\ProblemDetails\ProblemDetailsResponseFactory` service via the provided
`Zend\ProblemDetails\ConfigProvider` class.

## Examples

### Returning a Problem Details response

Let's say you have middleware that you know will only be used in a production
context, and need to return problem details:

```php
use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\ProblemDetails\ProblemDetailsResponseFactory;

class ApiMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        // discovered an error, so returning problem details:
        return (new ProblemDetailsResponseFactory())->createResponse(
            $request,
            403,
            'You do not have valid credentials to access ' . $request->getUri()->getPath(),
            '',
            '',
            ['login' => '/login']
        );
    }
}
```

The above will return a JSON response if the `Accept` request header matches
`application/json` or any `application/*+json` mediatype. Any other mediatype
will generate an XML response.

### Using a Throwable to create the response

Let's say you have middleware that invokes functionality from a service it
composes, and that service could raise an exception or other `Throwable`. For
this, you can use the `createResponseFromThrowable()` method instead.

```php
use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use Zend\ProblemDetails\ProblemDetailsResponseFactory;

class ApiMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        try {
            // some code that may raise an exception or throwable
        } catch (Throwable $e) {
            return (new ProblemDetailsResponseFactory())
                ->createResponseFromThrowable($request, $e);
        }
    }
}
```

As with the previous example, the above will return a JSON response if the
`Accept` request header matches `application/json` or any `application/*+json`
mediatype. Any other mediatype will generate an XML response.

By default, `createResponseFromThrowable()` will only use the exception message, and
potentially the exception code (if it falls in the 400 or 500 range). If you
want to include full exception details &mdash; line, file, backtrace, previous
exceptions &mdash; you must pass a boolean `true` as the first argument to the
constructor. In most cases, you should only do this in your development or testing
environment; as such, you would need to provide a flag to your middleware to use
when invoking the `createResponseFromThrowable()` method, or, more correctly,
pass a configured `ProblemDetailsResponseFactory` instance to your middleware's
constructor. As a more complete example:

```php
use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use Zend\ProblemDetails\ProblemDetailsResponseFactory;

class ApiMiddleware implements MiddlewareInterface
{
    private $problemDetailsFactory;

    public function __construct(
        /* other arguments*/
        ProblemDetailsResponseFactory $problemDetailsFactory)
    {
        // ...
        $this->problemDetailsFactory = $problemDetailsFactory;
    }

    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        try {
            // some code that may raise an exception or throwable
        } catch (Throwable $e) {
            return $this->problemDetailsFactory
                ->createResponseFromThrowable($request, $e);
        }
    }
}
```

### Creating Custom Response Types

If you have common problem types you will use over and over again, you may not
wish to provide the `type`, `title`, and/or `status` each time you create the
problem details. For those, we suggest creating extensions to
`ProblemDetailsResponseFactory`. To use the example from the introduction, we
could have a `RateLimitResponse` generated as follows:

```php
use Psr\Http\Message\ServerRequestInterface;
use Zend\ProblemDetails\ProblemDetailsResponseFactory;

class RateLimitResponseFactory extends ProblemDetailsResponseFactory
{
    const STATUS = 403;
    const TITLE = 'https://example.com/problems/rate-limit-exceeded';
    const TYPE = 'You have exceeded the rate limit.';

    public function create(
        ServerRequestInterface $request,
        int $tries,
        int $rateLimit,
        int $expires
    ) {
        return self::createResponse(
            $request,
            self::STATUS,
            sprintf('You have exceeded your %d requests per hour rate limit', $rateLimit),
            self::TITLE,
            self::TYPE,
            [
                'requests_this_hour' => $tries,
                'rate_limit' => $rateLimit,
                'rate_limit_reset' => date('c', $expires),
            ]
        );
    }
}
```

You would then compose this alternate factory in your middleware, and invoke it
as follows:

```php
$this->rateLimitResponseFactory->create(
    $request,
    $tries,
    $rateLimit,
    $expires
);
```
