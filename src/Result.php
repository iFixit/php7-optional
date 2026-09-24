<?php

declare(strict_types = 1);

namespace Optional;

use Closure;
use Optional\Exceptions\EarlyReturn;
use Optional\Exceptions\MissingValueException;
use Optional\Internal\DescribesValues;
use Throwable;

/**
 * The outcome of something that can fail: `Result::ok($value)` or `Result::err($error)`.
 *
 * Use it where a function would otherwise throw, or return `false` and leave
 * the reason in a log. The error travels with the box, so whoever handles the
 * failure can still say what it was.
 *
 * It uses `Option`'s names wherever the idea is the same (`map`, `flatMap`,
 * `filter`, `valueOr`, `valueOrThrow`, `match`, `tap`), and those act on the
 * success. The methods that reach the error say so: `mapErr`, `errOr`,
 * `tapErr`.
 *
 * The class is readonly, so PHP itself refuses to change a box once it is
 * built. It is final because the static factories build with `new self`, so a
 * subclass would be silently downgraded to a `Result` by the first `map` call.
 *
 * Both of its types are covariant: a `Result<Dog, string>` is a
 * `Result<Animal, string>`, and `Result::err('x')` is a `Result` of any value
 * type. That is safe because nothing can put a value into a box after it is
 * built.
 *
 * @api
 * @template-covariant T
 * @template-covariant E
 * @psalm-suppress InvalidTemplateParam psalm reports T and E in every
 *    `callable(T)` and `callable(E)` parameter, where the box only hands them
 *    out, which covariance allows. PHPStan checks those positions properly and
 *    accepts them.
 */
