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
* `Optional\Result` is Ok or Err. Use it when the failure has something to
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
also for the valid document `null`. A `Result` keeps the reason:

```php
$config = Result::fromCallable(
   fn() => json_decode('{"debug": tru}', flags: JSON_THROW_ON_ERROR),
   JsonException::class
);

echo $config
   ->mapErr(fn(JsonException $e) => "bad config: {$e->getMessage()}")
   ->errOr('config ok') . "\n"; // "bad config: Syntax error"
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

A `Result` keeps the failure instead of dropping it. It starts from a throwing
call directly, and from any of the entrances above through `toResult()`, which
gives the missing value a reason:

```php
$kept = Result::fromCallable(fn() => throw new Exception("the api said no"));
echo $kept->mapErr(fn(Throwable $e) => $e->getMessage())->errOr('') . "\n";
// "the api said no"

$row = false; // what a row fetch hands back when it finds nothing
echo (string) Option::someNotFalsy($row)->toResult('row not found') . "\n"; // "Err(row not found)"
```

There is also `Option::some`, `Option::none`, `Option::someNotNull`,
`Option::fromArray`, `Option::first`, and `Result::ok` and `Result::err`.

## The middle of a chain

`map` transforms the value. `flatMap` is for a step that hands back a box of
its own. `filter` drops a value that fails a test.
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
`value()`: a name that short hides the throw.

On a `Result`, the factory is handed the error, which is how you keep the
original failure as the cause. With no factory, a `Result` rethrows an
exception it caught, so its trace still points where the failure happened:

```php
$failed = Result::fromCallable(fn(): int => throw new Exception("the api said no"));

try {
   $failed->valueOrThrow(fn(Throwable $cause) => new RuntimeException("could not load the build", 0, $cause));
} catch (RuntimeException $e) {
   echo $e->getMessage() . ": " . $e->getPrevious()?->getMessage() . "\n";
   // "could not load the build: the api said no"
}
```

## Option or Result

Use `Option` when "there isn't one" is the whole story. Use `Result` when the
caller needs to know why. They share their method names wherever the idea is
the same, and you can cross between them:

```php
$value = Result::ok(10)->toOption();          // Some(10), drops any error
$error = Option::none()->toResult('no id');   // Err(no id), gains a reason

echo $value->valueOr(-1) . " " . $error->errOr('') . "\n"; // "10 no id"
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

`Result::fromCallable` and `Result::mapSafely` catch `Throwable` by default,
because the box keeps what it caught: a `TypeError` from a bad callable
signature lands in the error, where you can still find it. `Option::fromCallable` and `Option::mapSafely` catch `Exception`
by default, because those discard what they caught, and swallowing an `Error`
would erase a real bug with nothing left to trace it by.

Every one of them takes the class to catch as its last argument, the way a
`catch` clause names one. Only that class and its subclasses are caught, and
anything else propagates. On `Result` the error is then typed as that class,
so psalm knows what you are holding:

```php
$user = Result::fromCallable(
   fn() => throw new OutOfBoundsException("no user 5"),
   OutOfBoundsException::class
);

echo $user->errOr(null)?->getMessage() . "\n"; // "no user 5"
```

## Types

psalm and PHPStan both read the annotations, at their strictest levels. Every
method that returns a box returns one type, so a chain keeps its type through
each call, and the entrances drop what they exist to drop:

* `Option::someNotNull($name)` on a `?string` is an `Option<string>`
* `Option::someNotFalsy($row)` on a row fetch's `array{id: int}|false` is an
  `Option<array{id: int}>`, shape and all
* `Result::fromCallable($fn, JsonException::class)` is a
  `Result<T, JsonException>`
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
* `Either` is gone, and `Result` replaces it. `Either::left()` is
  `Result::ok()`, `mapLeft()` is `map()`, `mapRight()` is `mapErr()`, and the
  CHANGELOG maps every other 3.x `Either` method to its `Result` spelling.
* An `Option` you cached with `serialize()` under 3.x reads back unchanged,
  and 4.0 writes the same bytes. A cached `Either` does not, since the class is
  gone.
* `Option` keeps one name per operation, so six 3.x spellings are gone:

  | 3.x | 4.0 |
  |---|---|
  | `andThen($f)` | `flatMap($f)` |
  | `filterIf($f)` | `filter($f)` |
  | `matchSome($f)`, `matchNone($f)` | `tap($f)`, `tapNone($f)` |
  | `Option::someWhen($v, $f)` | `Option::some($v)->filter($f)` |
  | `Option::noneWhen($v, $f)` | `Option::some($v)->filter(fn($x) => !$f($x))` |
* `Option`, `Result` and `MissingValueException` are `final`. Extending a box
  never worked: the static factories build with `new self`, so a subclass came
  back as a plain box on the first `map`.
* This `Optional\Result` is new in 4.0. It is not the 2.x class of the same
  name, which earlier versions of this README documented by mistake.

## Full method list

`Option` factories: `some` `none` `someNotNull` `someNotFalsy` `someNotFalse`
`fromCallable` `fromArray` `first`

`Option` methods: `hasValue` `valueOr` `valueOrThrow` `valueOrCreate`
`toNullable` `toResult`
`or` `orCreate` `else` `elseCreate` `match` `map` `mapSafely` `flatMap` `tap`
`tapNone` `filter` `notNull` `notFalsy` `notFalse` `contains` `exists`

`Result` factories: `ok` `err` `fromCallable`

`Result` methods: `isOk` `isErr` `valueOr` `valueOrCreate` `valueOrThrow`
`errOr` `toNullable` `toOption` `match` `contains` `exists` `map` `mapErr`
`mapSafely` `flatMap` `filter` `or` `orCreate` `else` `elseCreate` `tap`
`tapErr`

Every one has a worked example in its docblock, and every one of those
examples is executed by the test suite. Read them in `src/Option.php` and
`src/Result.php`.

There is no generated API site. The old one at `php7-optional.surge.sh` is
still up but nothing has rebuilt it in years: it still serves pages for 2.x
classes deleted in `b892422`, including an unrelated `Optional\Result`.

## Licence

MIT.

## Special thanks

Heavily inspired by [nlkl/Optional](https://github.com/nlkl/Optional). This
started as a port of it.
