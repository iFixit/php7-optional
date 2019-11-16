# php7-optional
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Build Status](https://travis-ci.org/iFixit/php7-optional.svg?branch=master)](https://travis-ci.org/iFixit/php7-optional)
[![Stable Version](https://img.shields.io/packagist/v/ifixit/php7-optional.svg)](https://packagist.org/packages/ifixit/php7-optional)
[![Downloads](https://img.shields.io/packagist/dm/ifixit/php7-optional.svg)](https://packagist.org/packages/ifixit/php7-optional)

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
      return Result::okay($response);
   } else {
      $url = $response['url'];
      $code = $response['code'];
      return Result::error("The request to $url failed with code $code!");
   }
};

$response = Curl::request('http:://www.github.com');

$result = $responseToResult($response);

$dbConnectionStr = $result
   ->map(function ($result) {
      try {
         return json_decode($result['data'], true);
      } catch(JsonDecodeException $ex) {
         return false;
      }
   })
   ->notFalsy("Json failed to decode!")
   ->map(function(array $json) {
      return $json['environment_config'];
   })
   ->map(function(array $environment_config) {
      return $environment_config['database'];
   })
   ->map(function(array $dbData) {
      $host = $dbData['host'];
      $port = $dbData['port'];
      $username = $dbData['username'];
      $password = $dbData['password'];
      return "Server=$host;Port=$port;Uid=$username;Pwd=$password;";
   })
   ->dataOr('Server=myServerAddress;Port=1234;Database=myDataBase;Uid=myUsername;Pwd=myPassword;');

echo "Connection str: $dbConnectionStr \n";
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

# Licence
 MIT


# Special Thanks
Heavily inspired by https://github.com/nlkl/Optional. In fact this is essentially a port of this library.
