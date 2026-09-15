# php7-optional

Two immutable boxes that replace `null` and error returns: `Optional\Option`
(Some or None) and `Optional\Either` (Left or Right). This is a standalone
Composer package published to Packagist as `ifixit/php7-optional`. Consumers
install it with composer, so nothing you change here reaches any of them until
someone cuts a tag.

The whole library is three source files (`src/Option.php`, `src/Either.php`,
`src/Exceptions/MissingValueException.php`). Read them directly. This document
covers only what reading them will not tell you, or will tell you wrong.

## master is not what consumers get

The released package and the code in front of you are different APIs, and the
gap is now a whole major version. Tag `3.0` is PHP 7 compatible, has no
`value()`, `getLeft()`, `getRight()`, `tap()`, `fromCallable()` or
`valueOrThrow()`, and it is what a consumer on `^3.0` resolves to.

Before telling anyone a method is available, or writing code elsewhere that
calls one:

```bash
git describe --tags          # how far master is past the last release
git show 3.0:src/Option.php | grep "public function value()"
```

## Commands

```bash
composer install
vendor/bin/phpunit                    # 132 tests
vendor/bin/psalm                      # must exit 0, errorLevel 1, no baseline
php examples/optionExampleAfter.php
```

**Check psalm's exit code, not its output.** `vendor/bin/psalm ... | grep error`
matches neither "No errors found!" nor a clean run's silence, so a grep like
that reports success for both outcomes. That mistake put a psalm failure into
this repo's history once already.

There is no `psalm-baseline.xml` and there should not be one. `src/` and
`tests/` are both clean at errorLevel 1. Fix the type rather than baselining it.

`.github/workflows/test.yaml` runs three jobs: phpunit across the PHP versions
in the matrix, psalm once on 8.4, and `composer validate --strict`. Static
analysis is deliberately out of the test matrix, because pinning psalm to the
oldest PHP in the matrix is what kept this repo on a 2022 psalm for years.

## The docs are tested, and that is the point

