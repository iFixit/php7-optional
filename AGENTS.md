# php7-optional

Two immutable boxes that replace `null` and error returns: `Optional\Option`
(Some or None) and `Optional\Result` (Ok or Err). This is a standalone
Composer package published to Packagist as `ifixit/php7-optional`. Consumers
install it with composer, so nothing you change here reaches any of them until
someone cuts a tag.

The whole library is `src/Option.php`, `src/Result.php`, two exceptions in
`src/Exceptions/` (`MissingValueException` and `EarlyReturn`) and two internal
traits in `src/Internal/`: `DescribesValues` for the string form and
`ChecksItems` for what `all()` and `partition()` accept. The psalm plugin and PHPStan
extension, which report a dropped `Result` and type a box built in two branches
as one box, are in `static-analysis/`. Read them directly. This document
covers only what reading them will not tell you, or will tell you wrong.

## master is not what consumers get

The released package and the code in front of you are different APIs, and the
gap is now a whole major version. Tag `3.0` is what a consumer on `^3.0`
resolves to. It is PHP 7 compatible and has `Either` where master has
`Result`. It has no `tap()`, `fromCallable()`, `valueOrThrow()` or `first()`,
and it still has the six `Option` aliases master removed.

Before telling anyone a method is available, or writing code elsewhere that
calls one:

```bash
git describe --tags          # how far master is past the last release
git show 3.0:src/Option.php | grep "public function valueOrThrow"
```

## Commands

```bash
composer install
composer check       # what CI runs: phpunit, psalm and PHPStan
composer test        # phpunit alone
composer psalm       # psalm alone, errorLevel 1, no baseline
composer phpstan     # PHPStan alone, level max, no baseline
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
number. A coverage step that passes when nothing was measured is worse than no
step. `tests/CoverageGateTest.php` covers every one of its outcomes.

`composer mutation` breaks the source on purpose and checks a test notices. It
runs in the coverage job, because it needs the same driver. Both gates are at
100: every line of `src/` is covered, and every mutant is killed. A mutant left
alive is a missing test or code that does nothing; `infection.json5` names
the one equivalent mutant it skips, and why. It exists because
review found behavior that a docblock and this file both described and that no
test would notice being deleted, such as which class `Option::mapSafely`
catches.

`composer benchmark` measures what the box costs against the plain PHP it
replaces, and against `json_decode()` and a small file read for scale. The
README has the numbers.

`.github/workflows/test.yaml` is five jobs: the phpunit matrix on 8.4 and 8.5,
then coverage plus mutation testing, psalm, PHPStan, and
`composer validate --strict` once each on 8.4. Static analysis is out of the matrix because pinning psalm to
the oldest PHP in it is what kept this repo on a 2022 psalm for years. Coverage
is out because xdebug roughly triples the run, and measuring the same lines
once per PHP version teaches nothing.

## The docs are tested, and that is the point

The docblocks are the only API documentation, and four suites hold them to it.

- `DocblockExampleTest` executes every ```php fence in `src/` and `README.md`,
  one test case each, named for the method it documents, and checks every
  line an example prints against the comment on its `echo`.
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
- An example uses only the library and PHP's own classes. One that needs a
  class of its own gets a real one under `tests/Fixtures/`, imported in
  `PRELUDE`, rather than a runner taught to skip it.
- stdout is buffered and `error_log` redirected, so an example may print.

An example that prints says what it prints in the comment on each `echo`,
and the test holds it to that:

- `// "text"` states the line inside the quotes; anything after the closing
  quote is commentary.
- `// text` states everything up to the first `, ` outside brackets, so
  `// Some(3), after the retry` states `Some(3)`.
- Each `echo` prints exactly one line, ending in `"\n"`, or the lines no
  longer line up with the comments and the test says so.
- A comment on a line that does not echo (`$x = ...; // Some(3)`) is not
  checked. Prefer an `echo` that prints the box, so it is.

The generated-example file has its own psalm suppressions, each listed with its
reason in `psalm.xml`. Two of them, `ReservedWord` and `NoValue`, once hid a
real defect in chained calls, which is why chains are checked in
`tests/TypeInference` with nothing suppressed.

There is no generated API site. `php7-optional.surge.sh` is still served but
unowned and years stale; do not send anyone to it.

## Behavior that surprises people

Each verified by execution on PHP 8.4, not read off the annotations.

### Pass the closure, not its result

`Option::filter()` and `Result::filter()` take a bool or a `Closure`, not any
callable, so a string is never called. In a file without strict types a string
becomes `true`, as it did in 3.x. A bool is
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
cannot take a callable the way `filter` does, because their argument is a
value, and a value can be a callable.

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

### The early exit is an exception

