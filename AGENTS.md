# php7-optional

Two immutable boxes that replace `null` and error returns: `Optional\Option`
(Some or None) and `Optional\Result` (Ok or Err). This is a standalone
Composer package published to Packagist as `ifixit/php7-optional`. Consumers
install it with composer, so nothing you change here reaches any of them until
someone cuts a tag.

The whole library is `src/Option.php`, `src/Result.php`,
`src/Exceptions/MissingValueException.php` and one internal trait,
`src/Internal/DescribesValues.php`. Read them directly. This document
covers only what reading them will not tell you, or will tell you wrong.

## master is not what consumers get

The released package and the code in front of you are different APIs, and the
gap is now a whole major version. Tag `3.0` is PHP 7 compatible, has `Either`
where master has `Result`, has no `tap()`, `fromCallable()`, `valueOrThrow()`
or `first()`, and it is what a consumer on `^3.0` resolves to.

Before telling anyone a method is available, or writing code elsewhere that
calls one:

```bash
git describe --tags          # how far master is past the last release
git show 3.0:src/Option.php | grep "public function valueOrThrow"
```

## Commands

```bash
composer install
composer check       # what CI runs: phpunit then psalm
composer test        # phpunit alone
composer psalm       # psalm alone, errorLevel 1, no baseline
composer coverage    # needs xdebug or pcov, which a plain PHP install lacks
php bin/generate-example-checks.php   # after editing any example, see below

# the PHP 8.5 CI leg, the only place a dropped #[\NoDiscard] result warns
docker run --rm --user "$(id -u):$(id -g)" -v "$PWD":/app -w /app php:8.5-cli vendor/bin/phpunit
```

**Check psalm's exit code, not its output.** `vendor/bin/psalm | grep error`
matches neither "No errors found!" nor a clean run's silence, so it reports
success for both. That mistake has put a psalm failure into a commit before.

There is no `psalm-baseline.xml` and there should not be one. `src/` and
`tests/` are clean at errorLevel 1. Fix the type rather than baselining it.

`composer coverage` writes `clover.xml`, runs `bin/check-coverage.php` and
fails below the floor. That script exits 2, not 0, when the report is missing
or unparseable, when the counts are impossible, or when the floor is not a
number, because a coverage step that passes when nothing was measured is worse
than no step. `tests/CoverageGateTest.php` covers every one of its outcomes.

`composer mutation` breaks the source on purpose and checks a test notices. It
runs in the coverage job, because it needs the same driver. It exists because
review found behavior that a docblock and this file both described and that no
test would notice being deleted: the catch types of both `mapSafely` methods,
and a fallback exception nothing asserted.

`composer benchmark` measures what the box costs against the plain PHP it
replaces, and against `json_decode()` and a small file read for scale. The
README carries the numbers.

`.github/workflows/test.yaml` is four jobs: the phpunit matrix on 8.4 and 8.5,
then coverage plus mutation testing, psalm, and `composer validate --strict`
once each on 8.4. Static analysis is out of the matrix because pinning psalm to
the oldest PHP in it is what kept this repo on a 2022 psalm for years. Coverage
is out because xdebug roughly triples the run, and measuring the same lines
once per PHP version teaches nothing.

## The docs are tested, and that is the point

The docblocks are the only API documentation, and four suites hold them to it.

