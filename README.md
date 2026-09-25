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
since the call did nothing.

Every example in this file runs as part of the test suite, and every line one
prints is checked against the comment on its `echo`. If one of them is wrong,
the build goes red. The two exceptions are shown as plain text: a fragment
that shows a bug, and one in PHP 8.5 syntax.

## Installation

```sh
composer require ifixit/php7-optional:^4.0
```

Requires PHP 8.4. On PHP 7.2 to 8.3, use 3.x, which has `Option` but not
`Result`.

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
echo Option::someNotFalsy(false), "\n"; // None
echo Option::someNotFalsy('Ada'), "\n"; // Some('Ada')

// A throwing call
echo Option::fromCallable(fn() => 7), "\n";                             // Some(7)
echo Option::fromCallable(fn() => throw new Exception("no")), "\n";     // None
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
echo $kept->mapErr(fn(Throwable $e) => $e->getMessage())->errOr('') . "\n"; // "the api said no"

$row = false; // what a row fetch hands back when it finds nothing
echo (string) Option::someNotFalsy($row)->toResult('row not found') . "\n"; // "Err('row not found')"
```

There is also `Option::some`, `Option::none`, `Option::someNotNull`,
`Option::fromArray`, `Option::first`, and `Result::ok` and `Result::err`.

## The middle of a chain

`map` transforms the value. `flatMap` is for a step that hands back a box of
its own. `filter` drops a value that fails a test. `tap` runs a side effect
without ending the chain.

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
echo $some->valueOrThrow('no id for this device') . "\n"; // 41

// 4. Ask
echo $some->hasValue() ? "yes\n" : "no\n"; // "yes"
```

`valueOrThrow()` with a message throws a `MissingValueException` saying it.
Pass a closure instead to throw your own exception; it runs only on the empty
side, so nothing is built on the happy path. There is no bare `value()`: a name
that short hides the throw.

On a `Result`, a message also names the error, and a closure is handed the
error, which is how you keep the original failure as the cause. Called with no
argument, a `Result` rethrows an exception it caught, so its trace still points
where the failure happened:

```php
$failed = Result::fromCallable(fn(): int => throw new Exception("the api said no"));

try {
   $failed->valueOrThrow(fn(Throwable $cause) => new RuntimeException("could not load the build", 0, $cause));
} catch (RuntimeException $e) {
   echo $e->getMessage() . ": " . $e->getPrevious()?->getMessage() . "\n"; // "could not load the build: the api said no"
}
```

## Steps that each need the one before

`flatMap` nests a closure for every step that depends on the last. `do()`
runs the steps as plain code instead: `valueOrReturn()` unwraps a box, and the
first empty one ends the block, like Rust's `?`.

```php
$findUser = fn(int $id) => $id > 0 ? Result::ok("user{$id}") : Result::err("no user {$id}");
$findCart = fn(string $user) => $user !== 'user13' ? Result::ok(4200) : Result::err("cart for {$user} is locked");

$owes = fn(int $id) => Result::do(function() use ($findUser, $findCart, $id) {
   $user = $findUser($id)->valueOrReturn();
   $cents = $findCart($user)->valueOrReturn();

   return "{$user} owes {$cents}";
});

echo $owes(5), "\n";  // Ok('user5 owes 4200')
echo $owes(-1), "\n"; // Err('no user -1')
echo $owes(13), "\n"; // Err('cart for user13 is locked')
```

`Option::do()` works the same way, ending with a None. psalm types each
unwrapped value exactly, so `$user` is a `string`, but the block's error type
is `mixed` unless you annotate it.

The early exit is thrown, so it reaches the nearest `do()` from inside any
function the block calls, the way a throw reaches the nearest catch. A
`catch (Throwable)` in between catches it too; catch
`Optional\Exceptions\EarlyReturn` first and rethrow it. Return the plain
value from the block: a box returned from it ends up inside another box.

## Many boxes at once

