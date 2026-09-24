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

Both are immutable, and PHP enforces it: they are `readonly` classes. Nothing
you call can change a box you already hold, so a method either hands you a new
one or hands back the one you gave it. On PHP 8.5, dropping that result warns,
since the call did nothing:

```text
Warning: The return value of method Optional\Option::map() should either be used
or intentionally ignored by casting it as (void), a box never changes after it
is built, so this call only made a new one
```

Every example in this file runs as part of the test suite, so if one of them is
wrong the build goes red.

## Installation

```sh
composer require ifixit/php7-optional
```

Requires PHP 8.4. For PHP 7, stay on the 3.x line.

## Why not just null?

PHP 8's `??` and `?->` handle most missing values in one line, and when they
do, use them:

```php
/** @var array<string, array{name: string}> $people */
$people = ['a1' => ['name' => 'Ada']];

echo ($people['nope']['name'] ?? 'no name') . "\n"; // "no name"
```

The boxes are for the two things `null` cannot say.

**Why it is missing.** `json_decode()` returns `null` for a syntax error, and
also for the valid document `null`. An `Either` keeps the reason:

```php
$config = Either::fromCallable(
   fn() => json_decode('{"debug": tru}', flags: JSON_THROW_ON_ERROR),
   JsonException::class
);

echo $config
   ->mapRight(fn(JsonException $e) => "bad config: {$e->getMessage()}")
   ->rightOr('config ok') . "\n"; // "bad config: Syntax error"
```

**That you looked and found nothing.** When a memo or a cache uses `null` for
"not looked up yet", a lookup that finds nothing returns `null` too, so it
runs again on every call:

```php
$lookups = 0;
/** @var Closure(): ?string $findAvatar a user with no avatar */
$findAvatar = function() use (&$lookups): ?string { $lookups++; return null; };

$avatar = null;
for ($request = 0; $request < 3; $request++) {
   $avatar ??= $findAvatar();
}

echo $lookups . "\n"; // 3
```

`Option::none()` is not `null`, so the same memo looks once:

```php
$lookups = 0;
$findAvatar = function() use (&$lookups): Option { $lookups++; return Option::none(); };

$avatar = null;
for ($request = 0; $request < 3; $request++) {
   $avatar ??= $findAvatar();
}

echo $lookups . "\n"; // 1
```

## Starting a chain

PHP says something is missing in two ways: it returns something falsy, or it
throws. There is a factory for each.

```php
// A falsy return, the shape a row fetch gives you
$none = Option::someNotFalsy(false); // None
$some = Option::someNotFalsy('Ada'); // Some('Ada')

// A throwing call
$ok = Option::fromCallable(fn() => 7);                             // Some(7)
$failed = Option::fromCallable(fn() => throw new Exception("no")); // None

echo $none->valueOr('-') . " " . $some->valueOr('-') . " "
   . $ok->valueOr(0) . " " . $failed->valueOr(0) . "\n"; // "- Ada 7 0"
```

PHP's own lookups are the exception. `strpos` and `array_search` return `0`
for a match at the start, which is falsy, so they need `someNotFalse`, which
only treats `false` as missing:

```php
echo (string) Option::someNotFalsy(array_search('a', ['a', 'b'])) . "\n"; // "None", the match is lost
echo (string) Option::someNotFalse(array_search('a', ['a', 'b'])) . "\n"; // "Some(0)"
```

`Either` has the same entrances, and keeps the failure instead of dropping it:

```php
$right = Either::notFalsyLeft(false, 'row not found');
echo $right->rightOr('') . "\n"; // "row not found"

$kept = Either::fromCallable(fn() => throw new Exception("the api said no"));
echo $kept->mapRight(fn(Throwable $e) => $e->getMessage())->rightOr('') . "\n";
// "the api said no"
```

