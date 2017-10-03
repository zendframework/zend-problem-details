# Not Found Handler

This library provides a Not Found handler so that returned 404 responses are in
the problem details format.

This handler will create a problem details `Response` with a `404` status code and
a problem details body that will be rendered in either JSON or XML as required by
the request's `Accept` header.

This handler will only return a response if the request's accept header indicates
that it will accept either JSON or XML.

To use this handler in Expressive add it into your pipeline (usually `pipeline.php`)
immediate before the default `NotFoundHandler`:

```php
$app->pipe(\Zend\ProblemDetails\ProblemDetailsNotFoundHandler::class);
$app->pipe(NotFoundHandler::class);
```