`all()` turns a batch of boxes into one: every value, keyed as given, or the
first failure. `Result::partition()` keeps going and splits the values from
the errors, which is what a form needs to report every bad field at once.

```php
$parse = fn(string $input) => Option::someNotFalse(filter_var($input, FILTER_VALIDATE_INT))
   ->toResult("not a number: {$input}");

echo Result::all(array_map($parse, ['1', '2']))->map(array_sum(...)), "\n"; // Ok(3)
echo Result::all(array_map($parse, ['1', 'x'])), "\n";                     // Err('not a number: x')

[$numbers, $errors] = Result::partition(array_map($parse, ['1', 'x', '3', 'y']));
echo implode(' ', $numbers) . ' / ' . implode(', ', $errors), "\n"; // "1 3 / not a number: x, not a number: y"
```

## Option or Result

Use `Option` when "there isn't one" is the whole story. Use `Result` when the
caller needs to know why. They share their method names wherever the idea is
the same, and you can cross between them:

```php
echo Result::ok(10)->toOption(), "\n";        // Some(10), drops any error
echo Option::none()->toResult('no id'), "\n"; // Err('no id'), gains a reason
```

## Three things that catch people out

**Pass `filter` the closure, not its result.** `filter` takes a bool or a
closure. PHP evaluates an argument at the call site, so `filter(expensive())`
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

**Your callbacks are called under strict types.** This library declares
`strict_types=1`, and PHP applies the calling file's mode, so a callback runs
strictly even when your own file does not. A database hands back `'7'` for an
integer column, and `->map(fn(int $id) => ...)` then throws a `TypeError` that
calling the same closure directly would not. Convert first, with
`->map(intval(...))`, or type the parameter as what arrives.

## What each box catches

`Result::fromCallable` and `Result::mapSafely` catch `Throwable` by default,
because the box keeps what it caught. A `TypeError` from a bad callable
signature lands in the error, where you can still find it.
`Option::fromCallable` and `Option::mapSafely` catch `Exception` by default.
They discard what they caught, and swallowing an `Error` would erase a real
bug with nothing left to trace it by.

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

## Catching a dropped Result

A `Result` nobody looks at loses its error, and the classic way to drop one is
a missing `return`:

```text
if ($input === '') {
   Result::err('empty');   // falls through, and the caller gets Ok('')
}
return Result::ok($input);
```

On PHP 8.5 the library's own factories and methods warn about that. For every
function that returns a `Result`, yours included, on any PHP version, turn on
the check for psalm or PHPStan. It reports a call whose `Result` is dropped on
its own line, the way Rust's `#[must_use]` does:

```text
<!-- psalm.xml -->
<plugins>
    <pluginClass class="Optional\StaticAnalysis\Psalm\Plugin"/>
</plugins>

# phpstan.neon
includes:
    - vendor/ifixit/php7-optional/extension.neon
```

`vendor/bin/psalm-plugin enable ifixit/php7-optional` writes the psalm lines
for you, and PHPStan's extension-installer includes the extension without
them. The plugin needs psalm 6. The extension is checked in CI with PHPStan
2, and runs on 1.12 too.

PHPStan 2 reports some of these lines on its own: a dropped call to the
library's methods as `method.resultDiscarded`, and a call it can tell has no
side effects as `method.resultUnused`. So a line can be reported twice. The
extension is for the rest, such as a function that writes to the database and
then returns its `Result`.

Assign a result to `$_` to drop it on purpose. A chain ending in `tapErr()` has
looked at the error, so it is not reported. The same plugin and extension also
type a box built in two branches as one box, as the next section explains.

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
* a box is covariant, so a function declared `Option<Animal>` can return
  `Option::some(new Dog())`, and `Option::none()` fits any `Option`

Declare what your own functions return, such as `@return Option<User>` above
a function declared `: Option`. A bare `Option` says nothing about what is in
it: psalm types the call as a bare `Option` and stops checking what it holds,
and PHPStan reports `missingType.generics` from level 6.