There is also `Option::some`, `Option::none`, `Option::someNotNull`,
`Option::someWhen`, `Option::fromArray`, `Option::first`, and the matching
`Either::left`, `Either::right`, `Either::notNullLeft`, `Either::notFalseLeft`,
`Either::leftWhen` and `Either::fromArray`.

## The middle of a chain

`map` transforms the value. `andThen` (also spelled `flatMap`) is for a step
that hands back a box of its own. `filter` drops a value that fails a test.
`tap` runs a side effect without ending the chain.

```php
$total = Option::some(['price' => 5, 'qty' => 3])
   ->filter(fn(array $line) => $line['qty'] > 0)
   ->tap(fn(array $line) => error_log("pricing {$line['qty']} items"))
   ->map(fn(array $line) => $line['price'] * $line['qty'])
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

// 2. Handle both cases, named so the call reads like the two cases
echo $some->match(
   some: fn(int $n) => "got {$n}",
   none: fn() => "got nothing",
) . "\n"; // "got 41"

// 3. Throw if it is missing, saying what was missing
echo $some->valueOrThrow(fn() => new RuntimeException("no id")) . "\n"; // 41

// 4. Ask
echo $some->hasValue() ? "yes\n" : "no\n"; // "yes"
```

`valueOrThrow()` with no argument throws `MissingValueException`. With a
factory it throws what the factory builds, and the factory runs only on the
empty side, so nothing is constructed on the happy path. There is no bare
`value()`: a name that short hides the throw. On `Either` these are
`getLeftOrThrow` and `getRightOrThrow`, and the factory is handed the other
side's value, which is how you keep the original failure as the cause:

```php
$failed = Either::fromCallable(fn(): int => throw new Exception("the api said no"));

try {
   $failed->getLeftOrThrow(fn(Throwable $cause) => new RuntimeException("could not load the build", 0, $cause));
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
$result = Either::fromCallable(fn(): int => throw new Exception("the api said no"));

echo $result->isFailure() ? "failed\n" : "ok\n"; // "failed"

$message = $result
   ->mapFailure(fn(Throwable $e) => $e->getMessage())
   ->mapSuccess(fn(int $n) => "got {$n}")
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

**Pass `filter` the callable, not its result.** `filter` takes a bool or a
callable. PHP evaluates an argument at the call site, so `filter(expensive())`
runs `expensive()` even on a None, while `filter($expensive)` runs it only when
there is a value to test.

```php
$calls = 0;
$check = function() use (&$calls) { $calls++; return true; };

$eager = Option::none()->filter($check()); // $check ran, though the box is empty
$lazy = Option::none()->filter($check);    // $check did not

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

## Types

psalm and PHPStan both read the annotations, at their strictest levels. Every
method that returns a box returns one type, so a chain keeps its type through
each call, and the entrances drop what they exist to drop:

* `Option::someNotNull($name)` on a `?string` is an `Option<string>`
* `Option::someNotFalsy($row)` on a row fetch's `array{id: int}|false` is an
  `Option<array{id: int}>`, shape and all
* `Either::fromCallable($fn, JsonException::class)` is an
  `Either<T, JsonException>`
* `$option->map($f)->map($g)` is an `Option` of whatever `$g` returns

`tests/TypeInference` records what psalm infers for expressions like these,
including twenty two-call chains, and fails when one changes. PHPStan was
checked against the same expressions at level max. Its one quirk: it widens a
`false` passed to `Option::some()` into `bool`, so drop `false` with the
factories (`someNotFalse()`, `someNotFalsy()`) rather than
`some($value)->notFalse()`.

## On PHP 8.5

Two things work with no change to your code. A dropped result warns, as shown
at the top. And the pipe operator can feed a box, because a static factory is a
one-argument callable:

```text
echo $id
   |> $findName(...)
   |> Option::someNotNull(...)
   |> (fn(Option $name) => $name->map(strtoupper(...))->valueOr('nobody'));
```

That one is PHP 8.5 syntax, so it is the only example here the test suite
does not run: it also runs on 8.4, which cannot parse `|>`.

## What it costs