- `DocblockExampleTest` executes every ```php fence in `src/` and `README.md`,
  one test case each, named for the method it documents.
- psalm analyses every one of those examples as well, through
  `tests/generated/DocblockExamples.php`. Regenerate it with
  `php bin/generate-example-checks.php` after editing an example;
  `GeneratedExamplesAreCurrentTest` fails until you do.
- `ExampleScriptTest` runs each script in `examples/` in a subprocess, failing
  on any Fatal, Warning, Notice or Deprecated.
- `DocumentedApiTest` walks every class under `src/` with reflection and
  requires that every public method has a fence, that the fence calls the
  method it sits above, and that the method appears in README.md's list.
  `__toString` is exempt.

Together: a new public method needs an example, the example has to be about
that method, it has to run and type-check, and the method has to be listed.

Four things to know when you touch an example:

- It has to run. One that means to show a throw must catch it itself.
- `eval` does not inherit `use` aliases, which is why the runner prepends its
  own imports as a `PRELUDE` string.
- `SomeObject` and `NewObject` are real fixtures in `tests/Fixtures/`. Add
  to those rather than teaching the runner to skip one.
- stdout is buffered and `error_log` redirected, so an example may print.

Nothing checks that an example prints what its trailing comment claims. Run it
and change the comment by hand when you change what an example returns.

The generated-example file has its own psalm suppressions, each listed with its
reason in `psalm.xml`. Two of them, `ReservedWord` and `NoValue`, once hid a
real defect in chained calls, which is why chains are checked in
`tests/TypeInference` with nothing suppressed.

There is no generated API site. `php7-optional.surge.sh` is still served but
unowned and years stale; do not send anyone to it.

## Behavior that surprises people

Each verified by execution on PHP 8.4, not read off the annotations.

### Pass the callable, not its result

`Option::filter()` and `Result::filter()` take a bool or a callable. A bool is
evaluated at the call site, before the method runs, so an expensive or
throwing expression runs even when the box is empty:

```php
Option::none()->filter($expensive());   // $expensive() still runs
Option::none()->filter($expensive);     // callback never runs
```

A callable's result counts the way `if` counts it, as `array_filter` does and
3.x's `filterIf` did. Do not tighten it to `=== true`: a flag read back from a
database as `1` would then drop the value, with nothing to say why.

Elsewhere the short name is still the costly one: `valueOr`, `or` and `else`
are eager, while `valueOrCreate`, `orCreate` and `elseCreate` are lazy. They
cannot take a callable the way `filter` did, because their argument is a value,
and a value can be a callable.

### Exception or Throwable, and why they differ

| Call | Catches by default | Because |
|---|---|---|
| `Option::fromCallable`, `Option::mapSafely` | `Exception` | the box discards it, so swallowing an `Error` erases a bug |
| `Result::fromCallable`, `Result::mapSafely` | `Throwable` | the box keeps it, so nothing is lost |

This asymmetry is deliberate and both halves have a test. Do not "fix" it by
making them agree.

Each of the four takes the class to catch as its last argument, `$catch`, and
the defaults above are only its default values. The body catches `Throwable`
and rethrows anything that is not an instance of `$catch`, so narrowing that
`catch` block would silently stop a caller from naming an `Error` class.

A PHP 8 warning is not a throwable either, so a missing array key inside a
`mapSafely` callback yields `Some(null)`, not a None.

### `Option::some(null)` is a real Some

`hasValue()` returns `true`. That is what makes the box honest about a stored
null. `notNull()`, `notFalsy()`, `someNotNull()` and `someNotFalsy()` collapse
it.

### Smaller ones

- `contains()` compares with `===` on both boxes, so
  `Option::some(1)->contains('1')` is `false` and an object matches only
  itself. 3.x compared with `==`. For a looser test, pass it to `exists()`.
- `someNotFalsy()` drops a match at index `0`, since `0` is falsy. For
  `strpos`, `array_search` and the other functions that return `false` on
  failure, `someNotFalse()` and `notFalse()` are the right entrance.
- There is no `value()`, on purpose. The no-argument `valueOrThrow()` replaces
  it, so the throw is in the name. Do not add it back.
- `Result::valueOrThrow()` with no factory rethrows the error itself when the
  error is a `Throwable`, so the trace still points where the failure
  happened. Any other error becomes a `MissingValueException` naming it.
- `__toString()` never warns or throws. Both boxes show what they hold through
  `Internal\DescribesValues`: an array or object with no string form by its
  type, an exception by its class and message. 3.x interpolated the value, so
  an array warned and an object with no `__toString` threw.
- `Option::none()` builds a new object every call. Compare with `==`.

## The serialized form is API

Boxes get cached with `serialize()`, and the payload names the class and
every private property: `Optional\Option` with `hasValue` and `value`,
`Optional\Result` with `isOk`, `value` and `error`. Renaming one, or splitting a
class into `Some` and `None` subclasses, breaks every box cached before the
deploy, and quietly: a cached Some reads back as a None, with a deprecation
notice as the only sign. `SerializationTest` pins the bytes (Option's are
unchanged since tag 3.0) and fails on either direction of that drift.

## Two conventions the tests enforce

**Every method that returns a new box carries `#[\NoDiscard(self::DISCARDED)]`,
except the tap family.** `$box->map($f);` alone on a line does nothing, psalm
cannot see it (a method that runs a callback is never mutation-free), and PHP
8.5 warns for it. `NoDiscardTest` fails when a box-returning method lacks the
attribute or anything else has it. PHP 8.4 ignores the attribute, so a dropped
result only shows up in the 8.5 run: a test that calls a method just to see it
throw or not run a callback has to use the result, or assign it to `$_`, which
psalm treats as deliberately unused. Do not reach for a `(void)` cast; that is
PHP 8.5 syntax and a parse error on 8.4.

**`Result` takes `Option`'s names.** Wherever the idea is the same, the method
has the same name and the same parameter names on both boxes, and on `Result`
it acts on the success: `map`, `mapSafely`, `flatMap`, `filter`, `valueOr`,
`valueOrCreate`, `valueOrThrow`, `toNullable`, `contains`, `exists`, `or`,
`orCreate`, `else`, `elseCreate`, `tap`, `fromCallable`. What reaches the error
is spelled with `Err`: `mapErr`, `errOr`, `tapErr`, `isErr`.
`testSharesOptionsMethodAndParameterNames` fails when a shared method's
parameters drift. A new method on either box should take the other's name if
the other has the idea.