One shape neither tool follows on its own: a box built in two branches, such
as `$flag ? Result::ok($id) : Result::err('no')`, is a union of two boxes,
`Result<int, never>|Result<never, string>`. psalm then reports `ReservedWord`
on the next typed callback, and PHPStan types `->map(fn(int $x) => $x * 2)`
as `Result<never, string>`. The plugin and extension from the section above
type it as one box, `Result<int, string>`. Without them, declare the variable
(`/** @var Result<int, string> $x */`) or build it in a function declared to
return that type.

`tests/TypeInference` records what psalm and PHPStan (level max) infer for
expressions like these, including 18 two-call chains, and fails when either
changes. PHPStan's types are sometimes less literal than psalm's: it widens a
literal passed through a generic method, so `Option<5>` becomes `Option<int>`.
Neither tool infers `mixed` anywhere the library knows the type.

A few things to know about each tool:

* PHPStan widens `false` to `bool` the same way, so `Option::some($row)` on a
  row fetch's `array{id: int}|false` is an `Option<array{id: int}|bool>`, and
  `->notFalse()` leaves a `true` in it. Write `Option::someNotFalse($row)`,
  which both tools type as `Option<array{id: int}>`.
* psalm accepts an object with a `__toString()` where a type says `string`, so
  the `Result<int, Throwable>` that `fromCallable()` returns by default passes
  as a `Result<int, string>`. PHPStan reports it.
* PHPStan does not read `@psalm-mutation-free`, so from a `@phpstan-pure`
  function it calls every method on a box possibly impure.
* With the plugin or extension on, a union of boxes you declare yourself, such
  as `@return Result<int, never>|Result<never, string>`, is merged like any
  other. Declare `Result<int, string>`.

## On PHP 8.5

Two things work with no change to your code. A dropped result warns, since a
box never changes and the call did nothing:

```text
Warning: The return value of method Optional\Option::map() should either be used
or intentionally ignored by casting it as (void), a box never changes after it
is built, so this call changed nothing
```

And the pipe operator can feed a box, because a static factory is a
one-argument callable:

```text
echo $id
   |> $findName(...)
   |> Option::someNotNull(...)
   |> (fn(Option $name) => $name->map(strtoupper(...))->valueOr('nobody'));
```

That one is PHP 8.5 syntax, and the test suite also runs on 8.4, which cannot
parse `|>`, so the suite does not run it.

## What it costs

A library that asks you to allocate an object where you used to write `??`
should say what that buys and what it costs. `php bin/benchmark.php` measures
it, and on PHP 8.4 with no opcache it reports:

| | plain PHP | boxed | overhead |
|---|---|---|---|
| read a key that is present | 21 ns | 130 ns | 6.2x |
| read a key that is absent | 20 ns | 118 ns | 5.9x |
| two step chain | 64 ns | 348 ns | 5.4x |
| the same chain, key absent | 29 ns | 178 ns | 6.1x |

So the box costs roughly 100 ns per call, and a chain on an empty box skips
its steps without building a new box for each. Read the ratio and the absolute
number together: 6x sounds alarming and 100 ns usually is not. On the same
machine, decoding a small JSON object takes about 590 ns and reading a 1 KB
file about 5.5 µs. A box adds about a sixth to the first and under 2% to the
second, and a database query or an HTTP call is slower again.

Where it does matter is a tight loop over a large array. Boxing a million
elements adds about a tenth of a second. Reach for plain PHP there and keep
the box for the boundaries.

`do()` costs more when it ends early, because its early exit is an exception
and PHP records the whole call stack in each one. Three steps with the last
key absent take about 560 ns as a `flatMap()` chain and 1.1 µs in a `do()`
block. Fifty calls further down the stack, the gap grows from about 0.5 µs to
about 2 µs. A block that often ends early inside a tight loop is the place to
write the `flatMap()` chain instead.

Run it on your own machine rather than trusting this table; the numbers move
with the PHP version and the opcache.

## Upgrading from 3.x

