# php7-optional

Two immutable boxes that replace `null` and error returns: `Optional\Option`
(Some or None) and `Optional\Either` (Left or Right). This is a standalone
Composer package published to Packagist as `ifixit/php7-optional`. Consumers
install it with composer, so nothing you change here reaches any of them until
someone cuts a tag.

The whole library is three source files (`src/Option.php`, `src/Either.php`,
`src/Exceptions/MissingValueException.php`) and two test files. Read them
directly. This document covers only what reading them will not tell you, or
will tell you wrong.

## master is not what consumers get

The released package and the code in front of you are different APIs.
`value()`, `getLeft()`, `getRight()`, and the whole `MissingValueException`
class were added **after** tag `3.0` (commits `b0d860f` and `9dbef9e`). Tag
`3.0` has none of them, and `3.0` is what a consumer on `^3.0` resolves to.

Before you tell anyone a method is available, or write code elsewhere that
calls one:

```bash
git describe --tags          # how far master is past the last release
git show 3.0:src/Option.php | grep "public function value()"
```

Until a new tag is cut and picked up, `->value()` does not exist for a
consumer, no matter what `src/Option.php` says here.

## Commands

```bash
composer install
vendor/bin/phpunit                    # what CI runs, plus --coverage-text
vendor/bin/psalm                      # must be clean at errorLevel 1
vendor/bin/psalm --set-baseline=psalm-baseline.xml   # only with a reason
php examples/optionExampleAfter.php   # examples require src/ directly, no autoloader needed
```

`.github/workflows/test.yaml` is the only workflow. It runs phpunit and
`psalm --shepherd` across PHP 7.4, 8.0, and 8.1 on push to master and on PRs
targeting master.

## PHP 7.4 is the floor, and composer.json disagrees

`composer.json` requires `^7.2 || ^8.0`, but CI dropped 7.2 and 7.3 (commits
`f2f41ce`, `2a5c8f4`) and tests 7.4 upward. Write for 7.4: no constructor
promotion, no named arguments, no `match`, no enums, no nullsafe operator, no
union types in signatures. Nothing in the repo stops you from writing PHP 8
syntax, the 7.4 job in CI does.

If you deliberately want to drop 7.4, that is a `composer.json` change and a
major tag, not a quiet syntax upgrade.

## Behavior that surprises people

Each of these is verified against the source on PHP 8.4, not inferred from the
docblocks.

### The filter family is eager, the filterIf family is lazy

`Option::filter()`, `Either::filterLeft()`, and `Either::filterRight()` take a
**`bool`**, not a callable. PHP evaluates the argument at the call site, before
the method runs, so an expensive or throwing expression runs even when the box
is a None or the wrong side:

```php
Option::none()->filter($expensive());   // $expensive() still runs
Option::none()->filterIf($expensive);   // callback never runs
```

`Option::filter()` is also the only public method in either class with no
docblock, so it is missing from the generated API docs entirely.

### `mapSafely` and `mapLeftSafely` disagree about the exception

Both catch `\Exception`. They do opposite things with it:

| Call | On a thrown `\Exception` |
|---|---|
| `Option::mapSafely()` | returns `Option::none()`, exception discarded |
| `Either::mapLeftSafely()` | returns `Either::right($e)`, exception preserved |

Reach for `Either` when the failure reason matters. `Option::mapSafely` gives
you no way to find out what went wrong.

Neither catches `\Throwable`, so anything extending `\Error` goes straight
through: a `TypeError` from a bad callable signature escapes `mapSafely`
uncaught. A PHP 8 warning is not an exception either, which makes the
library's own `mapSafely` docblock example wrong. It claims a missing array
key yields a None; on PHP 8 it yields `Some(null)`.

### The two `fromArray` methods use different key checks

`Option::fromArray` uses `array_key_exists`, `Either::fromArray` uses `isset`.
On a key that exists holding `null` they disagree:

```php
Option::fromArray(['k' => null], 'k');          // Some(null)
Either::fromArray(['k' => null], 'k', 'miss');  // Right('miss')
```

`Either::fromArray` also fabricates a right value when you omit one:
`new \Exception("Either got null for rightValue")`. Passing an explicit
`null` gets you the same fabricated exception, so there is no way to build a
`Right(null)` through `fromArray`. `Either::right(null)` does work.

### `Option::some(null)` is a real Some

`hasValue()` returns `true` for it. That is deliberate, it is what makes the
box honest about a stored null. `notNull()`, `notFalsy()`, and
`Option::someNotNull()` are how you collapse it to a None.

### Smaller ones

- `contains()` compares with `==`, not `===`. `Option::some(1)->contains('1')`
  is `true`.
