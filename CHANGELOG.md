# Changelog

This project follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and [Semantic Versioning](https://semver.org/).

## [Unreleased]

The 4.0 work. Nothing is tagged yet, so `composer require ifixit/php7-optional`
still resolves to 3.0.

### Breaking

* **PHP 8.4 is required.** 3.x claimed `^7.2` while CI had tested nothing below
  7.4 since 2021. The toolchain had rotted with it. psalm was pinned to 5.2.0
  from December 2022, and no release in the 5.x line runs on a PHP newer than
  8.3. So the level-1 badge described a check nobody could reproduce.
  If you are on PHP 7, stay on 3.x.
* **`Either::fromArray` uses `array_key_exists` rather than `isset`.** A key
  holding `null` is now `Left(null)` where it used to be a Right.
  `Option::fromArray` always behaved this way, so the two classes disagreed
  about the same input. Chain `leftNotNull()` for the old behavior.
* **`Either::mapLeftSafely` catches `Throwable` rather than `Exception`.** An
  `Error` that used to escape and crash now lands on the right and travels down
  the chain. This is the riskier of the two behavior changes here, because the
  failure is silent rather than loud. `Option::mapSafely` is unchanged and
  still catches `Exception` only, on purpose. `Either` keeps what it caught,
  so catching more loses nothing. `Option` discards it, and swallowing an
  `Error` there would erase a real bug with nothing left to trace it by.
* **`Option`, `Either` and `MissingValueException` are `final`.** Extending the
  two boxes never worked: the static factories build with `new self`, so a
  subclass came back as a plain `Option` or `Either` on the first `map`.

### Added

Getting into a chain, which nothing in 3.x could do from a throwing or falsy
call:

* `Option::fromCallable()` and `Either::fromCallable()`
* `Option::someNotFalsy()`, `Either::notFalsyLeft()`, `Either::notFalsyRight()`
* `Option::someNotFalse()` and `notFalse()`, `Either::notFalseLeft()`,
  `notFalseRight()`, `leftNotFalse()` and `rightNotFalse()`, for PHP's own
  functions that return `false` on failure. `someNotFalsy` treats a match at
  index `0` from `strpos` or `array_search` as missing; these only treat
  `false` that way

Getting out of one, throwing if the value is missing, with a message you
choose or `MissingValueException` when you pass nothing:

* `Option::valueOrThrow()`
* `Either::getLeftOrThrow()` and `Either::getRightOrThrow()`

Catching only the failure you expect, the way a `catch` clause names its
class:

* A `$catch` argument on `Option::fromCallable()`, `Option::mapSafely()`,
  `Either::fromCallable()`, `Either::mapLeftSafely()` and
  `Either::mapRightSafely()`. Only that class and its subclasses are caught,
  and anything else propagates. On `Either` the right side is typed as that
  class, so `Either::fromCallable($fn, JsonException::class)` is an
  `Either<T, JsonException>`. Leaving it out keeps the defaults: `Exception`
  on `Option`, `Throwable` on `Either`

Staying in one through a side effect, since the `match*` methods return `void`
and so end a chain:

* `Option::tap()` and `Option::tapNone()`
* `Either::tapLeft()` and `Either::tapRight()`

Naming the sides, because left and right do not say which one went well:

* `Either::success()`, `failure()`, `isSuccess()`, `isFailure()`,
  `mapSuccess()`, `mapFailure()`, `successOr()`, `successOrThrow()`

Shorthands for shapes callers otherwise spell out by hand:

* `Option::first()`, for `if (count($x)) { return Option::some($x[0]); }`.
  Give it a predicate and it replaces the `foreach` that returns the first
  match, which PHP 8.4's `array_find` cannot do for a list that may hold
  `null`
* `Option::toNullable()`, for a chain ending in `->valueOr(null)` at a
  boundary whose signature is `?T`

Completing the left and right mirror, which was 17 pairs out of 21:

* `Either::flatMapRight()`, `mapRightSafely()`, `notNullRight()`,
  `toOptionFromRight()`


### Fixed

* **Chaining two calls failed psalm at errorLevel 1.** `Option::some(5)->map(...)`
  followed by a second `->map(fn(string $s) => ...)` reported `ReservedWord`
  and `NoValue`, and so did two `mapLeft()` calls, `notNull()->map()`,
  `filter($bool)->map()` and 7 other everyday pairs, 11 out of 20. Every method
  that returns a box had a conditional return type, and psalm cannot decide
  `T is never` for an ordinary `T`. So `map()` returned
  `Option<U>|Option<never>`, and the next typed callback met the empty half.
  Each of those methods now returns one type, and the 20 chains are pinned in
  `tests/TypeInference`. What this gives up: psalm no longer proves that a
  box you built empty stays empty through `map()`, which only mattered for
  code like `Option::none()->map($f)`
* **`Option::someNotNull()` and `someNotFalsy()` kept the value they exist to
  drop.** `Option::someNotNull($maybe)` on a `?string` was `Option<null|string>`,
  so the next `->map(fn(string $s) => ...)` was an `InvalidArgument`. They, and
  `notNull()`, `notFalsy()` and the Either versions, now drop `null` and
  `false` from the type: a row fetch's `array{id: int}|false` comes out as
  `Option<array{id: int}>`
* **`Either::filterLeft()` with a callable claimed a Left always became a
  Right.** `Either::left(10)->filterLeft(fn(int $x) => $x > 5, 'small')` was
  typed `Either<never, 'small'>`; it is `Left(10)`. It is `Either<10, 'small'>`
  now. `filterRight()` had the mirror image
* **`Either::mapRight()` typed a box that might be a Left as unmapped**, so a
  second `mapRight()` got the old right type
* **`Either::orRight` had its conditional branches swapped.** psalm inferred
  `Either<never, float(3.14)>` for `Either::right("original")->orRight(3.14)`,
  whose value is the string `"original"`. It infers
  `Either<never, 'original'|float(3.14)>` now.
* **`Option::map` erased the mapped type.** Its `@psalm-param` named the
  variable before the type, so the template never bound and psalm reported
  `Option<never>` for a box holding a string. `Option::mapSafely` had the same
  reversal plus a return type describing its input rather than its output.
* **`Either::toOptionFromLeft` returned `Option<never>`** for every caller,
  because its `@template U` appeared in no parameter and so bound to nothing.
* **Both `fromArray` methods** had a `$key is key-of<TArray>` conditional that
  psalm never resolved, taking the true branch even for a key it could see was
  absent. `Option::fromArray(['hello' => 'world'], 'hello')->valueOr('x')`
  traced `mixed` and now traces `'world'|'x'`. `Either::fromArray` with no
  fallback is `Either<T, Exception>`, which is what it returns.
* **`Option::filter` had no docblock at all**, so psalm gave it no template and
  the mapped type was lost through it.
* **Eight docblock examples did not run**, plus three in the README. Calls to
  methods that never existed (`->or()` on Either, `->toOption()`, `safeMap()`),
  factories invoked with too few arguments, and variables used but never
  defined. Two more ran but stated the wrong result, because a PHP 8 warning is
  not a throwable.
* **`Either::leftNotFalsy` documented itself with `leftNotNull`'s example.**

### Changed

* `Option` and `Either` are `readonly` classes, so PHP enforces the
  immutability the docblocks only promised. Setting a property on a box, even
  a new dynamic one, is now an `Error` rather than a deprecation notice

* `filter`, `filterLeft` and `filterRight` take `bool|callable` rather than
  `bool`, so the short, guessable name has the lazy option. Passing a bool is
  unchanged. The three lose `@psalm-mutation-free`, because a method that may
  call code you supplied is not mutation-free.

### Removed

* `Option::value()`, `Either::getLeft()` and `Either::getRight()`, which were
  on master but in no tagged release, so no 3.x caller has them. Call
  `valueOrThrow()`, `getLeftOrThrow()` or `getRightOrThrow()` with no argument
  instead: same result, same `MissingValueException`. A name that short hides
  the throw, and an unguarded call to it is the one mistake psalm cannot catch.
  Java made the same call: the documentation for `Optional.get()` names
  `orElseThrow()` as the preferred alternative

* `psalm-baseline.xml`. Every entry in it was already unused, and `src/` and
  `tests/` are clean at errorLevel 1 without it.
* `sami-config.php`. It configured Sami, which is not a dependency, is not
  installed, and has not been maintained for years. Nothing in CI built or
  deployed the docs it described. The generated site at `php7-optional.surge.sh`
  is years stale and still serves pages for `Optional\Result` and
  `Optional\SimpleResult`, deleted back in the 2.x line. The docblocks are the
  API documentation now, and every example in them is executed by the suite.

### Infrastructure

* Every ```php example in `src/` and `README.md` runs as a test, and psalm
  analyses all of them through a generated file. Every public method must carry
  one, the example must call the method it documents rather than mention it in
  a comment or a string, and the method must appear in the README's list. Each
  gate was verified by reintroducing the bug it catches.
* Coverage is measured in CI and fails below a floor, rather than being printed
  into a log nobody reads.
* `TypeInferenceTest` asserts what psalm infers for 27 expressions, so an
  annotation that stops carrying information fails a test. Every type defect
  fixed in this release is among them.
* Mutation testing runs in CI. Three behaviors in this release had a docblock,
  a line in AGENTS.md, and no test that would fail if they were deleted.
* `bin/benchmark.php` measures what the box costs. The README publishes it.
* CI is four jobs: the test matrix, coverage, psalm, and
  `composer validate --strict`. Static analysis and coverage are out of the
  matrix, because pinning psalm to the oldest PHP in it is what froze the
  toolchain for years.
* The suite went from 35 test methods to 81, and from 246 assertions to over a
  thousand. Most of the case count is data providers: every example is its own
  case.

## [3.0]

See the git history. This file starts here.
