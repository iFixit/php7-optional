# php7-optional

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Tests](https://github.com/iFixit/php7-optional/actions/workflows/test.yaml/badge.svg?branch=master)](https://github.com/iFixit/php7-optional/actions/workflows/test.yaml)
[![Stable Version](https://img.shields.io/packagist/v/ifixit/php7-optional.svg)](https://packagist.org/packages/ifixit/php7-optional)
[![Downloads](https://img.shields.io/packagist/dm/ifixit/php7-optional.svg)](https://packagist.org/packages/ifixit/php7-optional)
[![psalm](https://shepherd.dev/github/ifixit/php7-optional/level.svg)](https://shepherd.dev/github/ifixit/php7-optional)
[![PRs welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg)](https://github.com/iFixit/php7-optional/pulls)

Two boxes that hold a value so a caller cannot forget the case where there
isn't one.

* `Optional\Option` is Some or None. Use it instead of returning `null`.
* `Optional\Either` is Left or Right. Use it when the failure has something to
  say for itself.

Both are immutable. Nothing you call can change a box you already hold, so a
method either hands you a new one or hands back the one you gave it.

Every example in this file runs as part of the test suite, so if one of them is
wrong the build goes red.

## Installation

```sh
composer require ifixit/php7-optional
```

Requires PHP 8.4. For PHP 7, stay on the 3.x line.

## The shortest useful example

Without the box, the null check leaks into every caller:

```php
$people = ['a1' => ['name' => 'Ada']];

$person = $people['nope'] ?? null;
$name = $person === null ? null : ($person['name'] ?? null);

echo $name === null ? "no name\n" : strtoupper($name) . "\n";
```

With it, the caller says what it wants and the empty case takes care of itself:

```php
$people = ['a1' => ['name' => 'Ada']];

$name = Option::fromArray($people, 'nope')
   ->andThen(function(array $person) { return Option::fromArray($person, 'name'); })
   ->map('strtoupper')
   ->valueOr('no name');

echo $name . "\n"; // "no name"
```

## Starting a chain

PHP says something is missing in two ways: it returns something falsy, or it
throws. There is a factory for each.

```php
// A falsy return, the shape a row fetch or a lookup gives you
$none = Option::someNotFalsy(false); // None
$some = Option::someNotFalsy('Ada'); // Some('Ada')

// A throwing call
$ok = Option::fromCallable(function() { return 7; });                      // Some(7)
$failed = Option::fromCallable(function() { throw new Exception("no"); }); // None

echo $none->valueOr('-') . " " . $some->valueOr('-') . " "
   . $ok->valueOr(0) . " " . $failed->valueOr(0) . "\n"; // "- Ada 7 0"
```

`Either` has the same two, and keeps the failure instead of dropping it:

```php
$right = Either::notFalsyLeft(false, 'row not found');
echo $right->rightOr('') . "\n"; // "row not found"

$kept = Either::fromCallable(function() { throw new Exception("the api said no"); });
echo $kept->mapRight(function(Throwable $e) { return $e->getMessage(); })->rightOr('') . "\n";
// "the api said no"
```

There is also `Option::some`, `Option::none`, `Option::someNotNull`,
`Option::someWhen`, `Option::fromArray`, and the matching `Either::left`,
`Either::right`, `Either::notNullLeft`, `Either::leftWhen`, `Either::fromArray`.

## The middle of a chain

`map` transforms the value. `andThen` (also spelled `flatMap`) is for a step
that hands back a box of its own. `filterIf` drops a value that fails a test.
`tap` runs a side effect without ending the chain.

```php
$total = Option::some(['price' => 5, 'qty' => 3])
   ->filterIf(function(array $line) { return $line['qty'] > 0; })
   ->tap(function(array $line) { error_log("pricing {$line['qty']} items"); })
   ->map(function(array $line) { return $line['price'] * $line['qty']; })
   ->valueOr(0);

echo $total . "\n"; // 15
```

Nothing after an empty box runs:

```php
$ran = false;

$total = Option::none()
   ->map(function() use (&$ran) { $ran = true; return 1; })
   ->valueOr(0);

echo $total . "\n";                  // 0
echo $ran ? "ran\n" : "never ran\n"; // "never ran"
```

## Ending a chain

Four ways out, in rough order of how often you want them.

```php
$some = Option::some(41);

// 1. Supply a fallback
echo $some->valueOr(0) . "\n"; // 41

// 2. Handle both cases
echo $some->match(
   function(int $n) { return "got {$n}"; },
   function() { return "got nothing"; }
) . "\n"; // "got 41"

// 3. Throw, saying what was missing
echo $some->valueOrThrow(function() { return new RuntimeException("no id"); }) . "\n"; // 41

// 4. Ask
echo $some->hasValue() ? "yes\n" : "no\n"; // "yes"
```

`valueOrThrow` takes a factory rather than a built exception, so nothing is
constructed on the happy path. On `Either` these are `getLeftOrThrow` and
`getRightOrThrow`, and the factory is handed the other side's value, which is
how you keep the original failure as the cause:

```php
$failed = Either::fromCallable(function(): int { throw new Exception("the api said no"); });

try {
   $failed->getLeftOrThrow(function(Throwable $cause) {
      return new RuntimeException("could not load the build", 0, $cause);
   });
} catch (RuntimeException $e) {
   echo $e->getMessage() . ": " . $e->getPrevious()?->getMessage() . "\n";
   // "could not load the build: the api said no"
}
```

## Naming the sides

`Either` is symmetric, and plenty of uses have nothing to do with success. But
this library already leans left for the success case: `andThen` is
`flatMapLeft`, and `fromCallable` puts the return value on the left with the
`Throwable` on the right.

When you are modelling success and failure, say so. These are the same box with
clearer names, and they add no behavior:

```php
$result = Either::fromCallable(function(): int { throw new Exception("the api said no"); });

echo $result->isFailure() ? "failed\n" : "ok\n"; // "failed"

$message = $result
   ->mapFailure(function(Throwable $e) { return $e->getMessage(); })
   ->mapSuccess(function(int $n) { return "got {$n}"; })
   ->successOr('no value');

echo $message . "\n"; // "no value"
```

`success` and `failure` build them, `isSuccess` and `isFailure` ask, `mapSuccess`
and `mapFailure` transform, and `successOr`, `failureOr` and `successOrThrow` get
you out. Everything else is spelled left and right.

`match()` keeps its `left:` and `right:` parameter names. Renaming them would
break every caller passing them as named arguments.

## Option or Either

Use `Option` when "there isn't one" is the whole story. Use `Either` when the
caller needs to know why. You can cross between them:

```php
$value = Either::left(10)->toOptionFromLeft();          // Some(10), drops the error
$error = Either::right('bad id')->toOptionFromRight();  // Some('bad id')

echo $value->valueOr(-1) . " " . $error->valueOr('none') . "\n"; // "10 bad id"
```

## Two things that catch people out

**`filter` takes a bool, `filterIf` takes a callable.** PHP evaluates an
argument at the call site, so `filter(expensive())` runs `expensive()` even on
a None. Reach for `filterIf` unless you already have the bool in hand.

```php
$calls = 0;
$check = function() use (&$calls) { $calls++; return true; };

Option::none()->filter($check()); // $check ran
Option::none()->filterIf($check); // $check did not

echo $calls . "\n"; // 1
```

**`some(null)` is a real Some.** The box is honest about a stored null.
Collapse it when you want to.

```php
echo Option::some(null)->hasValue() ? "some\n" : "none\n";            // "some"
echo Option::some(null)->notNull()->hasValue() ? "some\n" : "none\n"; // "none"
```

## What each box catches

`Either::fromCallable`, `Either::mapLeftSafely` and `Either::mapRightSafely`
catch `Throwable` by default, because the box keeps what it caught: a
`TypeError` from a bad callable signature lands on the right, where you can
still find it. `Option::fromCallable` and `Option::mapSafely` catch `Exception`
by default, because those discard what they caught, and swallowing an `Error`
would erase a real bug with nothing left to trace it by.

Every one of them takes the class to catch as its last argument, the way a
`catch` clause names one. Only that class and its subclasses are caught, and
anything else propagates. On `Either` the right side is then typed as that
class, so psalm knows what you are holding:

```php
$user = Either::fromCallable(
   fn() => throw new OutOfBoundsException("no user 5"),
   OutOfBoundsException::class
);

echo $user->rightOr(null)?->getMessage() . "\n"; // "no user 5"
```

## What it costs

A library that asks you to allocate an object where you used to write `??`
should say what that buys and what it costs. `php bin/benchmark.php` measures
it, and on PHP 8.4 with no opcache it reports:

| | plain PHP | boxed | overhead |
|---|---|---|---|
| read a key that is present | 23 ns | 123 ns | 5.4x |
| read a key that is absent | 20 ns | 108 ns | 5.5x |
| two step chain | 63 ns | 328 ns | 5.2x |

So the box costs roughly 100 ns per call. Read the ratio and the absolute
number together: 5.4x sounds alarming and 100 ns usually is not, because the
work it wraps is almost always larger by orders of magnitude.

Where it does matter is a tight loop over a large array. Boxing a million
elements adds about a tenth of a second. Reach for plain PHP there and keep
the box for the boundaries.

Run it on your own machine rather than trusting this table; the numbers move
with the PHP version and the opcache.

## Upgrading from 3.x

* PHP 8.4 or later is required.
* `Either::fromArray` uses `array_key_exists` rather than `isset`, so a key
  holding `null` is now a `Left(null)` instead of a Right. `Option::fromArray`
  always behaved this way and the two now agree. Chain `leftNotNull()` for the
  old behavior.
* `Either::mapLeftSafely` catches `Throwable` where 3.x caught `Exception`. An
  `Error` that used to escape and crash now lands on the right and travels down
  the chain, so a caller relying on it crashing needs to check the right side.
  `Option::mapSafely` is unchanged and still catches `Exception` only.
* `Option` and `Either` are `final`, and so is `MissingValueException`.
  Extending the two boxes never worked: the static factories build with
  `new self`, so a subclass came back as a plain `Option` or `Either` on the
  first `map`.
* `Optional\Result` and `Optional\UnsafeResult` do not exist and have not since
  the 2.x line. Earlier versions of this README documented them by mistake. Use
  `Either`, whose left side is the success.

## Full method list

`Option` factories: `some` `none` `someWhen` `noneWhen` `someNotNull`
`someNotFalsy` `fromCallable` `fromArray` `first`

`Option` methods: `hasValue` `valueOr` `value` `valueOrThrow` `valueOrCreate`
`toNullable`
`or` `orCreate` `else` `elseCreate` `match` `matchSome` `matchNone` `map`
`mapSafely` `flatMap` `andThen` `tap` `tapNone` `filter` `filterIf` `notNull`
`notFalsy` `contains` `exists`

`Either` factories: `left` `right` `success` `failure` `leftWhen` `rightWhen`
`notNullLeft` `notNullRight` `notFalsyLeft` `notFalsyRight` `fromCallable`
`fromArray`

`Either` methods: `isLeft` `isRight` `isSuccess` `isFailure` `mapSuccess`
`mapFailure` `successOr` `failureOr` `successOrThrow` `leftOr` `rightOr` `getLeft`
`getRight`
`getLeftOrThrow` `getRightOrThrow` `leftOrCreate` `rightOrCreate` `orLeft`
`orRight` `orCreateLeft` `orCreateRight` `elseLeft` `elseRight`
`elseCreateLeft` `elseCreateRight` `match` `matchLeft` `matchRight` `mapLeft`
`mapRight` `mapLeftSafely` `mapRightSafely` `flatMapLeft` `flatMapRight`
`andThen` `tapLeft` `tapRight`
`filterLeft` `filterRight` `filterLeftIf` `filterRightIf` `leftNotNull`
`rightNotNull` `leftNotFalsy` `rightNotFalsy` `leftContains` `rightContains`
`existsLeft` `existsRight` `toOptionFromLeft` `toOptionFromRight`

Every one carries a worked example in its docblock, and every one of those
examples is executed by the test suite. Read them in `src/Option.php` and
`src/Either.php`.

There is no generated API site. The old one at `php7-optional.surge.sh` is
still up but nothing has rebuilt it in years: it still serves pages for
`Optional\Result` and `Optional\SimpleResult`, which were deleted in
`b892422`.

## Licence

MIT.

## Special thanks

Heavily inspired by [nlkl/Optional](https://github.com/nlkl/Optional). This
started as a port of it.