- `andThen()` is a plain alias: `Option::andThen` calls `flatMap`,
  `Either::andThen` calls `flatMapLeft`. Same behavior, nicer name at a call
  site.
- `__toString()` interpolates the boxed value, so a non-stringable value gives
  you a PHP warning and `"Some(Array)"` rather than a throw. The
  `@psalm-suppress InvalidCast` on it is intentional and points at
  vimeo/psalm#1982. Null is special-cased to `"Some(null)"` and `"Left(null)"`.
- `Option::none()` builds a new object every call. Compare with `==`, never
  `===`.
- `Either::toOptionFromLeft()` is the only bridge between the two classes.
  There is no `Option`-to-`Either` direction and no `toOption()`, despite that
  method's own docblock example calling `$either->toOption()`.

## The README documents classes that do not exist

`README.md` has a section on `Optional\Result` and `Optional\UnsafeResult`,
including a worked example using `SimpleResult::okay()`. None of those classes
are in `src/`. `Result` was deleted in commit `b892422` ("Result: Drop in
favor of either") and the README was never updated.

Do not write code against `Result`, and do not treat its absence as a bug to
fix. If someone asks for `Result` back, that is a design conversation about
reversing `b892422`, not a missing file.

The published docs at <https://php7-optional.surge.sh/> have the same problem
from the other direction. Nothing in CI builds or deploys them, and
`sami-config.php` is the only build config. That site shows whatever was true
when somebody last published it by hand.

## Psalm is the real gate

`psalm.xml` sets `errorLevel="1"`, the strictest level, over both `src/` and
`tests/`. The baseline holds exactly four entries, all `PropertyNotSetInConstructor`
on the two test classes. Keep it that way. Fix the type instead of baselining
it: a baseline entry added to land a change is how a level-1 project stops
being one. The level badge in the README is generated from `--shepherd` output
on every master build.

Writing annotations here, three things to copy carefully:

- **Do not use `map()` or `mapSafely()` as your docblock template.** Both write
  `@psalm-param $mapFunc callable(T):U`, with the variable before the type.
  The other 82 `@psalm-param` lines in `src/` use the correct
  `@psalm-param <type> $var` order.
- `mapSafely()` declares `@psalm-return self<T>` while actually returning
  `Option<U>` from `map()`. Do not propagate that either.
- `@psalm-mutation-free` and `@psalm-pure` are not interchangeable here. A
  method that invokes a user callable is not pure, because the callable can do
  anything. Static factories that only box a value carry both. Match the
  existing split rather than re-deriving it. It was settled over three commits:
  - `fbb87f8` "Remove pure in favor of external mutation free"
  - `7597c0c` "Drop pure for methods which touch exception"
  - `cb8755e` "Allow some impurity"

## Testing

`tests/` mirrors `src/`: one test class per source class, grouped by behavior
rather than one test per method, so `testCreateAndCheckExistence` covers
several factories at once. Add cases to the matching test rather than creating
a file per method.

**Do not use `expectException` to assert more than one throw in a test.**
PHPUnit ends the test at the first one, so every assertion after it silently
never runs. Commit `5abacf7` ("Tests: Actually run them") fixed exactly that.
Use an explicit try/catch per throw, asserting on both the class and the
message:

```php
try {
   /** @psalm-suppress UnusedMethodCall since php unit is catching the throw */
   $none->value();
} catch (\Exception $e) {
   $this->assertTrue($e instanceof MissingValueException);
   $this->assertSame("Value is missing.", $e->getMessage());
}
```

`phpunit.xml` turns notices, warnings, and deprecations into exceptions and
sets `failOnRisky`, so a test that asserts nothing fails the build.

## Style

- **Three spaces** for code indentation, with docblock `*` lines sitting one
  space further in. There is no `.editorconfig` and no phpcs in this repo, so
  nothing will correct you.
- `declare(strict_types = 1);` with spaces around the `=`, directly under the
  opening tag in every file.
- `iff` in the docblocks means "if and only if". It is not a typo, leave it.
- Docblock code fences are the public API documentation. A new public method
  needs a worked `php` example showing both states of the box, not just a type
  signature. Match the surrounding order: description, fenced example,
  `_Notes:_`, then the psalm annotations.

## Releasing

Tags are plain versions with no `v` prefix (`1.0` through `3.0`). Packagist
picks them up from the GitHub repository. A release is a tag on master, after
which a consumer picks it up with `composer update ifixit/php7-optional`.
Breaking the `Option` or `Either` surface means a major tag, because a
consumer's `^3.0` constraint will not pick it up otherwise, which is the
behavior you want.
