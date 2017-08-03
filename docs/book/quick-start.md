# Quick Start

## Installation

To install this package in your application, use
[Composer](https://getcomposer.org):

```bash
$ composer require zendframework/zend-problem-details
```

## Usage

This package provides three primary mechanisms for creating and returning
Problem Details responses:

- `ProblemDetailsResponseFactory` for generating problem details responses on
  the fly from either PHP primitives or exceptions/throwables.
- `ProblemDetailsException` for creating exceptions with additional problem
  details that may be used when generating a response.
- `ProblemDetailsMiddleware` that acts as error/exception handler middleware,
  casting and throwing PHP errors as `ErrorException` instances, and all caught
  exceptions as problem details responses using the
  `ProblemDetailsResponseFactory`.

### ProblemDetailsResponseFactory

If you are using [Expressive](https://docs.zendframework.com/zend-expressive/)
and have installed [zend-component-installer](https://docs.zendframework.com/zend-component-installer)
(which is installed by default in v2.0 and above), you can write middleware that
composes the `Zend\ProblemDetails\ProblemDetailsResponseFactory` immediately, and
inject that service in your middleware.

As an example, the following catches domain excpetions and uses them to create
problem details responses:

```php
use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use Zend\Diactoros\Response\JsonResponse;
use Zend\ProblemDetails\ProblemDetailsResponseFactory;

class DomainTransactionMiddleware implements MiddlewareInterface
{
    private $domainService;

    private $problemDetailsFactory;

    public function __construct(
        DomainService $service,
        ProblemDetailsResponseFactory $problemDetailsFactory
    ) {
        $this->domainService = $service;
        $this->problemDetailsFactory = $problemDetailsFactory;
    }

    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        try {
            $result = $this->domainService->transaction($request->getParsedBody());
            return new JsonResponse($result);
        } catch (DomainException $e) {
            return $this->problemDetailsFactory->createResponseFromThrowable($request, $e);
        }
    }
}
```

The factory for the above might look like:

```php
use Psr\Container\ContainerInterface;
use Zend\ProblemDetails\ProblemDetailsResponseFactory;

class DomainTransactionMiddlewareFactory
{
    public function __invoke(ContainerInterface $container)
    {
        return new DomainTransactionMiddleware(
            $container->get(DomainService::class),
            $container->get(ProblemDetailsResponseFactory::class)
        );
    }
}
```

Another way to use the factory is to provide PHP primitives to the factory. As
an example, validation failure is an expected condition, but should likely
result in problem details to the end user.

```php
use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use Zend\Diactoros\Response\JsonResponse;
use Zend\InputFilter\InputFilterInterface;
use Zend\ProblemDetails\ProblemDetailsResponseFactory;

class DomainTransactionMiddleware implements MiddlewareInterface
{
    private $domainService;

    private $inputFilter;

    private $problemDetailsFactory;

    public function __construct(
        DomainService $service,
        InputFilterInterface $inputFilter,
        ProblemDetailsResponseFactory $problemDetailsFactory
    ) {
        $this->domainService = $service;
        $this->inputFilter = $inputFilter;
        $this->problemDetailsFactory = $problemDetailsFactory;
    }

    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        $this->inputFilter->setData($request->getParsedBody());
        if (! $this->inputFilter->isValid()) {
            return $this->problemDetailsFactory->createResponse(
                $request,
                422,
                'Domain transaction request failed validation',
                '',
                '',
                ['messages' => $this->inputFilter->getMessages()]
            );
        }

        try {
            $result =
            $this->domainService->transaction($this->inputFilter->getValues());
            return new JsonResponse($result);
        } catch (DomainException $e) {
            return $this->problemDetailsFactory->createResponseFromThrowable($request, $e);
        }
    }
}
```

The above modifies the original example to add validation and, on failed
validation, return a custom response that includes the validation failure
messages.

### Custom Exceptions

In the above examples, we have a `DomainException` that is used to create a
Problem Details response. By default, in production mode, the factory will use
the exception message as the Problem Details description, and the exception code
as the HTTP status if it falls in the 400 or 500 range (500 will be used
otherwise).

You can also create custom exceptions that provide details for the factory to
consume by implementing `Zend\ProblemDetails\Exception\ProblemDetailsException`,
which defines the following:

```php
namespace Zend\ProblemDetails\Exception;

use JsonSerializable;

interface ProblemDetailsException extends JsonSerializable
{
    public function getStatus() : int;
    public function getType() : string;
    public function getTitle() : string;
    public function getDetail() : string;
    public function getAdditionalData() : array;
    public function toArray() : array;
}
```

We also provide the trait `CommonProblemDetailsException`, which implements each
of the above, the `jsonSerialize()` method, and also defines the following
instance properties:

```php
/**
 * @var int
 */
private $status;

/**
 * @var string
 */
private $detail;

/**
 * @var string
 */
private $title;

/**
 * @var string
 */
private $type;

/**
 * @var array
 */
private $additional = [];
```

By composing this trait, you can easily define custom exception types:

```php
namespace Api;

use DomainException as PhpDomainException;
use Zend\ProblemDetails\Exception\CommonProblemDetailsException;
use Zend\ProblemDetails\Exception\ProblemDetailsException;

class DomainException extends PhpDomainException implements ProblemDetailsException
{
    use CommonProblemDetailsException;

    public static function create(string $message, array $details) : DomainException
    {
        $e = new self($message)
        $e->status = 417;
        $e->detail = $message;
        $e->type = 'https://example.com/api/doc/domain-exception';
        $e->title = 'Domain transaction failed';
        $e->additional['transaction'] = $details;
        return $e;
    }
}
```

The data present in the generated exception will then be used by the
`ProblemDetailsResponseFactory` to generate full Problem Details.

### Error handling

When writing APIs, you may not want to handle every error or exception manually,
or may not be aware of problems in your code that might lead to them. In such
cases, having error handling middleware that can generate problem details can be
handy.

This package provides `ProblemDetailsMiddleware` for that situation. It composes
a `ProblemDetailsResponseFactory`, and does the following:

- If the request can not accept either JSON or XML responses, it simply
  passes handling to the delegate.
- Otherwise, it creates a PHP error handler that converts PHP errors to
  `ErrorException` instances, and then wraps processing of the delegate in a
  try/catch block. If the delegate does not return a `ResponseInterface`, a
  `ProblemDetails\Exception\MissingResponseException` is raised; otherwise, the
  response is returned.
- Any throwable or exception caught is passed to the
  `ProblemDetailsResponseFactory::createResponseFromThrowable()` method, and the
  response generated is returned.

When using Expressive, the middleware service is already wired to a factory that
ensures the `ProblemDetailsResponseFactory` is composed. As such, you can wire
it into your workflow in several ways.

First, you can have it intercept every request:

```php
$app->pipe(ProblemDetailsMiddleware::class);
```

With Expressive, you can also segregate this to a subpath:

```php
$app->pipe('/api', ProblemDetailsMiddleware::class);
```

Finally, you can include it in a route-specific pipeline:

```php
$app->post('/api/domain/transaction', [
    ProblemDetailsMiddleware::class,
    BodyParamsMiddleware::class,
    DomainTransactionMiddleware::class,
]);
```