final readonly class Result {
   use DescribesValues;

   /**
    * Finishes PHP 8.5's warning when a call below has its result dropped:
    * "The return value of method ... should either be used or intentionally
    * ignored by casting it as (void), " followed by this.
    */
   private const DISCARDED = 'a box never changes after it is built, so this call only made a new one';

   /**
    * The same, for the static factories. A factory's result dropped on its
    * own line is almost always a missing `return`.
    */
   private const UNREAD = 'nothing reads this box, so what it holds is lost; a missing return is the usual cause';

   private bool $isOk;

   /**
    * Callbacks get a local copy of this and of `$error`, never the properties.
    * A callback that takes its argument by reference, such as `end(...)`,
    * would otherwise try to write to a readonly property and throw.
    *
    * @psalm-var T
    */
   private mixed $value;

   /** @psalm-var E */
   private mixed $error;

   /**
    * @psalm-param T $value
    * @psalm-param E $error
    * @psalm-mutation-free
    */
   private function __construct(bool $isOk, mixed $value, mixed $error) {
      $this->isOk = $isOk;
      $this->value = $value;
      $this->error = $error;
   }

   /**
    * Returns true iff the result is `Result::ok`
    *
    * ```php
    * echo Result::ok(1)->isOk() ? "ok\n" : "err\n";     // "ok"
    * echo Result::err('no')->isOk() ? "ok\n" : "err\n"; // "err"
    * ```
    *
    * @psalm-mutation-free
    */
   public function isOk(): bool {
      return $this->isOk;
   }

   /**
    * Returns true iff the result is `Result::err`
    *
    * ```php
    * echo Result::err('no')->isErr() ? "err\n" : "ok\n"; // "err"
    * echo Result::ok(1)->isErr() ? "err\n" : "ok\n";     // "ok"
    * ```
    *
    * @psalm-mutation-free
    */
   public function isErr(): bool {
      return !$this->isOk;
   }

   /**
    * Returns the success value, or `$alternative` for a `Result::err`
    *
    * ```php
    * echo Result::ok(5)->valueOr(0), "\n";     // 5
    * echo Result::err('no')->valueOr(0), "\n"; // 0
    * ```
    *
    * @psalm-mutation-free
    * @template TT
    * @psalm-param TT $alternative
    * @psalm-return T|TT
    */
   public function valueOr(mixed $alternative): mixed {
      return $this->isOk
         ? $this->value
         : $alternative;
   }

   /**
    * Returns the success value, or what `$alternativeFactory` builds from the error
    *
    * The factory runs only on a `Result::err`, so an expensive default costs
    * nothing on a success.
    *
    * ```php
    * $describe = fn(string $error) => "failed: {$error}";
    *
    * echo Result::ok('done')->valueOrCreate($describe), "\n";     // "done"
    * echo Result::err('timeout')->valueOrCreate($describe), "\n"; // "failed: timeout"
    * ```
    *
    * _Notes:_
    *
    *  - `$alternativeFactory` must follow this interface `callable(E):TT`
    *
    * @template TT
    * @psalm-param callable(E):TT $alternativeFactory
    * @psalm-return T|TT
    */
   public function valueOrCreate(callable $alternativeFactory): mixed {
      $error = $this->error;

      return $this->isOk
         ? $this->value
         : $alternativeFactory($error);
   }

   /**
    * Returns the success value, or throws
    *
    * With no argument it rethrows the error when the error is a `Throwable`,
    * which is what `Result::fromCallable` stores, so the original trace is
    * kept. Any other error becomes a `MissingValueException` that names it.
    *
    * Say what failed, which is almost always worth it. A message becomes a
    * `MissingValueException` that also names the error, with the error as its
    * cause when it is a `Throwable`. A closure is handed the error and builds
    * the exception itself.
    *
    * ```php
    * echo Result::ok(1)->valueOrThrow('could not load user 5'), "\n"; // 1
    *
    * try {
    *    Result::err('timeout')->valueOrThrow('could not load user 5');
    * } catch (\Optional\Exceptions\MissingValueException $e) {
    *    echo $e->getMessage(), "\n"; // "could not load user 5: 'timeout'"
    * }
    *
    * try {
    *    Result::err(new \RuntimeException('the api said no'))->valueOrThrow('could not load user 5');
    * } catch (\Optional\Exceptions\MissingValueException $e) {
    *    echo $e->getPrevious()?->getMessage(), "\n"; // "the api said no", kept as the cause
    * }
    *
    * try {
    *    Result::err('timeout')->valueOrThrow(fn(string $error) => new \DomainException("could not load: {$error}"));
    * } catch (\DomainException $e) {
    *    echo $e->getMessage(), "\n"; // "could not load: timeout"
    * }
    *
    * try {
    *    Result::err(new \RuntimeException('the api said no'))->valueOrThrow();
    * } catch (\RuntimeException $e) {
    *    echo $e->getMessage(), "\n"; // "the api said no", the same exception
    * }
    * ```
    *
    * _Notes:_
    *
    *  - `$orThrow` is a message, or a closure that follows the interface
    *    `callable(E):Throwable`. It takes a closure rather than any callable,
    *    because a string naming a function is also a message
    *  - The closure runs only on a `Result::err`, so building the exception
    *    costs nothing on a success
    *
    * @psalm-param null|string|Closure(E):Throwable $orThrow
    * @psalm-return T
    */
   public function valueOrThrow(string|Closure|null $orThrow = null): mixed {
      if ($this->isOk) {
         return $this->value;
      }

      $error = $this->error;

      if ($orThrow instanceof Closure) {
         throw $orThrow($error);
      }

      if ($orThrow === null && $error instanceof Throwable) {
         throw $error;
      }

      throw new MissingValueException(
         ($orThrow ?? 'Result is an Err') . ': ' . self::describe($error),
         previous: $error instanceof Throwable ? $error : null,
      );
   }

   /**
    * Returns the success value, or ends the enclosing `Result::do()` block with this error
    *
    * The early exit for a block of steps that can each fail, like Rust's `?`.
    * See `Result::do()`.
    *
    * ```php
    * $parse = fn(string $input) => Option::someNotFalse(filter_var($input, FILTER_VALIDATE_INT))
    *    ->toResult("not a number: {$input}");
    *
    * $sum = Result::do(fn() => $parse('2')->valueOrReturn() + $parse('x')->valueOrReturn());
    * echo (string) $sum, "\n"; // Err('not a number: x')
    *
    * echo Result::ok(5)->valueOrReturn(), "\n"; // 5, a value needs no block
    * ```
    *
    * _Notes:_
    *
    *  - On a `Result::err` outside any `Result::do()`, it throws an `Error`
    *    that says so
    *  - psalm and PHPStan type the result as `T`, so the code after it has the
    *    value
    *
    * @psalm-mutation-free
    * @psalm-return T
    */
   public function valueOrReturn(): mixed {
      if (!$this->isOk) {
         throw new EarlyReturn('Result', $this->error);
      }

      return $this->value;
   }

   /**
    * Returns the error, or `$alternative` for a `Result::ok`
    *
    * ```php
    * echo Result::err('timeout')->errOr('no error'), "\n"; // "timeout"
    * echo Result::ok(5)->errOr('no error'), "\n";          // "no error"
    * ```
    *
    * @psalm-mutation-free
    * @template TT
    * @psalm-param TT $alternative
    * @psalm-return E|TT
    */
   public function errOr(mixed $alternative): mixed {
      return $this->isOk
         ? $alternative
         : $this->error;
   }

   /**
    * Returns the success value, or `null` for a `Result::err`
    *
    * For leaving the box at a boundary whose signature is `?T`, where the reason
    * for a failure is no longer anyone's business.
    *
    * ```php
    * $name = Result::ok('Ada')->toNullable();
    * echo $name === null ? "nobody\n" : "{$name}\n"; // "Ada"
    *
    * $missing = Result::err('timeout')->toNullable();
    * echo $missing === null ? "nobody\n" : "{$missing}\n"; // "nobody"
    * ```
    *
    * @psalm-mutation-free
    * @psalm-return T|null
    */
   public function toNullable(): mixed {
      return $this->isOk
         ? $this->value
         : null;
   }

   /**
    * Returns an `Option` of the success value, dropping the error
    *
    * ```php
    * echo (string) Result::ok(10)->toOption(), "\n";        // Some(10)
    * echo (string) Result::err('bad id')->toOption(), "\n"; // None
    * ```
    *
    * @psalm-mutation-free
    * @psalm-return Option<T>
    */
   #[\NoDiscard(self::DISCARDED)]
   public function toOption(): Option {
      return $this->isOk
         ? Option::some($this->value)
         : Option::none();
   }

   /**
    * Runs `$ok` with the value iff the result is `Result::ok`, or `$err` with the
    * error iff it is `Result::err`, and returns what that one returned
    *
    * Name the arguments and the call reads like the two cases it handles.
    *
    * ```php
    * $message = Result::ok(4)->match(
    *    ok: fn(int $x) => "squared: " . ($x * $x),
    *    err: fn(string $error) => "failed: {$error}",
    * );
    * echo $message, "\n"; // "squared: 16"
    *
    * $message = Result::err('timeout')->match(
    *    ok: fn(int $x) => "squared: " . ($x * $x),
    *    err: fn(string $error) => "failed: {$error}",
    * );
    * echo $message, "\n"; // "failed: timeout"
    * ```
    *
    * _Notes:_
    *
    *  - `$ok` must follow this interface `callable(T):U`
    *  - `$err` must follow this interface `callable(E):U`
    *
    * @template U
    * @psalm-param callable(T):U $ok
    * @psalm-param callable(E):U $err
    * @psalm-return U
    */
   public function match(callable $ok, callable $err): mixed {
      $value = $this->value;
      $error = $this->error;

      return $this->isOk
         ? $ok($value)
         : $err($error);
   }

   /**
    * Returns true iff the result is `Result::ok` and its value is `$value`, compared with `===`
    *
    * ```php
    * echo Result::ok(10)->contains(10) ? "yes\n" : "no\n";   // "yes"
    * echo Result::ok(10)->contains('10') ? "yes\n" : "no\n"; // "no", compared strictly
    * echo Result::err(10)->contains(10) ? "yes\n" : "no\n";  // "no", that 10 is the error
    * ```
    *
    * @psalm-mutation-free
    */
   public function contains(mixed $value): bool {
      return $this->isOk && $this->value === $value;
   }

   /**
    * Returns true iff the result is `Result::ok` and `$existsFunc` accepts its value
    *
    * ```php
    * $isAdult = fn(int $age) => $age >= 18;
    *
    * echo Result::ok(30)->exists($isAdult) ? "yes\n" : "no\n";        // "yes"
    * echo Result::ok(12)->exists($isAdult) ? "yes\n" : "no\n";        // "no"
    * echo Result::err('no age')->exists($isAdult) ? "yes\n" : "no\n"; // "no"
    * ```
    *
    * _Notes:_
    *
    *  - `$existsFunc` must follow this interface `callable(T):bool`
    *  - Its result counts the way `if` counts it, as in `filter()`
    *
    * @psalm-param callable(T):bool $existsFunc
    */
   public function exists(callable $existsFunc): bool {
      $value = $this->value;

      return $this->isOk && $existsFunc($value);
   }

   /**
    * Transforms the success value, and passes a `Result::err` through
    *
    * `$mapFunc` runs only iff the result is `Result::ok`.
    *
    * ```php
    * $square = fn(int $x) => $x * $x;
    *
    * echo (string) Result::ok(5)->map($square), "\n";     // Ok(25)
    * echo (string) Result::err('no')->map($square), "\n"; // Err('no'), $square never ran
    * ```
    *
    * _Notes:_
    *
    *  - `$mapFunc` must follow this interface `callable(T):U`
    *
    * @template U
    * @psalm-param callable(T):U $mapFunc
    * @psalm-return Result<U, E>
    */
   #[\NoDiscard(self::DISCARDED)]
   public function map(callable $mapFunc): self {
      if (!$this->isOk) {
         /** @var Result<U, E> an Err holds no value, so it stands in for any value type */
         return $this; // @phpstan-ignore varTag.nativeType (PHPStan cannot see that an Err holds no T)
      }

      $value = $this->value;

      return self::ok($mapFunc($value));
   }

   /**
    * Transforms the error, and passes a `Result::ok` through
    *
    * `$mapFunc` runs only iff the result is `Result::err`.
    *
    * ```php
    * $describe = fn(string $error) => "request failed: {$error}";
    *
    * echo (string) Result::err('timeout')->mapErr($describe), "\n"; // Err('request failed: timeout')
    * echo (string) Result::ok(1)->mapErr($describe), "\n";          // Ok(1), $describe never ran
    * ```
    *
    * _Notes:_
    *
    *  - `$mapFunc` must follow this interface `callable(E):F`
    *
    * @template F
    * @psalm-param callable(E):F $mapFunc
    * @psalm-return Result<T, F>
    */
   #[\NoDiscard(self::DISCARDED)]
   public function mapErr(callable $mapFunc): self {
      if ($this->isOk) {
         /** @var Result<T, F> an Ok holds no error, so it stands in for any error type */
         return $this; // @phpstan-ignore varTag.nativeType (PHPStan cannot see that an Ok holds no E)
      }

      $error = $this->error;

      return self::err($mapFunc($error));
   }

   /**
    * `map`, but a throw from `$mapFunc` becomes the error instead of escaping
    *
    * ```php
    * $decode = fn(string $json) => json_decode($json, flags: JSON_THROW_ON_ERROR);
    *
    * echo Result::ok('{"id": 7}')->mapSafely($decode)->isOk() ? "decoded\n" : "failed\n"; // "decoded"
    *
    * // Name the class you expect: only that is caught, and the error is typed as it
    * $broken = Result::ok('{bad')->mapSafely($decode, \JsonException::class);
    * echo $broken->errOr(null)?->getMessage(), "\n"; // "Syntax error"
    * ```
    *
    * _Notes:_
    *
    *  - `$mapFunc` must follow this interface `callable(T):U`
    *  - `$catch` defaults to `Throwable`, so a `TypeError` from a bad callable
    *    signature lands in the error rather than escaping. That is safe here and
    *    not in `Option::mapSafely`, because this box keeps what it caught
    *  - A subclass of `$catch` is caught too, the same as a `catch` clause
    *  - A `Result::err` passes through, and `$mapFunc` never runs on it
    *
    * @template U
    * @template C of Throwable
    * @psalm-param callable(T):U $mapFunc
    * @psalm-param class-string<C> $catch
    * @psalm-return Result<U, E|C>
    */
   #[\NoDiscard(self::DISCARDED)]
   public function mapSafely(callable $mapFunc, string $catch = Throwable::class): self {
      try {
         return $this->map($mapFunc);
      } catch (Throwable $e) {
         if ($e instanceof $catch && !$e instanceof EarlyReturn) {
            return self::err($e);
         }

         throw $e;
      }
   }

   /**
    * Runs a step that can itself fail on the success value, and returns that step's result
    *
    * A chain of steps that can each fail stops at the first failure and carries
    * its error to the end.
    *
    * ```php
    * $parseAge = fn(string $input) => Option::someNotFalse(filter_var($input, FILTER_VALIDATE_INT))
    *    ->toResult("not a number: {$input}");
    *
    * echo (string) Result::ok('42')->flatMap($parseAge), "\n";       // Ok(42)
    * echo (string) Result::ok('old')->flatMap($parseAge), "\n";      // Err('not a number: old')
    * echo (string) Result::err('no input')->flatMap($parseAge), "\n"; // Err('no input'), never parsed
    * ```
    *
    * _Notes:_
    *
    *  - `$mapFunc` must follow this interface `callable(T):Result<U, F>`
    *
    * @template U
    * @template F
    * @psalm-param callable(T):Result<U, F> $mapFunc
    * @psalm-return Result<U, E|F>
    */
   #[\NoDiscard(self::DISCARDED)]
   public function flatMap(callable $mapFunc): self {
      if (!$this->isOk) {
         /** @var Result<U, E|F> an Err holds no value, so it stands in for any value type */
         return $this; // @phpstan-ignore varTag.nativeType (PHPStan cannot see that an Err holds no T)
      }

      $value = $this->value;

      return $mapFunc($value);
   }

   /**
    * Turns a `Result::ok` into `Result::err($error)` iff `$condition` is false, or a
    * callable that returns false for the value. A `Result::err` stays as it is.
    *
    * ```php
    * $adult = Result::ok(30)->filter(fn(int $age) => $age >= 18, 'too young');
    * $minor = Result::ok(12)->filter(fn(int $age) => $age >= 18, 'too young');
    *
    * echo (string) $adult, " ", (string) $minor, "\n"; // "Ok(30) Err('too young')"
    * ```
    *
    * _Notes:_
    *
    *  - Pass a closure rather than calling it. A bool is evaluated at the
    *    call site, before this method runs, so an expensive or throwing
    *    expression happens even on a `Result::err`
    *  - It takes a closure rather than any callable, so a string is never
    *    called, and a string from a file without strict types is `true`
    *  - `$error` is evaluated at the call site too, whether or not it is used
    *  - A callable's result counts the way `if` counts it, so a callback that
    *    returns `1` or `'1'` keeps the value, as `array_filter` would. psalm
    *    and PHPStan still ask for a `bool`, which catches a callback that
    *    returns something always truthy, such as an object, by mistake
    *
    * @template TE
    * @psalm-param bool|Closure(T):bool $condition
    * @psalm-param TE $error
    * @psalm-return Result<T, E|TE>
    */
   #[\NoDiscard(self::DISCARDED)]
   public function filter(bool|Closure $condition, mixed $error): self {
      $value = $this->value;

      if (!$this->isOk) {
         return $this;
      }

      $keep = $condition instanceof Closure ? $condition($value) : $condition;

      return $keep ? $this : self::err($error);
   }

   /**
    * Returns `Result::ok($alternative)` iff the result is `Result::err`, otherwise this one
    *
    * ```php
    * echo (string) Result::err('timeout')->or(0), "\n"; // Ok(0)
    * echo (string) Result::ok(5)->or(0), "\n";          // Ok(5)
    * ```
    *
    * _Notes:_
    *
    *  - Returns `Result<T|TT, never>`, always an Ok
    *
    * @psalm-mutation-free
    * @template TT
    * @psalm-param TT $alternative
    * @psalm-return Result<T|TT, never>
    */
   #[\NoDiscard(self::DISCARDED)]
   public function or(mixed $alternative): self {
      /** @var Result<T|TT, never> an Ok never holds an error, which psalm cannot see */
      return $this->isOk
         ? $this
         : self::ok($alternative);
   }

   /**
    * Returns `Result::ok()` of what `$alternativeFactory` builds from the error iff the result is `Result::err`, otherwise this one
    *
    * `$alternativeFactory` runs only on a `Result::err`.
    *
    * ```php
    * $fallback = fn(string $error) => "default, after {$error}";
    *
    * echo (string) Result::err('timeout')->orCreate($fallback), "\n"; // Ok('default, after timeout')
    * echo (string) Result::ok('found')->orCreate($fallback), "\n";    // Ok('found')
    * ```
    *
    * _Notes:_
    *
    *  - `$alternativeFactory` must follow this interface `callable(E):TT`
    *  - Returns `Result<T|TT, never>`, always an Ok
    *
    * @template TT
    * @psalm-param callable(E):TT $alternativeFactory
    * @psalm-return Result<T|TT, never>
    */
   #[\NoDiscard(self::DISCARDED)]
   public function orCreate(callable $alternativeFactory): self {
      $error = $this->error;

      /** @var Result<T|TT, never> an Ok never holds an error, which psalm cannot see */
      return $this->isOk
         ? $this
         : self::ok($alternativeFactory($error));
   }

   /**
    * Returns `$alternativeResult` iff this result is `Result::err`, otherwise this one
    *
    * ```php
    * $fromDisk = Result::ok('settings from disk');
    *
    * echo (string) Result::err('cache miss')->else($fromDisk), "\n";       // Ok('settings from disk')
    * echo (string) Result::ok('settings from cache')->else($fromDisk), "\n"; // Ok('settings from cache')
    * ```
    *
    * _Notes:_
    *
    *  - Returns `Result<T|U, F>`
    *
    * @psalm-mutation-free
    * @template U
    * @template F
    * @psalm-param Result<U, F> $alternativeResult
    * @psalm-return Result<T|U, F>
    */
   #[\NoDiscard(self::DISCARDED)]
   public function else(self $alternativeResult): self {
      /** @var Result<T|U, F> an Ok never holds an error, which psalm cannot see */
      return $this->isOk
         ? $this
         : $alternativeResult;
   }

   /**
    * Returns the result `$alternativeResultFactory` builds from the error iff this one is `Result::err`, otherwise this one
    *
    * This is how a chain recovers from a failure it knows how to handle.
    *
    * ```php
    * $retry = fn(string $error) => Result::ok("second try, after {$error}");
    *
    * echo (string) Result::err('timeout')->elseCreate($retry), "\n"; // Ok('second try, after timeout')
    * echo (string) Result::ok('first try')->elseCreate($retry), "\n"; // Ok('first try')
    * ```
    *
    * _Notes:_
    *
    *  - `$alternativeResultFactory` must follow this interface `callable(E):Result<U, F>`
    *  - Returns `Result<T|U, F>`
    *
    * @template U
    * @template F
    * @psalm-param callable(E):Result<U, F> $alternativeResultFactory
    * @psalm-return Result<T|U, F>
    */
   #[\NoDiscard(self::DISCARDED)]
   public function elseCreate(callable $alternativeResultFactory): self {
      $error = $this->error;

      /** @var Result<T|U, F> an Ok never holds an error, which psalm cannot see */
      return $this->isOk
         ? $this
         : $alternativeResultFactory($error);
   }

   /**
    * Runs `$sideEffect` on the value iff the result is `Result::ok`, then returns this result
    *
    * ```php
    * echo (string) Result::ok(5)
    *    ->tap(fn(int $x) => error_log("about to double {$x}"))
    *    ->map(fn(int $x) => $x * 2), "\n"; // Ok(10), and the log line ran
    *
    * echo (string) Result::err('no')->tap(fn(int $x) => error_log("never runs")), "\n"; // Err('no')
    * ```
    *
    * _Notes:_
    *
    *  - `$sideEffect` must follow this interface `callable(T):mixed`. What it
    *    returns is ignored, so an arrow function around `error_log()` is fine
    *  - Returns `Result<T, E>`, always this one
    *
    * @psalm-param callable(T):mixed $sideEffect
    * @psalm-return Result<T, E>
    */
   public function tap(callable $sideEffect): self {
      $value = $this->value;

      if ($this->isOk) {
         $sideEffect($value);
      }

      return $this;
   }

   /**
    * Runs `$sideEffect` on the error iff the result is `Result::err`, then returns this result
    *
    * For logging a failure without handling it.
    *
    * ```php
    * echo (string) Result::err('timeout')
    *    ->tapErr(fn(string $error) => error_log("request failed: {$error}")), "\n"; // Err('timeout'), and the log line ran
    *
    * echo (string) Result::ok(5)->tapErr(fn(string $error) => error_log("never runs")), "\n"; // Ok(5)
    * ```
    *
    * _Notes:_
    *
    *  - `$sideEffect` must follow this interface `callable(E):mixed`. What it
    *    returns is ignored, so an arrow function around `error_log()` is fine
    *  - Returns `Result<T, E>`, always this one
    *
    * @psalm-param callable(E):mixed $sideEffect
    * @psalm-return Result<T, E>
    */
   public function tapErr(callable $sideEffect): self {
      $error = $this->error;

      if (!$this->isOk) {
         $sideEffect($error);
      }

      return $this;
   }

   //////////////////////////////
   // STATIC FACTORY FUNCTIONS //
   //////////////////////////////

   /**
    * Creates a successful result holding `$value`
    *
    * ```php
    * echo (string) Result::ok(200), "\n"; // Ok(200)
    * ```
    *
    * @psalm-pure
    * @template TT
    * @psalm-param TT $value
    * @psalm-return Result<TT, never>
    */
   #[\NoDiscard(self::UNREAD)]
   public static function ok(mixed $value): self {
      /** @var Result<TT, never> */
      return new self(true, $value, null);
   }

   /**
    * Creates a failed result holding `$error`
    *
    * The error can be anything that says what went wrong: a message, an enum
    * case, an exception.
    *
    * ```php
    * echo (string) Result::err('not found'), "\n"; // Err('not found')
    * ```
    *
    * @psalm-pure
    * @template TE
    * @psalm-param TE $error
    * @psalm-return Result<never, TE>
    */
   #[\NoDiscard(self::UNREAD)]
   public static function err(mixed $error): self {
      /** @var Result<never, TE> */
      return new self(false, null, $error);
   }

   /**
    * Runs `$valueFactory`, boxing what it returns as `Result::ok`, or what it threw as `Result::err`
    *
    * This is how a chain starts from code that signals failure by throwing, and
    * it is the piece that keeps the failure with the value. Pass `$catch` to
    * catch only the failure you expect, the way a `catch` clause would, and the
    * error is typed as that class.
    *
    * ```php
    * echo (string) Result::fromCallable(fn() => 7), "\n"; // Ok(7)
    *
    * $failed = Result::fromCallable(fn() => throw new \Exception("boom"));
    * echo (string) $failed, "\n"; // Err(Exception: boom)
    *
    * echo $failed->mapErr(fn(\Throwable $e) => $e->getMessage())->errOr(''), "\n"; // "boom"
    *
    * // psalm types this Result<mixed, JsonException>, and any other throw propagates
    * $parsed = Result::fromCallable(
    *    fn() => json_decode('{bad', flags: JSON_THROW_ON_ERROR),
    *    \JsonException::class
    * );
    * echo $parsed->errOr(null)?->getMessage(), "\n"; // "Syntax error"
    * ```
    *
    * _Notes:_
    *
    *  - `$valueFactory` must follow this interface `callable():TT`
    *  - `$catch` defaults to `Throwable`, so a `TypeError` from a bad callable
    *    signature lands in the error rather than escaping. That is safe here and
    *    not in `Option::fromCallable`, because this box keeps what it caught
    *  - A subclass of `$catch` is caught too, the same as a `catch` clause
    *
    * @template TT
    * @template C of Throwable
    *
    * @psalm-param callable():TT $valueFactory
    * @psalm-param class-string<C> $catch
    *
    * @psalm-return Result<TT, C>
    */
   #[\NoDiscard(self::UNREAD)]
   public static function fromCallable(callable $valueFactory, string $catch = Throwable::class): self {
      try {
         return self::ok($valueFactory());
      } catch (Throwable $e) {
         if ($e instanceof $catch && !$e instanceof EarlyReturn) {
            return self::err($e);
         }

         throw $e;
      }
   }

   /**
    * Runs `$block`, boxing what it returns as `Result::ok`, or the error a `valueOrReturn()` in it met as `Result::err`
    *
    * For steps that can each fail and each need the one before, without a
    * nested `flatMap` per step: write them in order, unwrap each with
    * `valueOrReturn()`, and the first `Result::err` ends the block with its
    * error.
    *
    * ```php
    * $findUser = fn(int $id) => $id > 0 ? Result::ok("user{$id}") : Result::err("no user {$id}");
    * $findCart = fn(string $user) => $user !== 'user13' ? Result::ok(4200) : Result::err("cart for {$user} is locked");
    *
    * $owes = fn(int $id) => Result::do(function() use ($findUser, $findCart, $id) {
    *    $user = $findUser($id)->valueOrReturn();
    *    $cents = $findCart($user)->valueOrReturn();
    *
    *    return "{$user} owes {$cents}";
    * });
    *
    * echo (string) $owes(5), "\n";  // Ok('user5 owes 4200')
    * echo (string) $owes(-1), "\n"; // Err('no user -1')
    * echo (string) $owes(13), "\n"; // Err('cart for user13 is locked')
    * ```
    *
    * _Notes:_
    *
    *  - `$block` must follow this interface `callable():TT`
    *  - psalm types the result `Result<TT, mixed>`, because the errors that can
    *    end the block are not part of its type. Give the variable an
    *    `@var Result<string, string>` annotation when you want them
    *  - `valueOrReturn()` ends the nearest `Result::do()`, even from inside a
    *    function the block calls, the way a throw reaches the nearest catch
    *  - A `catch (Throwable)` or `catch (Error)` in between also catches the
    *    early exit. Let it through by catching `EarlyReturn` first and
    *    rethrowing it. A `catch (Exception)` lets it through already
    *  - Return the plain value. A box returned from the block ends up inside
    *    another box, and a generator is returned without running
    *  - Anything else the block throws propagates. `Result::fromCallable()`
    *    inside the block catches a throw, and never the early exit
    *
    * @template TT
    * @psalm-param callable():TT $block
    * @psalm-return Result<TT, mixed>
    */
   #[\NoDiscard(self::UNREAD)]
   public static function do(callable $block): self {
      try {
         return self::ok($block());
      } catch (EarlyReturn $exit) {
         if ($exit->box !== 'Result') {
            throw $exit;
         }

         return self::err($exit->error);
      }
   }

   /**
    * `Result::ok` with every value, keyed as given, iff every result is ok, otherwise the first `Result::err`
    *
    * For a batch where one failure fails the whole, such as parsing every line
    * of a file. `partition()` keeps going and collects every error instead.
    *
    * ```php
    * $parse = fn(string $input) => Option::someNotFalse(filter_var($input, FILTER_VALIDATE_INT))
    *    ->toResult("not a number: {$input}");
    *
    * echo (string) Result::all(array_map($parse, ['1', '2']))->map(array_sum(...)), "\n"; // Ok(3)
    * echo (string) Result::all(array_map($parse, ['1', 'x', 'y'])), "\n";                 // Err('not a number: x')
    * ```
    *
    * _Notes:_
    *
    *  - It stops at the first `Result::err`, so a generator is read no further
    *  - An empty input gives `Ok([])`
    *  - Keys are kept, so a list comes back as a list
    *
    * @template K of array-key
    * @template TT
    * @template TE
    * @psalm-param iterable<K, Result<TT, TE>> $results
    * @psalm-return Result<array<K, TT>, TE>
    */
   #[\NoDiscard(self::UNREAD)]
   public static function all(iterable $results): self {
      $values = [];

      foreach ($results as $key => $result) {
         if (!$result->isOk) {
            /** @var Result<array<K, TT>, TE> an Err holds no value, so it stands in for any value type */
            return $result;
         }

         $values[$key] = $result->value;
      }

      return self::ok($values);
   }

   /**
    * Splits results into their values and their errors, both keyed as given
    *
    * For reporting every failure at once, such as every invalid field of a
    * form, where `all()` stops at the first.
    *
    * ```php
    * $form = [
    *    'email' => Result::ok('ada@example.com'),
    *    'age' => Result::err('must be a number'),
    *    'name' => Result::err('is required'),
    * ];
    *
    * [$valid, $errors] = Result::partition($form);
    *
    * echo implode(', ', array_keys($valid)), "\n"; // "email"
    * echo implode('; ', array_map(fn(string $field, string $error) => "{$field} {$error}", array_keys($errors), $errors)), "\n"; // "age must be a number; name is required"
    * ```
    *
    * _Notes:_
    *
    *  - Keys are kept, so each error still says which input it came from.
    *    From a list, `array_values()` renumbers them
    *
    * @template K of array-key
    * @template TT
    * @template TE
    * @psalm-param iterable<K, Result<TT, TE>> $results
    * @psalm-return array{0: array<K, TT>, 1: array<K, TE>}
    */
   public static function partition(iterable $results): array {
      $values = [];
      $errors = [];

      foreach ($results as $key => $result) {
         if ($result->isOk) {
            $values[$key] = $result->value;
         } else {
            $errors[$key] = $result->error;
         }
      }

      return [$values, $errors];
   }

   /**
    * Shows the result for debugging, as `Ok(value)` or `Err(error)`
    *
    * A value with no string form is shown by its type, and an exception by its
    * class and message, so this never warns and never throws.
    *
    * ```php
    * echo (string) Result::ok([1, 2]), "\n";                         // Ok(array)
    * echo (string) Result::err(new \RuntimeException('boom')), "\n"; // Err(RuntimeException: boom)
    * ```
    */
   public function __toString(): string {
      return $this->isOk
         ? 'Ok(' . self::describe($this->value) . ')'
         : 'Err(' . self::describe($this->error) . ')';
   }
}
