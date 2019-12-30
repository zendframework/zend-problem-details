# Default Types

- **Since 1.1.0.**

When you raise your own exceptions implementing `Zend\ProblemDetails\Exception\ProblemDetailsExceptionInterface` 
you will always be in control of all the properties returned as part of the
response payload, including the `status`, `type`, `title`, `detail`, etc.
items.

However, there are some use cases in which this library will have to infer some
of those values.

The main situations in which this can happen are:

- When an exception not implementing `ProblemDetailsExceptionInterface` is
  captured by the `ProblemDetailsMiddleware`.
- When the `ProblemDetailsNotFoundHandler` is executed.

In these two cases, the `title` and `type` properties will be inferred from the
status code, which will usually be `500` in the first case and `404` in the
second one.

> To be more precise, the `ProblemDetailsMiddleware` will use the exception's
> error code when `debug` is `true`, and `500` otherwise.

Because of this, in any of those cases, you will end up with values like
`https://httpstatus.es/404` or `https://httpstatus.es/500` for the `type`
property.

## Configuring custom default types

Since the `type` property will usually be used by API consumers to uniquely
identify an error, you might want to be able to provide your own custom values
for the `type` property.

In order to do that, this library lets you configure the default `type` value to
be used for every status code when some of the cases listed above happens.

```php
return [
    'problem-details' => [
        'default_types_map' => [
            404 => 'https://example.com/problem-details/error/not-found',
            500 => 'https://example.com/problem-details/error/internal-server-error',
        ],
    ],
];
```

If this configuration is found, it will be consumed by the
[ProblemDetailsResponseFactoryFactory](response.md#problemdetailsresponsefactoryfactory)
and your custom values will be used when the `type` was not explicitly provided.
