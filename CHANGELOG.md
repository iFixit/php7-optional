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

Getting out of one with a message, which `value()`'s fixed
`"Value is missing."` could not:

* `Option::valueOrThrow()`
* `Either::getLeftOrThrow()` and `Either::getRightOrThrow()`

Staying in one through a side effect, since the `match*` methods return `void`
and so end a chain:

* `Option::tap()` and `Option::tapNone()`
* `Either::tapLeft()` and `Either::tapRight()`

Naming the sides, because left and right do not say which one went well:

* `Either::success()`, `failure()`, `isSuccess()`, `isFailure()`,
  `mapSuccess()`, `mapFailure()`, `successOr()`, `successOrThrow()`

Completing the left and right mirror, which was 17 pairs out of 21:

* `Either::flatMapRight()`, `mapRightSafely()`, `notNullRight()`,
  `toOptionFromRight()`

Also `Option::value()`, `Either::getLeft()` and `Either::getRight()`, which
existed on master but were never in a tagged release.

### Fixed

* **`Either::orRight` had its conditional branches swapped.** psalm inferred
  `Either<never, float(3.14)>` for `Either::right("original")->orRight(3.14)`,
  whose value is the string `"original"`.
* **`Option::map` erased the mapped type.** Its `@psalm-param` named the
  variable before the type, so the template never bound and psalm reported
  `Option<never>` for a box holding a string. `Option::mapSafely` had the same
  reversal plus a return type describing its input rather than its output.
* **`Option::map`, `Option::filter` and `Either::mapLeft` did not narrow on the
  empty side**, so psalm accepted `Option::none()->map(...)->value()`, which
  always throws. It reports that as dead code now.
* **`Either::toOptionFromLeft` returned `Option<never>`** for every caller,
  because its `@template U` appeared in no parameter and so bound to nothing.
* **Both `fromArray` methods** had a `$key is key-of<TArray>` conditional that
  psalm never resolved, taking the true branch even for a key it could see was
  absent. `Option::fromArray(['hello' => 'world'], 'hello')->valueOr('x')`
  traced `mixed` and now traces `'world'|'x'`.
* **`Option::filter` had no docblock at all**, so psalm gave it no template and
  the mapped type was lost through it.
* **Eight docblock examples did not run**, plus three in the README. Calls to
  methods that never existed (`->or()` on Either, `->toOption()`, `safeMap()`),
  factories invoked with too few arguments, and variables used but never
  defined. Two more ran but stated the wrong result, because a PHP 8 warning is
  not a throwable.
* **`Either::leftNotFalsy` documented itself with `leftNotNull`'s example.**

### Removed

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
* CI is four jobs: the test matrix, coverage, psalm, and
  `composer validate --strict`. Static analysis and coverage are out of the
  matrix, because pinning psalm to the oldest PHP in it is what froze the
  toolchain for years.
* The suite went from 35 test methods to 81, and from 246 assertions to over a
  thousand. Most of the case count is data providers: every example is its own
  case.

## [3.0]

See the git history. This file starts here.
