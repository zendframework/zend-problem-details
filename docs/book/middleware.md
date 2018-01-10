# Problem Details Middleware

While returning a problem details response from your own middleware is powerful
and flexible, sometimes you may want a fail-safe way to return problem details
for any exception or PHP error that occurs without needing to catch and handle
them yourself.

For this purpose, this library provides `ProblemDetailsMiddleware`.

This middleware does the following:

- Composes a `ProblemDetailsResponseFactory`; if none is passed during
  instantiation, one is created with no arguments, defaulting to usage of
  zend-diactoros for response and response body generation, and defaulting to
  production settings.
- Determines if the request accepts JSON or XML; if neither is accepted, it
  simply passes execution to the delegate.
- Registers a PHP error handler using the current `error_reporting` mask, and
  throwing any errors handled as `ErrorException` instances.
- Wraps a call to the `$delegate` in a `try`/`catch` block; if nothing is
  caught, and a response is returned, it returns the response immediately. If a
  response is _not_ returned, it raises a
  `ProblemDetails\Exception\MissingResponseException`.
- For all caught throwables, it passes the throwable to
  `ProblemDetailsResponseFactory::createResponseFromThrowable()` to generate a
  Problem Details response.

As such, you can register this in middleware stacks in order to automate
generation of problem details for exceptions and PHP errors.

As an example, using Expressive, you could compose it as an error handler within
your application pipeline, having it handle _all_ errors and exceptions from
your application:

```php
$app->pipe(ProblemDetailsMiddleware::class);
```

Or for a subpath of your application:

```php
$app->pipe('/api', ProblemDetailsMiddleware::class);
```

Alternately, you could pipe it within a routed-middleware pipeline:

```php
$app->get('/api/books', [
    ProblemDetailsMiddleware::class,
    BooksList::class,
], 'books');
```

This latter approach ensures that you are only providing problem details for
specific API endpoints, which can be useful when you have a mix of APIs and
traditional web content in your application.

## Listeners

- Since 0.5.2

The `ProblemDetailsMiddleware` allows you to register _listeners_ to trigger
when it handles a `Throwable`. Listeners are PHP callables, and the middleware
triggers them with the following arguments, in the following order:

- `Throwable $error`: the throwable/exception caught by the middleware.
- `ServerRequestInterface $request`: the request as provided to the
  `ProblemDetailsMiddleware`.
- `ResponseInterface $response`: the response the `ProblemDetailsMiddleware`
  generated based on the `$error`.

Note that each of these arguments are immutable; you cannot change the state in
a way that that state will propagate meaningfully. As such, you should use
listeners for reporting purposes only (e.g., logging).

As an example:

```php
// Where $logger is a PSR-3 logger implementation
$listener = function (
    Throwable $error,
    ServerRequestInterface $request,
    ResponseInterface $response
) use ($logger) {
    $logger->error('[{status}] {method} {uri}: {message}', [
        'status'  => $response->getStatusCode(),
        'method'  => $request->getMethod(),
        'uri'     => (string) $request->getUri(),
        'message' => $error->getMessage(),
    ]);
};
```

Attach listeners to the `ProblemDetailsMiddleware` instance using its
`attachListener()` method:

```php
$middleware->attachListener($listener);
```

## Factory

The `ProblemDetailsMiddleware` ships with a corresponding PSR-11 compatible factory,
`ProblemDetailsMiddlewareFactory`. This factory looks for a service named
`Zend\ProblemDetails\ProblemDetailsResponseFactory`; if present, that value is used
to instantiate the middleware.

For Expressive 2 users, this middleware should be registered automatically with
your application on install, assuming you have the zend-component-installer
plugin in place (it's shipped by default with the Expressive skeleton).

### Registering listeners

- Since 0.5.2

In order to register listeners, we recommend using a
[delegator factory](https://docs.zendframework.com/zend-expressive/features/container/delegator-factories/)
on the `Zend\ProblemDetails\ProblemDetailsMiddleware` service.

As an example:

```php
class LoggerProblemDetailsListenerDelegator
{
    public function __construct(ContainerInterface $container, $serviceName, callable $callback)
    {
        $middleware = $callback();
        $middleware->attachListener($container->get(LoggerProblemDetailsListener::class));
        return $middleware;
    }
}
```

You would then register this as a delegator factory in your configuration:

```php
'delegators' => [
    ProblemDetailsMiddleware::class => [
        LoggerProblemDetailsListenerDelegator::class,
    ],
],
```
