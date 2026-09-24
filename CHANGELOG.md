# Changelog

This project follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and [Semantic Versioning](https://semver.org/).

## [Unreleased]

The 4.0 work. Nothing is tagged yet, so `composer require ifixit/php7-optional`
still resolves to 3.0. 4.0 does not try to stay compatible with 3.x: every
change below that breaks a caller says what to write instead.

### Breaking

* **PHP 8.4 is required.** 3.x claimed `^7.2` while CI had tested nothing below
  7.4 since 2021. The toolchain had rotted with it. psalm was pinned to 5.2.0
  from December 2022, and no release in the 5.x line runs on a PHP newer than
  8.3. So the level-1 badge described a check nobody could reproduce.
  If you are on PHP 7, stay on 3.x.
* **`Either` is replaced by `Result`.** `Either` held a left or a right value
  and never said which one meant success. The library treated left as success,
  and callers passed `left:` and `right:` to `match()` by name to keep the two
  straight. `Result` is `Ok` or `Err`, every method acts on the success, and it
  takes `Option`'s method names wherever the idea is the same. It is not 2.x's
  `Result`, which wrapped `Either` under other names (`okay()`, `dataOr()`) and
  was dropped in 3.0. Where each 3.x `Either` method went:

  | 3.x `Either` | 4.0 |
  |---|---|
  | `Either::left($v)`, `Either::right($e)` | `Result::ok($v)`, `Result::err($e)` |
  | `Either::notNullLeft($v, $e)` | `Option::someNotNull($v)->toResult($e)` |
  | `Either::fromArray($a, $k, $e)` | `Option::fromArray($a, $k)->toResult($e)`. A key holding `null` is now `Ok(null)` where 3.x gave a Right; add `->notNull()` before `toResult()` for the old behavior. There is no default error: 3.x made up an `Exception` when you passed none |
  | `Either::leftWhen($v, $e, $f)` | `Result::ok($v)->filter($f, $e)` |
  | `Either::rightWhen($v, $e, $f)` | `Result::ok($v)->filter(fn($x) => !$f($x), $e)` |
  | `isLeft()`, `isRight()` | `isOk()`, `isErr()` |
  | `leftOr($x)`, `rightOr($x)` | `valueOr($x)`, `errOr($x)` |
  | `leftOrCreate($f)` | `valueOrCreate($f)` |
  | `mapLeft($f)`, `mapRight($f)` | `map($f)`, `mapErr($f)` |
  | `mapLeftSafely($f)` | `mapSafely($f)`, which catches `Throwable` where 3.x caught `Exception`; pass `Exception::class` as the second argument for the old behavior |
  | `flatMapLeft($f)`, `andThen($f)` | `flatMap($f)`, with `$f` returning a `Result` |
  | `orLeft($x)`, `orCreateLeft($f)` | `or($x)`, `orCreate($f)` |
  | `elseLeft($r)`, `elseCreateLeft($f)` | `else($r)`, `elseCreate($f)` |
  | `filterLeft($c, $e)`, `filterLeftIf($f, $e)` | `filter($c, $e)`, with `$c` a bool or a callable |
  | `leftNotNull($e)` | `flatMap(fn($v) => Option::someNotNull($v)->toResult($e))` |
  | `leftNotFalsy($e)` | `flatMap(fn($v) => Option::someNotFalsy($v)->toResult($e))` |
  | `leftContains($v)`, `existsLeft($f)` | `contains($v)`, which compares with `===` where 3.x used `==`, and `exists($f)` |
  | `match($l, $r)` | `match(ok: ..., err: ...)` |
  | `matchLeft($f)`, `matchRight($f)` | `tap($f)`, `tapErr($f)`, which return the box rather than `void` |
  | `toOptionFromLeft()` | `toOption()` |

  The eleven methods that act on the right side have no one-call equivalent,
  since `Result` has no reason to turn a failure into a success by default.
  Each rewrite below was checked against 3.0 on both sides:

  <details>
  <summary>The right-side methods, rewritten</summary>

  | 3.x `Either` | 4.0 |
  |---|---|
  | `rightOrCreate($f)` | `match(ok: $f, err: fn($e) => $e)` |
  | `rightContains($x)` | `$r->isErr() && $r->errOr(null) === $x`, compared with `===` where 3.x used `==` |
  | `existsRight($f)` | `match(ok: fn() => false, err: $f)` |
  | `orRight($e)` | `flatMap(fn() => Result::err($e))` |
  | `orCreateRight($f)` | `flatMap(fn($v) => Result::err($f($v)))` |
  | `elseRight($other)` | `flatMap(fn() => $other)` |
  | `elseCreateRight($f)` | `flatMap($f)` |
  | `filterRight($c, $v)` | `elseCreate(fn($e) => $c ? Result::err($e) : Result::ok($v))` |
  | `filterRightIf($f, $v)` | `elseCreate(fn($e) => $f($e) ? Result::err($e) : Result::ok($v))` |
  | `rightNotNull($v)` | `elseCreate(fn($e) => $e === null ? Result::ok($v) : Result::err($e))` |
  | `rightNotFalsy($v)` | `elseCreate(fn($e) => $e ? Result::err($e) : Result::ok($v))` |

  </details>

  `(string)` shows `Ok(...)` and `Err(...)` rather than `Left(...)` and
  `Right(...)`. A cached `Either` unserializes as `__PHP_Incomplete_Class`,
  since the class is gone, and throws an `Error` on the first method call.
  Clear those cache entries when you deploy 4.0.
* **`Option` has one name per operation.** Six 3.x methods repeated another
  method under a second name, and are gone. Each replacement was checked
  against 3.0:

  | 3.x | 4.0 |
  |---|---|
  | `andThen($f)` | `flatMap($f)` |
  | `filterIf($f)` | `filter($f)` |
  | `matchSome($f)`, `matchNone($f)` | `tap($f)`, `tapNone($f)`, which return the box rather than `void` |
  | `Option::someWhen($v, $f)` | `Option::some($v)->filter($f)` |
  | `Option::noneWhen($v, $f)` | `Option::some($v)->filter(fn($x) => !$f($x))` |
* **`Option::contains()` compares with `===`**, as `Result::contains()` does.
  Under 3.x's `==`, `Option::some(1)->contains('1')`,
  `Option::some('1e1')->contains('10')` and `Option::some(null)->contains(false)`
  were all true, and two separate but equal objects matched. For a looser
  test, pass your own comparison to `exists()`.
* **`Option::fromArray()` takes an `int|string` key.** A `null` key throws a
  `TypeError` where 3.x looked up the `""` key, and so do float and bool keys
  in a file with `strict_types`. psalm already typed the key `array-key`.
* **`Option`, `Result` and `MissingValueException` are `final`.** Extending a
  box never worked: the static factories build with `new self`, so a subclass
  came back as a plain box on the first `map`.

### Added

* `Optional\Result`, described under Breaking. `Option::toResult()` and
  `Result::toOption()` cross between the two boxes

Getting into a chain, which nothing in 3.x could do from a throwing or falsy
call:

* `Option::fromCallable()`
* `Option::someNotFalsy()`
* `Option::someNotFalse()` and `notFalse()`, for PHP's own functions that
  return `false` on failure. `someNotFalsy` treats a match at index `0` from
  `strpos` or `array_search` as missing; these only treat `false` that way

Getting out of one, throwing if the value is missing:

* `Option::valueOrThrow()`. Pass a message and it throws a
  `MissingValueException` saying it, or a closure that builds your own
  exception. `Result::valueOrThrow()` takes the same, adds the error's
  description to a message, and keeps a `Throwable` error as the cause

Catching only the failure you expect, the way a `catch` clause names its
class:

* A `$catch` argument on `Option::fromCallable()` and `Option::mapSafely()`.
  Only that class and its subclasses are caught, and anything else
  propagates. Leaving it out catches `Exception`, as 3.x did
* The same argument on `Result::fromCallable()` and `Result::mapSafely()`,
  which also types the error as the class you name:
  `Result::fromCallable($fn, JsonException::class)` is a
  `Result<T, JsonException>`. Leaving it out catches `Throwable`. The
  defaults differ on purpose. `Result` keeps what it caught, so catching more
  loses nothing. `Option` throws it away, and swallowing an `Error` there
  would erase a real bug with nothing left to trace it by

Leaving a block of steps early, the way Rust's `?` returns from a function:

* `Option::do()` and `Result::do()`, with `valueOrReturn()` on both boxes.
  Inside the block, `valueOrReturn()` unwraps a box, and the first empty one
  ends the block with that None or Err. psalm types each unwrapped value
  exactly; the block's error type is `mixed` unless you annotate it

Catching a `Result` that is dropped, the way Rust's `#[must_use]` does:

* A psalm plugin, `Optional\StaticAnalysis\Psalm\Plugin`, and a PHPStan rule
  in `extension.neon`. Each reports a call whose `Result` is dropped on its
  own line, from any function, including a missing `return` before a
  `Result::err()`. `#[\NoDiscard]` covers only the library's own methods, and
  only on PHP 8.5

Turning a batch of boxes into one:

* `Option::all()` and `Result::all()`: every value, keyed as given, or the
  first failure. They stop reading at that failure, so a generator is not
  drained
* `Result::partition()`: the values and the errors, both keyed as given, for
  reporting every failure at once, such as every bad field in a form

Staying in one through a side effect, which 3.x's `matchSome` and
`matchNone` could not do, since they returned `void`:

* `Option::tap()` and `Option::tapNone()`

Shorthands for shapes callers otherwise spell out by hand:

* `Option::first()`, for `if (count($x)) { return Option::some($x[0]); }`.
  Give it a predicate and it replaces the `foreach` that returns the first
  match, which PHP 8.4's `array_find` cannot do for a list that may hold
  `null`. It takes any iterable, keys and all, including a `WeakMap`
* `Option::toNullable()`, for a chain ending in `->valueOr(null)` at a
  boundary whose signature is `?T`

### Fixed

* **psalm rejected ordinary code that returns a box.** The templates were
  invariant, so a function declared `Option<int>` could not return
  `Option::some(count($items))` (an `Option<int<0, max>>`), nor one declared
  `Option<Animal>` return `Option::some(new Dog())`. psalm only let a literal
  type through, which is why nothing noticed. Both boxes are covariant now,
  which also lets a `flatMap` or `elseCreate` callback return a Some on one
  branch and a None on the other with no annotation
* **PHPStan dropped the fallback from `valueOr()`.** It typed
  `$user->valueOr(null)` as `User`, so it missed a null dereference after it
  and called a correct `?->` redundant. `valueOrCreate()` and `errOr()` had the
  same fault. The `T is never` conditionals behind it are gone: a plain `T|TT`
  gives psalm the same types and PHPStan the right ones
* **PHPStan rejected the tap family's own examples.** The callbacks were typed
  `callable(T): void`, and an arrow function returns its expression, so
  `->tap(fn($x) => error_log(...))` failed at level 5 and up because
  `error_log()` returns a bool. They take `callable(T): mixed` now, and the
  return value is ignored as before
* **psalm lost the type of every chain through `Option::map`.** On 3.0 it
  typed each one `Option<never>`, a box it believes is always empty, so
  `$opt->map($f)->valueOr(0)` traced as the literal `0`. `map`'s
  `@psalm-param` named the variable before the type, so the template never
  bound. `Option::mapSafely` had the same reversal plus a return type
  describing its input rather than its output. Every method that returns a box
  now declares one precise type, and 18 everyday two-call chains across both
  boxes are pinned in `tests/TypeInference`, where nothing is suppressed
* **`Option::someNotNull()` kept the `null` it exists to drop.** On a
  `?string`, 3.0 typed it `Option<null|string>`, so psalm reported
  `strlen(Option::someNotNull($maybe)->valueOr('x'))` as a
  `PossiblyNullArgument`. It is `Option<string>` now. `notNull()` and
  `notFalsy()` dropped the right value but added an always-empty
  `Option<never>` to the union. They return one type now, so a row fetch's
  `array{id: int}|false` through `notFalsy()` comes out as
  `Option<array{id: int}>`
* **`Option::fromArray`** had a `$key is key-of<TArray>` conditional that psalm
  never resolved, so the value was `mixed` whatever the key.
  `Option::fromArray(['hello' => 'world'], 'hello')->valueOr('x')` traces
  `'world'|'x'` now
* **`Option::exists()` threw a `TypeError` for a callback that returned `1`.**
  It returned the callback's result from a method declared `bool`. It reads
  the result the way `if` does now, like `filter()` and `Result::exists()`
* **`Option::filter` had no docblock at all**, so psalm gave it no template and
  the mapped type was lost through it
* **`Option::mapSafely`'s example did not run.** It called `safeMap()`, which
  never existed, and showed a caught exception using a missing array key,
  which on PHP 8 is a warning that nothing catches. The README's only complete
  example called `SimpleResult::okay()`, from a class deleted in the 2.x line
* **`(string)` on an `Option` warned or threw for some values.** 3.x
  interpolated the value, so an array printed `Some(Array)` with an
  "Array to string conversion" warning, and an object with no `__toString`
  threw an `Error`. Both boxes now show a value with no string form by its
  type (`Some(array)`, `Some(stdClass)`), an exception by its class and
  message, and an enum case as `Suit::Hearts`. A value whose own
  `__toString()` throws shows by its type, and `NAN` no longer warns on 8.5

### Changed

* `(string)` on a box never shows two different values the same way. Strings
  are quoted, so `Some('Ada')` rather than 3.x's `Some(Ada)`, and `'null'`
  and `null` differ. Floats keep their point (`Some(1.0)`, not `Some(1)`),
  and booleans print as `true` and `false` rather than `1` and nothing. A
  string's control characters are escaped, so the result stays on one line.
  `MissingValueException` messages use the same form: `Result is an Err:
  'timeout'`

* Every method that returns a new box has PHP 8.5's `#[\NoDiscard]`, so
  `$option->map($f);` on its own line, which does nothing, warns on 8.5. psalm
  cannot catch it: it reports a dropped result only for a method it knows
  changes nothing, and a method that runs your callback cannot promise that.
  PHP 8.4 ignores the attribute. The tap family is left unmarked, since
  dropping its result is the normal use. The static factories are marked too,
  with a message that names the usual cause, a missing `return`:
  `if ($input === '') { Result::err('empty'); }` falls through to the success
  path, and on 8.5 it now warns. A test suite that fails on warnings now
  fails wherever it drops a result
* `Option` and `Result` are `readonly` classes, so PHP enforces the
  immutability the docblocks only promised. Setting a property on a box, even
  a new dynamic one, is now an `Error` rather than a deprecation notice. A
  callback that takes its argument by reference, such as `map(end(...))`,
  still works as it did in 3.x: it gets a copy of the value
* Every `Option` parameter and return has a native type, `mixed` where it
  holds a value, as on `Result`. Only `fromArray`'s key accepts less than it
  did (see Breaking)
* psalm infers different types for most methods that return a box, each now a
  single type (see Fixed). A psalm baseline in your own code that recorded the
  old types may need regenerating
* `Option::filter` takes `bool|Closure` rather than `bool`, so the short,
  guessable name has the lazy option. Passing a bool is unchanged, and a
  closure's result counts the way `if` counts it, as `filterIf`'s did. It
  takes a closure rather than any callable so a string is never called: from
  a file without strict types, `filter($flag)` with `$flag = 'var_dump'` is
  `filter(true)`, as in 3.x. It loses `@psalm-mutation-free`, because a method
  that may call code you supplied is not mutation-free

### Removed

* `Option::value()`, which was on master but in no tagged release, so no 3.x
  caller has it. Call `valueOrThrow()` with no argument instead: same result,
  same `MissingValueException`. A name that short hides the throw, and an
  unguarded call to it is the one mistake psalm cannot catch. Java made the
  same call: the documentation for `Optional.get()` names `orElseThrow()` as
  the preferred alternative
* `psalm-baseline.xml`. Every entry in it was already unused, and `src/` and
  `tests/` are clean at errorLevel 1 without it.
* `sami-config.php`. It configured Sami, which is not a dependency, is not
  installed, and has not been maintained for years. Nothing in CI built or
  deployed the docs it described. The generated site at `php7-optional.surge.sh`
  is years stale and still serves pages for 2.x classes deleted long ago. The
  docblocks are the API documentation now, and every example in them is
  executed by the suite.

### Infrastructure

* Every ```php example in `src/` and `README.md` runs as a test, every line an
  example prints is checked against the comment on its `echo`, and psalm
  analyses all of them through a generated file. Every public method must carry
  one, the example must call the method it documents rather than mention it in
  a comment or a string, and the method must appear in the README's list. Each
  gate was verified by reintroducing the bug it catches.
* `SerializationTest` pins the serialized bytes of both boxes, in both
  directions: pinned payloads read back unchanged, and this version writes
  them byte for byte. `Option`'s are the bytes tag 3.0 writes. The payload
  names every private property, so renaming one would make every box cached
  before a deploy throw an `Error` when it is read back.
* Coverage is measured in CI and fails below a floor, rather than being printed
  into a log nobody reads. Every line of `src/` is covered, so the floor is
  100%.
* `TypeInferenceTest` asserts what psalm infers for every traced expression,
  including the two-call chains and the entrances that drop `null` and
  `false`, so an annotation that stops carrying information fails a test.
  Every type defect fixed in this release is among them. It runs PHPStan
  (level max) over the same expressions and records its types too, and
  `tests/TypeInference/Assignability.php` returns boxes from typed functions,
  so a variance mistake fails in either tool.
* `NoDiscardTest` fails when a method that returns a box lacks
  `#[\NoDiscard]` or anything else has it, and on PHP 8.5 checks the warning
  fires. `ResultTest` fails when `Result` stops taking `Option`'s parameter
  names for a method both boxes have.
* Mutation testing runs in CI. Mutants made by hand had already found behavior
  with a docblock, a line in AGENTS.md, and no test that would fail if it were
  deleted, such as which class `Option::mapSafely` catches. The first full run
  killed 272 of 276 mutants; the four survivors were two missing tests, dead
  code, and one equivalent mutant, now skipped by name. Every mutant is killed
  now, and the threshold is 100%.
* `bin/benchmark.php` measures what the box costs. The README publishes it.
* CI is five jobs: the test matrix, coverage, psalm, PHPStan, and
  `composer validate --strict`. Static analysis and coverage are out of the
  matrix, because pinning psalm to the oldest PHP in it is what froze the
  toolchain for years.

## [3.0]

See the git history. This file starts here.