`tests/DocblockExampleTest.php` pulls every ```php fence out of `src/` and
`README.md` and executes it. Fifty-odd examples, one test case each, named for
the method they document.

This exists because eight docblock examples had been uncallable for years.
They called methods that never existed, invoked factories with too few
arguments, and in two cases stated a result that is wrong on PHP 8. Nothing
executed them, so nothing caught it.

Consequences when you add or edit an example:

- It has to run. An example that means to show a throw must catch it itself.
- `eval` does not inherit `use` aliases from the calling file, which is why the
  runner prepends its own import list as a `PRELUDE` string.
- Illustrative class names (`SomeObject`, `NewObject`, `SomeComplexThing`) are
  real fixtures in `tests/Fixtures/`. Add to those rather than teaching the
  runner to skip an example.
- stdout is buffered and `error_log` is redirected for the duration, so an
  example may print or log without making the test risky.

`tests/ExampleScriptTest.php` does the same job for `examples/`, running each
script in a subprocess and failing on any Fatal, Warning, Notice or Deprecated.

The docblocks are the only API documentation. There is no generated site:
`php7-optional.surge.sh` is still served but unowned and years stale, still
returning 200 for `Optional/Result.html` and `Optional/SimpleResult.html`,
classes deleted in `b892422`. Rebuilding it needs a deploy credential nobody
has written down, so treat the docblocks as the product.

## Behavior that surprises people

Each verified by execution on PHP 8.4, not read off the annotations.

### The filter family is eager, the filterIf family is lazy

`Option::filter()`, `Either::filterLeft()` and `Either::filterRight()` take a
**`bool`**, not a callable. PHP evaluates the argument at the call site, before
the method runs, so an expensive or throwing expression runs even when the box
is empty:

```php
Option::none()->filter($expensive());   // $expensive() still runs
Option::none()->filterIf($expensive);   // callback never runs
```

The discoverable spelling is the costly one across the whole API: `valueOr`,
`or`, `else` and `filter` are eager, while `valueOrCreate`, `orCreate`,
`elseCreate` and `filterIf` are lazy.

### Exception or Throwable, and why they differ

| Call | Catches | Because |
|---|---|---|
| `Option::fromCallable`, `Option::mapSafely` | `Exception` | the box discards it, so swallowing an `Error` erases a bug |
| `Either::fromCallable`, `Either::mapLeftSafely` | `Throwable` | the box keeps it, so nothing is lost |

This asymmetry is deliberate and both halves have a test. Do not "fix" it by
making them agree.

A PHP 8 warning is not a throwable either, so a missing array key inside a
`mapSafely` callback yields `Some(null)`, not a None.

### `Option::some(null)` is a real Some

`hasValue()` returns `true`. That is what makes the box honest about a stored
null. `notNull()`, `notFalsy()`, `someNotNull()` and `someNotFalsy()` collapse
it.

### Smaller ones

- `contains()` compares with `==`. `Option::some(1)->contains('1')` is `true`.
- `andThen()` is an alias: `Option::andThen` calls `flatMap`,
  `Either::andThen` calls `flatMapLeft`.
- `__toString()` interpolates the boxed value, so a non-stringable value gives
  a PHP warning and `"Some(Array)"` rather than a throw. The
  `@psalm-suppress InvalidCast` is intentional (vimeo/psalm#1982).
- `Option::none()` builds a new object every call. Compare with `==`.
- `Either::fromArray` with no `$rightValue` fabricates
  `new Exception("Either got null for rightValue")`, so there is no way to
  build a `Right(null)` through it. `Either::right(null)` works.

## Two traps in the tooling itself

**PHPUnit cannot see a compile-time deprecation.** `failOnDeprecation` is set,
but a deprecation raised while a file is being compiled fires before PHPUnit
has a handler attached to a test. That is how an implicitly nullable parameter
(`string $ex = null`, deprecated in 8.4) sat in the test suite unnoticed. The
only thing that catches those is running the file in a subprocess, which is
what `ExampleScriptTest` does.

**psalm proves the throws, so code after one is unreachable.** The conditional
return types (`(T is never ? never : T)`) let psalm prove that `value()` on a
`None` cannot return. Anything written after such a call is reported as
`UnevaluatedCode`, and a `$threw` flag read afterwards as `RedundantCondition`.
Use one `expectException` per test rather than fighting it. One throw per test
is what makes that safe. `expectException` ends a test at the first throw,
which is why `5abacf7` had to abandon it when a single test asserted two.

## Psalm annotations

`psalm.xml` sets `errorLevel="1"` over `src/` and `tests/`, with
`findUnusedCode` on. The `psalm/plugin-phpunit` plugin is what stops psalm
reporting the test classes themselves as unused.

- `@psalm-param` is `<type> $var`. Two methods once had it reversed, and the
  effect was invisible: psalm silently failed to bind the template, so `map()`
  reported `Option<never>` for a box holding a string. Check a change to a
  conditional return type with `@psalm-trace`, and compare it against what the
  code actually returns.
- `@psalm-pure` already implies `@psalm-mutation-free`. Do not write both.
- The classes carry `@api`, which is what keeps psalm from reporting a public
  method as unused just because its callers live in another repository.
- `@psalm-param` beats a plain `@param` for the same variable. A docblock
  carrying both silently drops the `@param`, which is how `Either::fromArray`
  ended up with a `key-of<TArray>` conditional that could never bind.

## Style

- **Three spaces** for code indentation, with docblock `*` lines one further
  in. There is no `.editorconfig` and no phpcs, so nothing corrects you.
- `declare(strict_types = 1);` with spaces around the `=`.
- `iff` in the docblocks means "if and only if". Leave it.
- A new public method needs a worked `php` example showing both states of the
  box. Match the surrounding order: description, fenced example, `_Notes:_`,
  then the psalm annotations. The example will be executed.

## Releasing

Tags are plain versions with no `v` prefix. Packagist picks them up from
GitHub. A release is a tag on master, after which a consumer picks it up with
`composer update ifixit/php7-optional`.

The package keeps the name `ifixit/php7-optional` even though it now requires
PHP 8.4. Renaming a published Packagist package costs every consumer an edit,
which is not worth it to fix an inaccurate number.
