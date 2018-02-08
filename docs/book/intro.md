# Problem Details

This library addresses [RFC 7807: Problem Details for HTTP APIs](https://tools.ietf.org/html/rfc7807)
for usage with [PSR-7 HTTP Messages](https://www.php-fig.org/psr/psr-7/) and
[PSR-15 HTTP Handlers](https://www.php-fig.org/psr/psr-15/).

## Problem Details for HTTP APIs

When developing APIs, it is good practice to:

- Use HTTP status codes to help convey error status.
- Provide sufficient error detail to clients.

Unfortunately, unless you are using a documented
[RPC](https://en.wikipedia.org/wiki/Remote_procedure_call) format such as
XML-RPC, JSON-RPC, or SOAP, _how_ to return error details is not dictated, and
many API developers end up creating their own formats. Most standardized
RPC formats do not use the HTTP status code to convey an error, only the
payload, and, in fact, most clients of such services will fail if a non-200
status is returned.

RFC 7807 provides a standard format for returning problem details from HTTP
APIs. In particular, it specifies the following:

- Error responses MUST use standard HTTP status codes in the 400 or 500 range to
  detail the general category of error.
- Error responses will be of the `Content-Type` `application/problem`,
  appending a serialization format of either `json` or `xml`:
  `application/problem+json`, `application/problem+xml`.
- Error responses will have each of the following keys:
  - `detail`, a human-readable description of the specific error.
  - `type`, a unique URI for the general error type, generally pointing to
    human-readable documentation of that given type.
  - `title`, a short, human-readable title for the general error type; the title
    should not change for given `type`s.
  - `status`, conveying the HTTP status code; this is so that all information
    is in one place, but also to correct for changes in the status code due to
    usage of proxy servers.

Optionally, an `instance` key may be present, with a unique URI for the specific
error; this will often point to an error log for that specific response.

Finally, problem details are _extensible_. You may provide additional keys that
give the consumer more information about the error. As an example, in an API
that has rate limiting, you may want to indicate how many requests the user has
made, what the rate limit is, and when the limit resets:

```json
{
    "type": "https://example.com/problems/rate-limit-exceeded",
    "title": "You have exceeded your API rate limit.",
    "detail": "You have hit your rate limit of 5000 requests per hour.",
    "requests_this_hour": 5025,
    "rate_limit": 5000,
    "rate_limit_reset": "2017-05-03T14:39-0500"
}
```

## Custom errors

What if you have custom error types?

RFC 7807 specifically allows you to define these with the following:

- A URI to documentation of the error _type_.
- A human-readable _title_ describing the error type.
- One or more HTTP _status_ codes associated with the error type.

For your custom errors, you use the above with a problem details response; if
the problem type requires additional information, you provide it within the
payload, and document that information at the URI describing the type.

This approach allows usage of a single, general-purpose media type for returning
problem details for your HTTP API, while allowing full customization of what
types of errors you report.

## ProblemDetails

This library provides custom PSR-7 responses for JSON and XML representations of
`application/problem`. Additionally, it provides a factory that will introspect
the contents of a provided `Accept` header in order to determine which
representation to return, defaulting to the XML representation. This factory may
then be composed in middleware in order to create and return problem details
responses.

Additionally, the library provides middleware that acts as an error and
exception handler and wrapping calls to a request handler, converting each into problem
details responses.
