# Problem Details Responses

When writing middleware, you will often be able to detect error conditions
within the middleware logic. When you do, you can immediately return a problem
details response.

This library provides the following:

- `ProblemDetailsResponse` is an interface defining two static methods, `create`
  and `createFromThrowable()`.
- `ProblemDetailsJsonResponse` implements `ProblemDetailsResponse` and extends
  `Zend\Diactoros\Response\JsonResponse` in order to provide a JSON
  representation of problem details.
- `ProblemDetailsXmlResponse` implements `ProblemDetailsResponse` and extends
  `Zend\Diactoros\Response\TextResponse` in order to provide an XML
  representation of problem details.
- `ProblemDetailsResponseFactory` defines two static methods, `createResponse()`
  and `createResponseFromThrowable()`. Each accepts the same arguments as the
  `create()` and `createFromThrowable()` methods of `ProblemDetailsResponse`,
  respectively, but with an additional initial argument, `$accept`, representing
  an `Accept` header to negotiate in order to determine which specific response
  type to create.

The signature of `ProblemDetailsResponse` is as follows:

```php
namespace ProblemDetails;

use Throwable;

interface ProblemDetailsResponse
{
    const INCLUDE_THROWABLE_DETAILS = true;
    const EXCLUDE_THROWABLE_DETAILS = false;

    public static function create(
        int $status,
        string $detail,
        string $title = '',
        string $type = '',
        array $additional = []
    ) : ProblemDetailsResponse;

    public static function createFromThrowable(
        Throwable $e,
        bool $includeThrowable = self::EXCLUDE_THROWABLE_DETAILS
    ) : ProblemDetailsResponse;
}
```

While that for `ProblemDetailsResponseFactory` is this:

```php
namespace ProblemDetails;

use Negotiation\Negotiator;
use Throwable;

class ProblemDetailsResponseFactory
{
    public static function createResponse(
        string $accept,
        int $status,
        string $detail,
        string $title = '',
        string $type = '',
        array $additional = []
    ) : ProblemDetailsResponse;

    public static function createResponseFromThrowable(
        string $accept,
        Throwable $e,
        bool $includeThrowable = ProblemDetailsResponse::EXCLUDE_THROWABLE_DETAILS
    ) : ProblemDetailsResponse;
}
```

## Examples

### Returning a JSON or XML response

Let's say you have middleware that you know will only be used in a JSON context,
and need to return problem details:

```php
use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface;
use ProblemDetails\ProblemDetailsJsonResponse;
use Psr\Http\Message\ServerRequestInterface;

class ApiMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        // discovered an error, so returning problem details:
        return ProblemDetailsJsonResponse::create(
            403,
            'You do not have valid credentials to access ' . $request->getUri()->getPath(),
            '',
            '',
            ['login' => '/login']
        );
    }
}
```

If you wanted to return an XML version instead, you would replace
`ProblemDetailsJsonResponse` with `ProblemDetailsXmlResponse` in the above
example.

### Using a Throwable to create the response

Let's say you have middleware that invokes functionality from a service it
composes, and that service could raise an exception or other `Throwable`. For
this, you can use the `createFromThrowable()` method instead.

```php
use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface;
use ProblemDetails\ProblemDetailsJsonResponse;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

class ApiMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        try {
            // some code that may raise an exception or throwable
        } catch (Throwable $e) {
            return ProblemDetailsJsonResponse::createFromThrowable($e);
        }
    }
}
```

As with the previous example, for an XML serialization, you would substitute
`ProblemDetailsXmlResponse` for `ProblemDetailsJsonResponse`.

By default, `createFromThrowable()` will only use the exception message, and
potentially the exception code (if it falls in the 400 or 500 range). If you
want to include full exception details &mdash; line, file, backtrace, previous
exceptions &mdash; you must pass a boolean `true` as the second argument to the
method. In most cases, you should only do this in your development or testing
environment; as such, you would need to provide a flag to your middleware to use
when invoking the `createFromThrowable()` method. As a more complete example:

```php
use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface;
use ProblemDetails\ProblemDetailsJsonResponse;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

class ApiMiddleware implements MiddlewareInterface
{
    private $debug;

    public function __construct(/* other arguments*/ boolean $debug = false)
    {
        // ...
        $this->debug = $debug;
    }

    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        try {
            // some code that may raise an exception or throwable
        } catch (Throwable $e) {
            return ProblemDetailsJsonResponse::createFromThrowable($e, $this->debug);
        }
    }
}
```

### Varying the serialization based on Accept header

If your API should respond to either JSON or XML requests, you will need to use
the `ProblemDetailsResponseFactory` to create the response. To do so, you will
pull the `Accept` header from the request when passing it to the factory.

```php
use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface;
use ProblemDetails\ProblemDetailsResponseFactory;
use Psr\Http\Message\ServerRequestInterface;

class ApiMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        // discovered an error, so returning problem details:
        return ProblemDetailsResponseFactory::createResponse(
            $request->getHeaderLine('Accept'),
            403,
            'You do not have valid credentials to access ' . $request->getUri()->getPath(),
            '',
            '',
            ['login' => '/login']
        );
    }
}
```

If you wish to use a `Throwable` or `Exception` to create the response, you
would use `createResponseFromThrowable()`; just like `createFromThrowable()`
this method also accepts the flag for whether or not to include
exception/throwable details.

```php
use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface;
use ProblemDetails\ProblemDetailsResponseFactory;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

class ApiMiddleware implements MiddlewareInterface
{
    private $debug;

    public function __construct(/* other arguments*/ boolean $debug = false)
    {
        // ...
        $this->debug = $debug;
    }

    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        try {
            // some code that may raise an exception or throwable
        } catch (Throwable $e) {
            return ProblemDetailsResponseFactory::createResponseFromThrowable(
                $request->getHeaderLine('Accept'),
                $e,
                $this->debug
            );
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
use ProblemDetails\ProblemDetailsResponseFactory;

class RateLimitResponseFactory extends ProblemDetailsResponseFactory
{
    const STATUS = 403;
    const TITLE = 'https://example.com/problems/rate-limit-exceeded';
    const TYPE = 'You have exceeded the rate limit.';

    public static function create(
        string $accept,
        int $tries,
        int $rateLimit,
        int $expires
    ) {
        return self::createResponse(
            $accept,
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

You would then create and return your response as follows:

```php
RateLimitResponseFactory::create(
    $request->getHeaderLine('Accept'),
    $tries,
    $rateLimit,
    $expires
);
```
