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

### Factory

The `ProblemDetailsMiddleware` ships with a corresponding PSR-11 compatible factory,
`ProblemDetailsMiddlewareFactory`. This factory looks for a service named
`Zend\ProblemDetails\ProblemDetailsResponseFactory`; if present, that value is used
to instantiate the middleware.

For Expressive 2 users, this middleware should be registered automatically with
your application on install, assuming you have the zend-component-installer
plugin in place (it's shipped by default with the Expressive skeleton).
