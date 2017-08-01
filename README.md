# Problem Details for PSR-7 Applications

[![Build Status](https://secure.travis-ci.org/zendframework/zend-problem-details.svg?branch=master)](https://secure.travis-ci.org/zendframework/zend-problem-details)
[![Coverage Status](https://coveralls.io/repos/github/zendframework/zend-problem-details/badge.svg?branch=master)](https://coveralls.io/github/zendframework/zend-problem-details?branch=master)

This library provides provides a factory for generating Problem Details
responses, error handling middleware for automatically generating Problem
Details responses from errors and exceptions, and custom exception types for
[PSR-7](http://www.php-fig.org/psr/psr-7/) applications.

## Installation

Run the following to install this library:

```bash
$ composer require zendframework/zend-problem-details
```

## Documentation

Documentation is [in the doc tree](doc/book/), and can be compiled using [mkdocs](http://www.mkdocs.org):

```bash
$ mkdocs build
```

You may also [browse the documentation online](https://docs.zendframework.com/problem-details/).