There is also one name per operation. `Either` reached 66 methods by spelling
every operation twice, left and right, and then adding success and failure
aliases on top. `Option` had six second names of its own, which 4.0 removed:
`andThen` for `flatMap`, `filterIf` for `filter`, `matchSome` and `matchNone`
beside `tap` and `tapNone`, and the `someWhen` and `noneWhen` factories. Do not
add an alias back. No test catches one, so this rule is on review.

## Two traps in the tooling itself

**PHPUnit cannot see a compile-time deprecation.** `failOnDeprecation` is set,
but a deprecation raised while a file is being compiled fires before PHPUnit
has a handler attached to a test. That is how an implicitly nullable parameter
(`string $ex = null`, deprecated in 8.4) sat in the test suite unnoticed. The
only thing that catches those is running the file in a subprocess, which is
what `ExampleScriptTest` does.

**psalm proves the throws, so code after one is unreachable.** The conditional
return types (`(T is never ? never : T)`) let psalm prove that
`valueOrThrow()` on a `None` cannot return. Anything written after such a call
is reported as `UnevaluatedCode`, and a `$threw` flag read afterwards as
`RedundantCondition`. Use one `expectException` per test rather than fighting
it. One throw per test is what makes that safe. `expectException` ends a test
at the first throw, which is why `5abacf7` had to abandon it when a single test
asserted two.

## Psalm annotations

`psalm.xml` sets `errorLevel="1"` over `src/` and `tests/`, with
`findUnusedCode` on. The `psalm/plugin-phpunit` plugin is what stops psalm
reporting the test classes themselves as unused.

- **A method that returns a box never gets a conditional return type.**
  psalm cannot decide `T is never` for an ordinary `T`, so it returns both
  branches, and `(T is never ? Option<never> : Option<U>)` becomes
  `Option<U>|Option<never>`. The next call with a typed callback meets the
  empty half and fails with `ReservedWord` and `NoValue`; that once broke 11
  of 20 everyday two-call chains. Write one type instead (`Option<T|TT>`,
  `Result<T, E|TE>`), and state the class invariant in the body with a
  `/** @var ... */` where psalm cannot see it. A conditional on a method that
  returns a plain value is fine, since psalm drops `never` from a value union.
- To drop `null` or `false` from a box's type, bind a template through
  `@psalm-if-this-is Option<TNotNull|null>`. Two or three members bind; a
  longer union of literals does not, and fails on an array shape.
- `@psalm-param` is `<type> $var`. Two methods once had it reversed, and the
  effect was invisible: psalm silently failed to bind the template, so `map()`
  reported `Option<never>` for a box holding a string. Check a change to a
  return type with `@psalm-trace`, and compare it against what the code
  actually returns.
- `@psalm-pure` already implies `@psalm-mutation-free`. Do not write both.
- The classes carry `@api`, which is what keeps psalm from reporting a public
  method as unused just because its callers live in another repository.
- `@psalm-param` beats a plain `@param` for the same variable. A docblock
  carrying both silently drops the `@param`, which is how a `fromArray` once
  ended up with a `key-of<TArray>` conditional that could never bind.

## PHPStan reads the same annotations

PHPStan understands the `@psalm-*` tags, so there is one set of types. The
exception is `@psalm-if-this-is`, which is how `notNull()`, `notFalsy()` and
`notFalse()` drop a type; PHPStan ignores it and would call the result
`mixed`. Those three methods also carry a
`@phpstan-return` conditional such as `(T is null ? self<never> : self<T>)`.
psalm ignores that tag, and PHPStan merges the `Option<never>` half back into
the other, which psalm will not do. Keep both when you touch one.

Nothing in CI runs PHPStan. To check by hand with any PHPStan binary:

```bash
sed -E 's#/\*\* @psalm-trace (\$[A-Za-z]+) \*/#\\PHPStan\\dumpType(\1);#' \
   tests/TypeInference/Expressions.php > /tmp/phpstan-expressions.php
phpstan analyse --level=max --autoload-file=vendor/autoload.php \
   --no-progress --error-format=raw /tmp/phpstan-expressions.php
```

Every expression should get a type, none of them `mixed`. The one error to
expect is PHPStan's, not ours: it widens a `false` passed to `Option::some()`
into `bool`, so `Option::some($intOrFalse)->notFalsy()` stays
`Option<int|true>`. The factories, `someNotFalse()` and `someNotFalsy()`,
narrow it.

## Style

- **Three spaces** for code indentation, with docblock `*` lines one further
  in. There is no `.editorconfig` and no phpcs, so nothing corrects you.
- `declare(strict_types = 1);` with spaces around the `=`.
- `iff` in the docblocks means "if and only if". Leave it.
- Examples use arrow functions, first-class callables (`strtoupper(...)`) and
  named arguments on `match()`. Keep `function` only where an arrow function
  cannot do the job: a by-reference `use`, or a statement such as `echo`.
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
