# Problem Details Exceptions

If you are developing an API, it may be useful to raise exceptions from your
business domain that contain all the information necessary to report problem
details.

To facilitate this, we provide an interface, `ProblemDetailsException`:

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

You may create exceptions that implement this interface. When such exceptions
are passed to `ProblemDetailsResponseFactory::createResponseFromThrowable()`,
these will pull the relevant details in order to create a Problem Details
response.

To facilitate creating such exception types, we also ship the trait
`CommonProblemDetailsException`. This trait defines the following properties:

- `$status`
- `$detail`
- `$title`
- `$type`
- `$additional`

and implements each of the methods of the interface. This allows you as a
developer to create implementations with either constructors or named
constructors for generating exception instances.

As an example, if you wanted to create an exception type for providing
transaction problem details, you might do so as follows:

```php
use DomainException;
use Zend\ProblemDetails\Exception\CommonProblemDetailsException;
use Zend\ProblemDetails\Exception\ProblemDetailsException;

class TransactionException extends DomainException implements ProblemDetailsException
{
    use CommonProblemDetailsException;

    const STATUS = 403;
    const TYPE = 'https://example.com/problems/insufficient-funds';
    const TITLE = 'You have insufficient funds to complete the transaction.';

    public static function create(int $needed, float $balance, string $account) : self
    {
        $e = new self(sprintf(
            'Your transaction required %01.2f, but you only have %01.2f in your account',
            $needed,
            $balance
        ));
        $e->status = self::STATUS;
        $e->type   = self::TYPE;
        $e->title  = self::TITLE;
        $e->additional = [
            'account' => $account,
            'balance' => $balance,
        ];

        return $e;
    }
}
```

You might then raise the exception as follows:

```php
throw TransactionException::create($price, $balance, $accountUri);
```

And it might result in the following:

```json
{
    "status": 403,
    "type": "https://example.com/problems/insufficient-funds",
    "title": "You have insufficient funds to complete the transaction.",
    "detail": "Your transaction required 5.63, but you only have 1.37 in your account",
    "account": "https://example.com/api/accounts/12345",
    "balance": 1.37
}
```

The benefit to this approach is that you can easily provide domain-specific
exceptions throughout your application that can, as a side-effect, be
re-purposed immediately to provide problem details in your application.
