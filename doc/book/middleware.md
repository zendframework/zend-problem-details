# Problem Details Middleware

While returning a problem details response from your own middleware is powerful
and flexible, sometimes you may want a fail-safe way to return problem details
for any exception or PHP error that occurs without needing to catch and handle
them yourself.

For this purpose, this library provides `ProblemDetailsMiddleware`.

This middleware does the following:

- Composes an `$includeThrowableDetail` flag, `false` by default, to indicate if
  throwable/exception details should be provided with responses.
- Registers a PHP error handler using the current `error_reporting` mask, and
  throwing any errors handled as `ErrorException` instances.
- Wraps a call to the `$delegate` in a `try`/`catch` block; if nothing is
  caught, and a response is returned, it returns the response immediately. If a
  response is _not_ returned, it raises a `ProblemDetails\MissingResponseException`.
- For all caught throwables, it pulls the `Accept` header and determines if the
  client can accept either JSON or XML. If it can accept neither, the exception is
  re-thrown.
- For acceptable responses, it passes the accept header, caught throwable, and
  `$includeThrowableDetail` flag to the
  `ProblemDetailsResponseFactory::createResponseFromThrowable()` method, and
  returns the generated response.

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
$app->pipe('/api', 'ProblemDetailsMiddleware::class);
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
`config` containing a `debug` key; the value of that key is then used to create
the `ProblemDetailsMiddleware` instance itself.

For Expressive 2 users, this middleware should be registered automatically with
your application on install, assuming you have the zend-component-installer
plugin in place (it's shipped by default with the Expresive skeleton).