`valueOrReturn()` leaves a `do()` block by throwing
`Exceptions\EarlyReturn`, and the nearest `do()` for the same kind of box
catches it, however many calls down it was thrown. Three rules keep that safe,
and each has a test in `EarlyReturnTest`:

- It extends `Error`, so a `catch (Exception)` in the block cannot swallow it.
  A `catch (Throwable)` can, so the class is public API: a catch-all catches
  `EarlyReturn` first and rethrows it. Its constructor is `@internal`.
- `fromCallable()` and `mapSafely()` never catch it, whatever `$catch` says,
  because it is not a failure of their callable. Any new method that catches
  a callable's throw needs the same `!$e instanceof EarlyReturn` check.
- An `Option` escape passes through a `Result::do()` untouched, and the other
  way round.

`valueOrReturn()` carries no `#[\NoDiscard]`: on its own line it is an
assertion that ends the block early, which is a use.

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
- `Result::valueOrThrow()` with no argument rethrows the error itself when
  the error is a `Throwable`, so the trace still points where the failure
  happened. With a message, or with any other error, it throws a
  `MissingValueException` that names the error and keeps it as `$error`.
- `__toString()` never warns or throws, and never shows two different scalars
  the same way. Both boxes go through `Internal\DescribesValues`. Strings are
  quoted with control characters escaped, and floats keep their point. An enum
  case shows as `Class::Case`, an exception as `RuntimeException('boom')`, any
  other object with a string form as `SplFileInfo('5')`, and anything else as
  its type, so two arrays look alike. It catches what a value's own
  `__toString()` throws, except `EarlyReturn`, which it rethrows. Before 4.0
  it interpolated the value, so an array warned and an object with no
  `__toString` threw.
- Compare boxes with `hasValue()` and `contains()`, not `==`. `==` compares
  the held values loosely, so `Option::some(1) == Option::some('1')` is true.
  `Option::none()` builds a new object each call, so two Nones are `==` but
  not `===`.
- Callbacks run under this library's `strict_types`, whatever the caller's
  file declares, so `->map(fn(int $id) => ...)` on a database's `'7'` throws.
  The README says so; do not "fix" it by dropping `strict_types`, which would
  only move the surprise to the strict callers.

## The serialized form is API

