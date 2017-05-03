# Problem Details for PSR-7 Applications

[![Build Status](https://secure.travis-ci.org/weierophinney/problem-details.svg?branch=master)](https://secure.travis-ci.org/weierophinney/problem-details)
[![Coverage Status](https://coveralls.io/repos/github/weierophinney/problem-details/badge.svg?branch=master)](https://coveralls.io/github/weierophinney/problem-details?branch=master)

This library provides custom response types, error handling middleware, and
exception types for [PSR-7](http://www.php-fig.org/psr/psr-7/) applications.

## Installation

This library currently depends on an unreleased patch to willdurand/negotiation.
Your first step is adding a repository entry to your `composer.json` for
retrieving that patch:

```json
"repositories": [
    {"type": "vcs", "url": "https://github.com/weierophinney/negotiation.git"}
]
```

You will then need to provide a manual specification for the
`willdurand/negotiation` requirement within `composer.json`:

```json
"require": {
      "willdurand/negotiation": "dev-feature/plus-part-matching as 2.3.0"
}
```

Once you have made those changes, run the following to install this library:

```bash
$ composer require weierophinney/problem-details
```

## Documentation

Documentation is [in the doc tree](doc/book/), and can be compiled using [mkdocs](http://www.mkdocs.org):

```bash
$ mkdocs build
```
