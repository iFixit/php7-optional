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

* [Optional\Option](#using-option)
* [Optional\Either](#using-either)

## [Read the full docs](https://php7-optional.surge.sh/)

## Using Option

To use Optional, simply import the following namespace:

```php
use Optional\Option;
```

## Creating optional values

---
### Option::some($thing);
Creates a option with a boxed value

```php
$someThing = Option::some(1);
$someClass = Option::some(new SomeObject());

$someNullThing = Option::some(null); // Valid
```

---
### Option::none();
Creates a option which represents an empty box

```php
$none = Option::none();
```

---
### Option::someWhen($thing, $filterFunc);
Take a value, turn it a `Option::some($thing)` iff the `$filterFunc` returns true

```php
$positiveThing = Option::someWhen(1, function($x) { return $x > 0; });
$negativeThing = Option::someWhen(1, function($x) { return $x < 0; });
```
Note: `$filterFunc` must follow this interface `function filterFunc(mixed $value): bool`

---
### Option::noneWhen($thing, $filterFunc);
Take a value, turn it a `Option::none()` iff the `$filterFunc` returns true

```php
$positiveThing = Option::noneWhen(1, function($x) { return $x < 0; });
$negativeThing = Option::noneWhen(1, function($x) { return $x > 0; });
```

Note: `$filterFunc` must follow this interface `function filterFunc(mixed $value): bool`

## Retrieving values

Note: Since php does not have generic types it is not possible to type check the input / output match.

---
### $option->valueOr($otherValue);
Returns the options value or returns `$otherValue`

```php
$someThing = Option::some(1);
$someClass = Option::some(new SomeObject());

$none = Option::none();

$myVar = $someThing->valueOr("Some other value!"); // 1
$myVar = $someClass->valueOr("Some other value!"); // instance of SomeObject

$myVar = $none->valueOr("Some other value!"); // "Some other value!"

$none = Option::some(null)->valueOr("Some other value!"); // null, See option->notNull()
```

---
### $option->valueOrCreate($valueFactoryFunc);
Returns the options value or calls `$valueFactoryFunc` and returns the value of that function

```php
$someThing = Option::some(1);
$someClass = Option::some(new SomeObject());

$none = Option::none();

$myVar = $someThing->valueOrCreate(function() { return new NewObject(); }); // 1
$myVar = $someClass->valueOrCreate(function() { return new NewObject(); }); // instance of SomeObject

$myVar = $none->valueOrCreate(function() { return new NewObject(); }); // instance of NewObject
```

Note: `$valueFactoryFunc` must follow this interface `function valueFactoryFunc(): mixed`

## Run a function instead of retriving the value

---
### $option->match($someFunc, $noneFunc);
Runs only 1 function:

* `$someFunc` iff the option is `Option::some`
* `$noneFunc` iff the option is `Option::none`

```php
$someThing = Option::some(1);

$someThingSquared = $someThing->match(
   function($x) { return $x * $x; },    // runs iff $someThing == Option::some
   function() { return 0; }             // runs iff $someThing == Option::none
);


$configOption = Option::some($config)->notNull();

$configOption->match(
   function($x) { var_dump("Your config: {$x}"); },
   function() { var_dump("Config was missing!"); }
);
```

Note: `$someFunc` must follow this interface `function someFunc(mixed $x): mixed|void`
Note: `$noneFunc` must follow this interface `function noneFunc(): mixed|void`

---
### $option->matchSome($someFunc);
Side effect function: Runs the function iff the option is `Option::some`

```php
$configOption = Option::some($config)->notNull();

$configOption->matchSome(
   function($x) { var_dump("Your config: {$x}"); }
);
```

Note: `$someFunc` must follow this interface `function someFunc(mixed $x): mixed|void`

---
### $option->matchNone($noneFunc);
Side effect function: Runs the function iff the option is `Option::none`

```php
$configOption = Option::some($config)->notNull();

$configOption->matchNone(
   function() { var_dump("Config was missing!"); }
);
```

Note: `$noneFunc` must follow this interface `function noneFunc(): mixed|void`

## Transforming and filtering values

---
### $option->notNull();
Turn a `Option::some(null)` into an `Option::none()`

```php
$someThing = Option::some(null); // Valid
$noneThing = $someThing->notNull(); // Turn null into an none Option
```

---
### $option->or($otherValue);
Returns a `Option::some($value)` iff the the option orginally was `Option::none`

```php
$none = Option::none();
$myVar = $none->or(10); // A some instance, with value 10
```

---
### $option->orCreate($valueFactoryFunc);
Returns a `Option::some($value)` iff the the option orginally was `Option::none`

The `$valueFactoryFunc` is called lazily - iff the option orginally was `Option::none`

```php
$none = Option::none();
$myVar = $none->orCreate(function() { return 10; }); // A some instance, with value 10, but lazy
```

Note: `$valueFactoryFunc` must follow this interface `function valueFactoryFunc(): mixed`

---
### $option->else($otherOption);
iff `Option::none` return `$otherOption`, otherwise return the orginal `$option`

```php
$none = Option::none();
$myVar = $none->else(Option::some(10)); // A some instance, with value 10
$myVar = $none->else(Option::none()); // A new none instance
```

---
### $option->elseCreate($otherOptionFactoryFunc);
iff `Option::none` return the `Option` returned by `$otherOptionFactoryFunc`, otherwise return the orginal `$option`

`$otherOptionFactoryFunc` is run lazily

```php
$none = Option::none();

$myVar = $none->elseCreate(function() { return Option::some(10); }); // A some instance, with value 10, but lazy
```

Note: `$otherOptionFactoryFunc` must follow this interface `function otherOptionFactoryFunc(): Option`

---
### $option->map($mapValueFunc);
Maps the `$value` of a `Option::some($value)`

The map function runs iff the options is a `Option::some`
Otherwise the `Option:none` is propagated

```php
$none = Option::none();
$stillNone = $none->map(function($x) { return $x * $x; });

$some = Option::some(5);
$someSquared = $some->map(function($x) { return $x * $x; });
```

Note: `$mapValueFunc` must follow this interface `function mapValueFunc(mixed $value): mixed`

---
### $option->flatMap($mapFunc);


```php
$none = Option::none();
$noneNotNull = $none->flatMap(function($x) { return Option::some($x)->notNull(); });

$some = Option::some(null);
$someNotNull = $some->flatMap(function($x) { return Option::some($x)->notNull(); });
```

Note: `$mapFunc` must follow this interface `function mapFunc(mixed $value): Option`

---
### $option->filterIf($filterFunc);
Change the `Option::some($value)` into `Option::none()` iff `$filterFunc` returns false,
otherwise propigate the `Option::none()`

```php
$none = Option::none();
$stillNone = $none->filterIf(function($x) { return $x > 10; });

$some = Option::some(10);
$stillSome = $some->filterIf(function($x) { return $x == 10; });
$none = $some->filterIf(function($x) { return $x != 10; });
```

Note: `$filterFunc` must follow this interface `function filterFunc(mixed $value): bool`

---
### $option->contains($value);
Returns true if the option's value == `$value`, otherwise false.

```php
$none = Option::none();
$false = $none->contains(1);

$some = Option::some(10);
$true = $some->contains(10);
$false = $some->contains("Thing");
```

---
### $option->exists($existsFunc);
Returns true if the `$existsFunc` returns true, otherwise false.

```php
$none = Option::none();
$false = $none->exists(function($x) { return $x == 10; });

$some = Option::some(10);
$true = $some->exists(function($x) { return $x >= 10; });
$false = $some->exists(function($x) { return $x == "Thing"; });
```

Note: `$existsFunc` must follow this interface `function existsFunc(mixed $value): bool`

## Using Either

To use Either, simply import the following namespace:

```php
use Optional\Either;
```

## Creating either values
Either is just a box which has a valid some value and none value.

---
### Either::some($thing);
Creates an either with a boxed value

```php
$someThing = Either::some(1);
$someClass = Either::some(new SomeObject());

$someNullThing = Either::some(null); // Valid
```

---
### Either::none($noneValue);
Creates an either which represents an empty box

```php
$none = Either::none("This is some string to show on no value");
```

---
### Either::someWhen($someValue, $noneValue, $filterFunc);
Take a value, turn it a `Either::some($someValue)` iff the `$filterFunc` returns true
otherwise an `Either::none($noneValue)`

```php
$positiveOne = Either::someWhen(1, -1, function($x) { return $x > 0; });
$negativeOne = Either::someWhen(1, -1, function($x) { return $x < 0; });
```
Note: `$filterFunc` must follow this interface `function filterFunc(mixed $value): bool`

---
### Either::noneWhen($someValue, $noneValue, $filterFunc);
Take a value, turn it a `Either::none($noneValue)` iff the `$filterFunc` returns true
otherwise an `Either::some($someValue)`

```php
$positiveOne = Either::noneWhen(1, -1, function($x) { return $x < 0; });
$negativeOne = Either::noneWhen(1, -1, function($x) { return $x > 0; });
```

Note: `$filterFunc` must follow this interface `function filterFunc(mixed $value): bool`

## Retrieving values

Note: Since php does not have generic types it is not possible to type check the input / output match.

---
### $either->valueOr($otherValue);
Returns the either value or returns `$otherValue`

```php
$someThing = Either::some(1);
$someClass = Either::some(new SomeObject());

$none = Either::none("Error Code 123");

$myVar = $someThing->valueOr("Some other value!"); // 1
$myVar = $someClass->valueOr("Some other value!"); // instance of SomeObject

$myVar = $none->valueOr("Some other value!"); // "Some other value!"

$none = Either::some(null)->valueOr("Some other value!"); // null, See either->notNull()
```

---
### $either->valueOrCreate($valueFactoryFunc);
Returns the either's value or calls `$valueFactoryFunc` and returns the value of that function

```php
$someThing = Either::some(1);
$someClass = Either::some(new SomeObject());

$none = Either::none("Error Code 123");

$myVar = $someThing->valueOrCreate(function($noneValue) { return new NewObject(); }); // 1
$myVar = $someClass->valueOrCreate(function($noneValue) { return new NewObject(); }); // instance of SomeObject

$myVar = $none->valueOrCreate(function($noneValue) { return new NewObject(); }); // instance of NewObject
```

Note: `$valueFactoryFunc` must follow this interface `function valueFactoryFunc(mixed $noneValue): mixed`

## Run a function instead of retriving the value

---
### $either->match($someFunc, $noneFunc);
Runs only 1 function:

* `$someFunc` iff the either is `Either::some`
* `$noneFunc` iff the either is `Either::none`

```php
$someThing = Either::some(1);

$someThingSquared = $someThing->match(
   function($x) { return $x * $x; },               // runs iff $someThing == Either::some
   function($noneValue) { return $noneValue; }     // runs iff $someThing == Either::none
);


$configEither = Either::some($config)->notNull("Config was missing!");

$configEither->match(
   function($x) { var_dump("Your config: {$x}"); },
   function($errorMessage) { var_dump($errorMessage); }
);
```

Note: `$someFunc` must follow this interface `function someFunc(mixed $someValue): mixed|void`
Note: `$noneFunc` must follow this interface `function noneFunc(mixed $noneValue): mixed|void`

---
### $either->matchSome($someFunc);
Side effect function: Runs the function iff the either is `Either::some`

```php
$configEither = Either::some($config)->notNull("Config was missing!");

$configEither->matchSome(
   function($x) { var_dump("Your config: {$x}"); }
);
```

Note: `$someFunc` must follow this interface `function someFunc(mixed $someValue): mixed|void`

---
### $either->matchNone($noneFunc);
Side effect function: Runs the function iff the either is `Either::none`

```php
$configEither = Either::some($config)->notNull("Config was missing!");

$configEither->matchNone(
   function($errorMessage) { var_dump($errorMessage); }
);
```

Note: `$noneFunc` must follow this interface `function noneFunc(mixed $noneValue): mixed|void`

## Transforming and filtering values

---
### $either->notNull($noneValue);
Turn an `Either::some(null)` into an `Either::none($noneValue)`

```php
$someThing = Either::some($myVar); // Valid
$noneThing = $someThing->notNull("The var was null"); // Turn null into an Either::none($noneValue)
```

---
### $either->or($otherValue);
Returns a `Either::some($value)` iff the either orginally was `Either::none($noneValue)`

```php
$none = Either::none();
$myVar = $none->or(10); // A some instance, with value 10
```

---
### $either->orCreate($valueFactoryFunc);
Returns a `Either::some($value)` iff the the either orginally was `Either::none($noneValue)`

The `$valueFactoryFunc` is called lazily - iff the either orginally was `Either::none($noneValue)`

```php
$none = Either::none();
$myVar = $none->orCreate(function($noneValue) { return 10; }); // A some instance, with value 10, but lazy
```

Note: `$valueFactoryFunc` must follow this interface `function valueFactoryFunc(mixed $noneValue): mixed`

---
### $either->else($otherEither);
iff `Either::none($noneValue)` return `$otherEither`, otherwise return the orginal `$either`

```php
$none = Either::none("Some Error Message");
$myVar = $none->else(Either::some(10)); // A some instance, with value 10
$myVar = $none->else(Either::none("Different Error Message")); // A new none instance
```

---
### $either->elseCreate($otherEitherFactoryFunc);
iff `Either::none` return the `Either` returned by `$otherEitherFactoryFunc`, otherwise return the orginal `$either`

`$otherEitherFactoryFunc` is run lazily

```php
$none = Either::none();

$myVar = $none->elseCreate(function($noneValue) { return Either::some(10); }); // A some instance, with value 10, but lazy
```

Note: `$otherEitherFactoryFunc` must follow this interface `function otherEitherFactoryFunc($noneValue): Either`

---
### $either->map($mapValueFunc);
Maps the `$value` of a `Either::some($value)`

The map function runs iff the either's is a `Either::some`
Otherwise the `Either:none($noneValue)` is propagated

```php
$none = Either::none("Some Error Message");
$stillNone = $none->map(function($x) { return $x * $x; });

$some = Either::some(5);
$someSquared = $some->map(function($x) { return $x * $x; });
```

Note: `$mapValueFunc` must follow this interface `function mapValueFunc(mixed $someValue): mixed`

---
### $either->filterIf($filterFunc, $noneValue);
Change the `Either::some($value)` into `Either::none()` iff `$filterFunc` returns false,
otherwise propigate the `Either::none()`

```php
$none = Either::none("Some Error Message");
$stillNone = $none->filterIf(function($x) { return $x > 10; }, "New none value");

$some = Either::some(10);
$stillSome = $some->filterIf(function($x) { return $x == 10; }, "New none value");
$none = $some->filterIf(function($x) { return $x != 10; }, "New none value");
```

Note: `$filterFunc` must follow this interface `function filterFunc(mixed $value): bool`

---
### $either->contains($value);
Returns true if the either's value == `$value`, otherwise false.

```php
$none = Either::none("Some Error Message");
$false = $none->contains(1);

$some = Either::some(10);
$true = $some->contains(10);
$false = $some->contains("Thing");
```

---
### $either->exists($existsFunc);
Returns true if the `$existsFunc` returns true, otherwise false.

```php
$none = Either::none("Some Error Message");
$false = $none->exists(function($x) { return $x == 10; });

$some = Either::some(10);
$true = $some->exists(function($x) { return $x >= 10; });
$false = $some->exists(function($x) { return $x == "Thing"; });
```

Note: `$existsFunc` must follow this interface `function existsFunc(mixed $value): bool`

---
### $either->flatMap($mapFunc);

```php
$none = Either::none(null);
$noneNotNull = $none->flatMap(function($noneValue) { return Either::some($noneValue)->notNull(); });

$some = Either::some(null);
$someNotNull = $some->flatMap(function($someValue) { return Either::some($someValue)->notNull(); });
```

Note: `$mapFunc` must follow this interface `function mapFunc(mixed $value): Either`


---
### $either->toOption();
Returns an `Option` which drops the none value.

```php
$either = Either::none("Some Error Message");
$option = $either->toOption();
```

# Licence
 MIT


# Special Thanks
Heavily inspired by https://github.com/nlkl/Optional. In fact this is essentially a port of this library.