A library that asks you to allocate an object where you used to write `??`
should say what that buys and what it costs. `php bin/benchmark.php` measures
it, and on PHP 8.4 with no opcache it reports:

| | plain PHP | boxed | overhead |
|---|---|---|---|
| read a key that is present | 23 ns | 130 ns | 5.7x |
| read a key that is absent | 19 ns | 116 ns | 6.0x |
| two step chain | 63 ns | 350 ns | 5.6x |

So the box costs roughly 100 ns per call. Read the ratio and the absolute
number together: 5.7x sounds alarming and 100 ns usually is not. On the same
machine, decoding a small JSON object takes about 590 ns and reading a 1 KB
file about 5.5 µs. A box adds about a sixth to the first and under 2% to the
second, and a database query or an HTTP call is slower again.

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
* Boxes you cached with `serialize()` under 3.x read back unchanged, and 4.0
  writes the same bytes, so a rollback can read them too. A test holds
  payloads written by 3.0 to keep it that way.
* `filterIf`, `filterLeftIf` and `filterRightIf` are deprecated. They still
  work and print a deprecation naming the replacement: pass the callable to
  `filter`, `filterLeft` or `filterRight`.
* `Option` and `Either` are `final`, and so is `MissingValueException`.
  Extending the two boxes never worked: the static factories build with
  `new self`, so a subclass came back as a plain `Option` or `Either` on the
  first `map`.
* `Optional\Result` and `Optional\UnsafeResult` do not exist and have not since
  the 2.x line. Earlier versions of this README documented them by mistake. Use
  `Either`, whose left side is the success.

## Full method list

`Option` factories: `some` `none` `someWhen` `noneWhen` `someNotNull`
`someNotFalsy` `someNotFalse` `fromCallable` `fromArray` `first`

`Option` methods: `hasValue` `valueOr` `valueOrThrow` `valueOrCreate`
`toNullable` `toResult`
`or` `orCreate` `else` `elseCreate` `match` `matchSome` `matchNone` `map`
`mapSafely` `flatMap` `andThen` `tap` `tapNone` `filter` `notNull`
`notFalsy` `notFalse` `contains` `exists`

`Either` factories: `left` `right` `success` `failure` `leftWhen` `rightWhen`
`notNullLeft` `notNullRight` `notFalsyLeft` `notFalsyRight` `notFalseLeft`
`notFalseRight` `fromCallable` `fromArray`

`Either` methods: `isLeft` `isRight` `isSuccess` `isFailure` `mapSuccess`
`mapFailure` `successOr` `failureOr` `successOrThrow` `leftOr` `rightOr`
`getLeftOrThrow` `getRightOrThrow` `leftOrCreate` `rightOrCreate` `orLeft`
`orRight` `orCreateLeft` `orCreateRight` `elseLeft` `elseRight`
`elseCreateLeft` `elseCreateRight` `match` `matchLeft` `matchRight` `mapLeft`
`mapRight` `mapLeftSafely` `mapRightSafely` `flatMapLeft` `flatMapRight`
`andThen` `tapLeft` `tapRight`
`filterLeft` `filterRight` `leftNotNull`
`rightNotNull` `leftNotFalsy` `rightNotFalsy` `leftNotFalse` `rightNotFalse`
`leftContains` `rightContains`
`existsLeft` `existsRight` `toOptionFromLeft` `toOptionFromRight`

`Result` factories: `ok` `err` `fromCallable`

`Result` methods: `isOk` `isErr` `valueOr` `valueOrCreate` `valueOrThrow`
`errOr` `toNullable` `toOption` `match` `contains` `exists` `map` `mapErr`
`mapSafely` `flatMap` `filter` `or` `orCreate` `else` `elseCreate` `tap`
`tapErr`

Deprecated in 4.0, and still working until a major version removes them:
`filterIf`, `filterLeftIf` and `filterRightIf`. Pass the callable to `filter`,
`filterLeft` or `filterRight` instead.

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
