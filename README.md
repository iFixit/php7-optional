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

There are 2 main classes:

* [Optional\Option](https://php7-optional.surge.sh/Optional/Option.html)
* [Optional\Either](https://php7-optional.surge.sh/Optional/Either.html)

## [Read the full docs](https://php7-optional.surge.sh/)

## Using Option

To use Optional, simply import the following namespace:

```php
use Optional\Option;
```

## Creating optional values

Here is a complex example with null

```php
$somePerson = [
   'name' => [
      'first' => 'First',
      'last' => 'Last'
   ]
];

$person = is_set($somePerson['name']) ? $somePerson['name'] : null;

if ($person) {
   return;
}

$fullName = $person['first'] . $person['last'];

try {
   $name = SomeComplexThing::doWork($fullName);
} catch (\Exception $e) {
   $name = null;
}

$user = new User($name);

if ($user) {
   var_dump("The user is {$user->name}");
} else {
   var_dump("Oh no! The user is missing!");
}

$user = $user ?: new GolbalRobotUser();
```


Here is the same complex example with optional

```php
$somePerson = [
   'name' => [
      'first' => 'First',
      'last' => 'Last'
   ]
];

$person = Option::fromArray($somePerson, 'name');

$nameOption = $person->andThen(function($person) {
   $fullName = $person['first'] . $person['last'];

   try {
      $thing = SomeComplexThing::doWork($fullName);
   } catch (\Exception $e) {
      return Option::none();
   }

   return Option::some($thing);
});

$userOption = $nameOption->map(function($name) {
   return new User($name);
});

$userOption->match(
   function ($user) { var_dump("The user is {$user->name}"); }
   function () { var_dump("Oh no! The user is missing!"); }
);

$user = $user->valueOr(new GolbalRobotUser());
```

## Using Either

To use Either, simply import the following namespace:

```php
use Optional\Either;
```

## Creating either values

# Licence
 MIT


# Special Thanks
Heavily inspired by https://github.com/nlkl/Optional. In fact this is essentially a port of this library.