Boxes get cached with `serialize()`, and the payload names the class and
every private property: `Optional\Option` with `hasValue` and `value`,
`Optional\Result` with `isOk`, `value` and `error`. Renaming one, or splitting a
class into `Some` and `None` subclasses, breaks every box cached before the
deploy. Because the classes are `readonly`, reading one back throws
`Error: Cannot create dynamic property Optional\Option::$hasValue` on every
cache hit until the entries expire. `SerializationTest` pins the bytes
(Option's are unchanged since tag 3.0) and fails on either direction of that
drift.

## Two conventions the tests enforce

**Every method that returns a new box carries `#[\NoDiscard]`, factories
included, except the tap family.** `$box->map($f);` alone on a line does
nothing, psalm cannot see it (a method that runs a callback is never
mutation-free), and PHP 8.5 warns for it. The factories use `self::UNREAD`
rather than `self::DISCARDED`, because a factory dropped on its own line is
almost always a missing `return`. `NoDiscardTest` fails when a box-returning
method lacks the attribute or anything else has it.

PHP 8.4 ignores the attribute, so a dropped result only shows up in the 8.5
run. A test that calls a method just to see it throw, or to see a callback not
run, still has to use the result. Assigning it to `$_` counts, and psalm treats
that as deliberately unused. Do not reach for a `(void)` cast; that is PHP 8.5
syntax and a parse error on 8.4.

psalm checks an attribute by analysing it as a call to its constructor, in the
method's own context, and it does not know `NoDiscard`'s constructor is pure.
`stubs/NoDiscard.phpstub` says so. Without it, every `@psalm-pure` factory
reports `ImpureMethodCall`.

**`Result` takes `Option`'s names.** Wherever the idea is the same, the method
has the same name and the same parameter names on both boxes. On `Result` it
acts on the success: `map`, `mapSafely`, `flatMap`, `filter`, `valueOr`,
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

## The psalm plugin and PHPStan extension

`static-analysis/` holds a psalm plugin and a PHPStan extension. Each does two
things: it reports a call whose `Result` is dropped on its own line, and it
types a box built in two branches (`Result<A, never>|Result<never, B>`) as one
box (`Result<A, B>`). The merge is exact only because a box holds one value and
its type parameters are covariant, so never extend it to a class that can hold
several values, where `List<A>|List<B>` is not `List<A|B>`. They live outside
`src/` because they are tooling, not the library's API, and the PHPStan half
names classes psalm cannot see inside PHPStan's phar, so `psalm.xml` skips that
directory.

`psalm.xml` and `phpstan.neon` turn the check on for this repo too, so a test
or an example that drops a `Result` is reported. The one test that drops one on purpose says so
with `@psalm-suppress UnusedResult`.

`tests/StaticAnalysis/dropped-results.php` marks every line the check must
report with `// dropped`, and `StaticAnalysisTest` runs psalm and PHPStan over
it and compares. It also holds the calls that must not be reported: a `void`
call, a `never` one, and `parent::__construct()`. PHPStan types the last two
`never`, which is a subtype of `Result`, so the rule skips `never` by name. Add
a case there before changing what the check reports.

`tests/StaticAnalysis/branch-built.php` traces boxes built in two branches, and
`StaticAnalysisTest` compares each trace with the type it records for each
tool. Turning either merge off fails it, with `ReservedWord` from psalm and
`Result<never, string>` from PHPStan. Measured on this repo, neither merge
changes how long the tool takes.

## Two traps in the tooling itself

**PHPUnit cannot see a compile-time deprecation.** `failOnDeprecation` is set,
but a deprecation raised while a file is being compiled fires before PHPUnit
has a handler attached to a test. That is how an implicitly nullable parameter
(`string $ex = null`, deprecated in 8.4) sat in the test suite unnoticed. The
only thing that catches those is running the file in a subprocess, which is
what `ExampleScriptTest` does.

**psalm proves the throws, so code after one is unreachable.**
`valueOrThrow()` returns `T`, and `T` is `never` on an `Option::none()`, so
psalm and PHPStan both prove the call cannot return. Anything written after such a call
is reported as `UnevaluatedCode`, and a `$threw` flag read afterwards as
`RedundantCondition`. Use one `expectException` per test rather than fighting
it. One throw per test is what makes that safe. `expectException` ends a test
at the first throw, which is why `5abacf7` had to abandon it when a single test
asserted two.

## Psalm annotations

`psalm.xml` sets `errorLevel="1"` over `src/` and `tests/`, with
`findUnusedCode` on. The `psalm/plugin-phpunit` plugin is what stops psalm
reporting the test classes themselves as unused.

- **Both boxes are covariant** (`@template-covariant`), which is what lets a
  function declared `Option<int>` return `Option::some(count($x))`, and
  `Option::none()` stand in for any `Option`. With invariant templates psalm
  rejected both, and the type fixture never noticed: psalm lets a literal type
  through (`Option<'x'>` into `Option<string>`), and literals are what the
  fixture used. `tests/TypeInference/Assignability.php` now returns boxes from
  typed functions and fails if the variance breaks.
- psalm reports `InvalidTemplateParam` for every `callable(T)` parameter,
  where the value only flows out, so the class docblocks suppress it once.
  That suppression would also hide the one real mistake: a public method with
  a bare `T` or `E` parameter. Never add one. Take a new template instead, the
  way `valueOr(TT $alternative)` does.
- **Never write a `T is never` conditional return type.** A plain union
  already does the job, because a union with `never` in it reduces: on an
  `Option::none()`, `T|TT` is `TT` and `T` is `never`. The conditional only
  did harm. psalm cannot decide `T is never` for an ordinary `T`, so on a
  method that returns a box it returned both branches:
  `Option<U>|Option<never>`. The next typed callback then failed with
  `ReservedWord` and `NoValue`, which once broke 11 of 20 everyday two-call
  chains. PHPStan does decide it, always false, so `valueOr(null)` lost its
  `null` and a caller's null check was called redundant. Write one type
  (`Option<T|TT>`, `Result<T, E|TE>`, `T|null`), and state the class
  invariant in the body with a `/** @var ... */` where psalm cannot see it.
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

PHPStan is a dev dependency, and `composer phpstan` runs it at level max over
`src/`, `static-analysis/` and `tests/TypeInference/Assignability.php`, with
the dropped-Result rule on. `TypeInferenceTest` also runs it over the traced
expressions, each trace turned into a `dumpType()`, and compares every type
with a second recorded list. PHPStan's types differ from psalm's in style: it
widens a literal passed through a method's template, and adds refinements such
as `lowercase-string`.

The two tools disagree in three places, and `phpstan.neon` names each one with
a count, so a new case fails:

- `notNull()`, `notFalsy()` and `notFalse()` bind a template only psalm
  understands, so PHPStan reports the template unused and the cast in the body
  wrong. Callers see PHPStan's own `@phpstan-return`, which the type test
  checks.
- The pure factories' `new self` needs the constructor marked pure for
  PHPStan, and `@phpstan-pure` there makes psalm report the property writes.
- On the empty path, `map()` and friends return `$this` with a `@var` cast
  PHPStan cannot follow. Those lines carry a same-line `@phpstan-ignore` with
  the reason.

PHPStan 1.12 widened a `false` passed to `Option::some()` into `bool`; 2.x
does not.

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