* PHP 8.4 or later is required.
* `Either` is gone, and `Result` replaces it. `Either::left()` is
  `Result::ok()`, `mapLeft()` is `map()`, `mapRight()` is `mapErr()`, and the
  CHANGELOG maps every other 3.x `Either` method to its `Result` spelling.
* An `Option` you cached with `serialize()` under 3.x reads back unchanged,
  and 4.0 writes the same bytes. A cached `Either` comes back as
  `__PHP_Incomplete_Class` and throws on the first method call, so clear those
  cache entries when you deploy 4.0.
* `Option` keeps one name per operation, so six 3.x spellings are gone:

  | 3.x | 4.0 |
  |---|---|
  | `andThen($f)` | `flatMap($f)` |
  | `filterIf($f)` | `filter($f(...))` |
  | `matchSome($f)`, `matchNone($f)` | `tap($f)`, `tapNone($f)` |
  | `Option::someWhen($v, $f)` | `Option::some($v)->filter($f(...))` |
  | `Option::noneWhen($v, $f)` | `Option::some($v)->filter(fn($x) => !$f($x))` |
* `contains()` compares with `===` on both boxes, where 3.x used `==`, so
  `Option::some(1)->contains('1')` is false now.
* `Option::fromArray()` takes an `int|string` key, so a `null` key throws a
  `TypeError`.
* `Option` is `final`, as the new `Result` and `MissingValueException` are.
  Extending a box never worked: the static factories build with `new self`, so a subclass came
  back as a plain box on the first `map`.
* `(string)` on a box quotes strings and shows an object's class, so
  `Some(Ada)` is now `Some('Ada')`. Code that parses that text has to change.
* 3.x's `matchLeft($f)` was `void`. Its rewrite, `tap($f)`, returns the
  `Result`, so on its own line the dropped-Result check reports it: the error
  goes unread. Chain `tapErr()` to handle it, or assign the result to `$_`.
* psalm lets a `@psalm-pure` function or a `@psalm-immutable` class call only
  the methods that run none of your code, so `map()`, `flatMap()`, `match()`
  and the other callback methods report `ImpureMethodCall` there, and so do
  `all()`, `partition()` and `first()`, which may run a generator. 3.0 allowed
  the ones it had.
* This `Optional\Result` is new in 4.0. It is not the 2.x class of the same
  name, which earlier versions of this README documented by mistake.

The CHANGELOG lists every change, with the rest of the `Either` table.

## Full method list

`Option` factories: `some` `none` `someNotNull` `someNotFalsy` `someNotFalse`
`fromCallable` `fromArray` `first` `do` `all`

`Option` methods: `hasValue` `valueOr` `valueOrThrow` `valueOrCreate`
`valueOrReturn` `toNullable` `toResult`
`or` `orCreate` `else` `elseCreate` `match` `map` `mapSafely` `flatMap` `tap`
`tapNone` `filter` `notNull` `notFalsy` `notFalse` `contains` `exists`

`Result` factories: `ok` `err` `fromCallable` `do` `all` `partition`

`Result` methods: `isOk` `isErr` `valueOr` `valueOrCreate` `valueOrThrow`
`valueOrReturn` `errOr` `toNullable` `toOption` `match` `contains` `exists` `map` `mapErr`
`mapSafely` `flatMap` `filter` `or` `orCreate` `else` `elseCreate` `tap`
`tapErr`

Every one has a worked example in its docblock, and every one of those
examples is executed by the test suite. Read them in `src/Option.php` and
`src/Result.php`. `examples/` has three longer scripts that run on their own:
the same lookup in plain PHP and with a box, and a config reader.

There is no generated API site. The old one at `php7-optional.surge.sh` is
still up but nothing has rebuilt it in years: it still serves pages for 2.x
classes deleted in `b892422`, including an unrelated `Optional\Result`.

## Licence

MIT.

## Special thanks

Heavily inspired by [nlkl/Optional](https://github.com/nlkl/Optional). This
started as a port of it.
