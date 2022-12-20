# php7-optional
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Build Status](https://travis-ci.org/iFixit/php7-optional.svg?branch=master)](https://travis-ci.org/iFixit/php7-optional)
[![Stable Version](https://img.shields.io/packagist/v/ifixit/php7-optional.svg)](https://packagist.org/packages/ifixit/php7-optional)
[![Downloads](https://img.shields.io/packagist/dm/ifixit/php7-optional.svg)](https://packagist.org/packages/ifixit/php7-optional)
[![codecov](https://codecov.io/gh/iFixit/php7-optional/branch/master/graph/badge.svg)](https://codecov.io/gh/iFixit/php7-optional)
[![psalm](https://shepherd.dev/github/ifixit/php7-optional/level.svg)](https://shepherd.dev/github/ifixit/php7-optional)
[![psalm](https://shepherd.dev/github/ifixit/php7-optional/coverage.svg)](https://shepherd.dev/github/ifixit/php7-optional)
[![Pull Requests](https://img.shields.io/badge/PRs-welcome-brightgreen.svg?longCache=true)](https://img.shields.io/badge/PRs-welcome-brightgreen.svg?longCache=true)


## Installation

```sh
composer require ifixit/php7-optional
```

## Usage

There are 3 main classes:

* [Optional\Option](https://php7-optional.surge.sh/Optional/Option.html)
   * Conceptually: Some or None. This is a box that optionally holds a value. Use this to replace null.
* [Optional\Either](https://php7-optional.surge.sh/Optional/Either.html)
   * Conceptually: Left or Right. This is a box that is bi-state. Can be Left, or Right. Not Both, not none. You can apply the same transformations to the left side as you can to the right.
* [Optional\Result](https://php7-optional.surge.sh/Optional/Result.html)
   * Conceptually: Okay or Error. This is a box that is bi-state, but leans towards okay state. The error state is limited as we want to make this object easy to deal with mapping or dump error.
   * All callables have `Throwable` auto wrapped and thrown at the end of the chain.
* [Optional\UnsafeResult](https://php7-optional.surge.sh/Optional/UnsafeResult.html)
   * Conceptually: Okay or Error. This is a box that is bi-state, but leans towards okay state. The error state is limited as we want to make this object easy to deal with mapping or dump error.
   * All callables do not have `Throwable` auto wrapped. Thus, an Exception will be thrown immediately.

## [Read the full docs](https://php7-optional.surge.sh/)

## Using Option

To use Optional, simply import the following namespace:

```php
use Optional\Option;
```

## Creating optional values

There are examples under `examples`.

Here is one of them which will show how fluently you can describe the chnage you want to apply.

```php
use Optional\Result;

class Curl {
   public static function request(string $url): array {
      return [
         'code' => 200,
         'url' => $url,
         'data' =>
            '{
               "environment_config": {
                 "app": {
                   "name": "Sample App",
                   "url": "http://10.0.0.120:8080/app/"
                 },
                 "database": {
                   "name": "mysql database",
                   "host": "10.0.0.120",
                   "port": 3128,
                   "username": "root",
                   "password": "toor"
                 },
                 "rest_api": "http://10.0.0.120:8080/v2/api/"
               }
             }'
      ];
   }
}

$responseToResult = function (array $response): Result {
   $wasGood = $response['code'] == 200;

   if ($wasGood) {
      return SimpleResult::okay($response);
   } else {
      $url = $response['url'];
      $code = $response['code'];
      return SimpleResult::error("The request to $url failed with code $code!");
   }
};

$response = Curl::request('http:://www.github.com');

$result = $responseToResult($response);

$dbConnectionStr = $result
   ->map(function ($result) {
      return json_decode($result['data'], true);
   })
   ->notFalsy("Json failed to decode!")
   ->map(function(array $json) {
      $dbData = $json['environment_config']['database'];

      $host = $dbData['host'];
      $port = $dbData['port'];
      $username = $dbData['username'];
      $password = $dbData['password'];

      return "Server=$host;Port=$port;Uid=$username;Pwd=$password;";
   })
   ->dataOrThrow();

echo "Connection str: $dbConnectionStr \n";


$dbConnectionResult = $result
   ->map(function ($result) {
      return false;
   })
   ->notFalsy("Json failed to decode!")
   ->map(function(array $json) {
      $dbData = $json['environment_config']['database'];

      $host = $dbData['host'];
      $port = $dbData['port'];
      $username = $dbData['username'];
      $password = $dbData['password'];

      return "Server=$host;Port=$port;Uid=$username;Pwd=$password;";
   });

   try {
      $dbConnectionResult->dataOrThrow();
   } catch (Throwable $ex) {
      // Don't want to kill the example
      echo "Example of a wrapped exception: {$ex->getMessage()}\n";
   }

   $defaultValue = $dbConnectionResult
   ->orSetDataTo('Server=myServerAddress;Port=1234;Database=myDataBase;Uid=myUsername;Pwd=myPassword;')
   ->dataOrThrow();

   echo "Example of setting to a default: $defaultValue\n";
```

## Using Either

To use Either, simply import the following namespace:

```php
use Optional\Either;
```

## Using Result

To use Result, simply import the following namespace:

```php
use Optional\Result;
```

### Result Methods

#### Creation (Boxing)
---
- static okay($data): Result
- static error(Throwable $errorData): Result
- static okayWhen($data, Throwable $errorValue, callable $filterFunc): Result
- static errorWhen($data, Throwable $errorValue, callable $filterFunc): Result
- static okayNotNull($data, Throwable $errorValue): Result
- static fromArray(array $array, $key, Throwable $rightValue = null): Result

### Flipping
---
- toError($errorValue): Result
- toOkay($dataValue): Result

### Unboxing
---
- dataOrThrow()

### State
---
- isOkay(): bool
- isError(): bool
- contains($value): bool
- errorContains($value): bool
- exists(callable $existsFunc): bool



### Transformation
---
- orSetDataTo($data): Result
- orCreateResultWithData(callable $alternativeFactory): Result
- okayOr(self $alternativeResult): Result
- createIfError(callable $alternativeResultFactory): Result
- map(callable $mapFunc): Result
- mapError(callable $mapFunc): Result
- andThen(callable $mapFunc): Result
- flatMap(callable $mapFunc): Result
- toErrorIf(callable $filterFunc, Throwable $errorValue): Result
- toOkayIf(callable $filterFunc, $data): Result
- notNull(Throwable $errorValue): Result
- notFalsy(Throwable $errorValue): Result

### Side Effect
- run(callable $dataFunc, callable $errorFunc)
- runOnOkay(callable $dataFunc): void
- runOnError(callable $errorFunc): void


# Licence
 MIT


# Special Thanks
Heavily inspired by https://github.com/nlkl/Optional. In fact this is essentially a port of this library.
