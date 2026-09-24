<?php

declare(strict_types = 1);

namespace Optional;

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
 * @api
 * @template T
 * @template E
 */
final readonly class Result {
   use DescribesValues;

   /**
    * Finishes PHP 8.5's warning when a call below has its result dropped:
    * "The return value of method ... should either be used or intentionally
    * ignored by casting it as (void), " followed by this.
    */
   private const DISCARDED = 'a box never changes after it is built, so this call only made a new one';

   private bool $isOk;

   /** @psalm-var T */
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
    * @psalm-return (T is never ? TT : T)
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
    * @psalm-return (T is never ? TT : T)
    */
   public function valueOrCreate(callable $alternativeFactory): mixed {
      return $this->isOk
         ? $this->value
         : $alternativeFactory($this->error);
   }

   /**
    * Returns the success value, or throws
    *
    * With no argument it rethrows the error when the error is a `Throwable`,
    * which is what `Result::fromCallable` stores, so the original trace is
    * kept. Any other error becomes a `MissingValueException` that names it.
    * Pass `$exceptionFactory` to say what failed instead; it is handed the
    * error, so the error can become the cause.
    *
    * ```php
    * echo Result::ok(1)->valueOrThrow(), "\n"; // 1
    *
    * try {
    *    Result::err(new \RuntimeException("the api said no"))->valueOrThrow();
    * } catch (\RuntimeException $e) {
    *    echo $e->getMessage(), "\n"; // "the api said no", the same exception
    * }
    *
    * try {
    *    Result::err("timeout")->valueOrThrow(fn(string $error) => new \RuntimeException("could not load: {$error}"));
    * } catch (\RuntimeException $e) {
    *    echo $e->getMessage(), "\n"; // "could not load: timeout"
    * }
    *
    * try {
    *    Result::err("timeout")->valueOrThrow();
    * } catch (\Optional\Exceptions\MissingValueException $e) {
    *    echo $e->getMessage(), "\n"; // "Result is an Err: timeout"
    * }
    * ```
    *
    * _Notes:_
    *
    *  - `$exceptionFactory` must follow this interface `callable(E):Throwable`
    *  - It is called lazily, so building the exception costs nothing on a success
    *
    * @psalm-param null|callable(E):Throwable $exceptionFactory
    * @psalm-return (T is never ? never : T)
    */
   public function valueOrThrow(?callable $exceptionFactory = null): mixed {
      if ($this->isOk) {
         return $this->value;
      }

      if ($exceptionFactory !== null) {
         throw $exceptionFactory($this->error);
      }

      if ($this->error instanceof Throwable) {
         throw $this->error;
      }

      throw new MissingValueException('Result is an Err: ' . self::describe($this->error));
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
    * @psalm-return (E is never ? TT : E)
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
    * @psalm-return (T is never ? null : T|null)
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
      return $this->isOk
         ? $ok($this->value)
         : $err($this->error);
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
    *
    * @psalm-param callable(T):bool $existsFunc
    */
   public function exists(callable $existsFunc): bool {
      return $this->isOk && $existsFunc($this->value);
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
    * echo (string) Result::err('no')->map($square), "\n"; // Err(no), $square never ran
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
      return $this->isOk
         ? self::ok($mapFunc($this->value))
         : self::err($this->error);
   }

   /**
    * Transforms the error, and passes a `Result::ok` through
    *
    * `$mapFunc` runs only iff the result is `Result::err`.
    *
    * ```php
    * $describe = fn(string $error) => "request failed: {$error}";
    *
    * echo (string) Result::err('timeout')->mapErr($describe), "\n"; // Err(request failed: timeout)
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
      return $this->isOk
         ? self::ok($this->value)
         : self::err($mapFunc($this->error));
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
         if ($e instanceof $catch) {
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
    * echo (string) Result::ok('old')->flatMap($parseAge), "\n";      // Err(not a number: old)
    * echo (string) Result::err('no input')->flatMap($parseAge), "\n"; // Err(no input), never parsed
    * ```
    *
    * _Notes:_
    *
    *  - `$mapFunc` must follow this interface `callable(T):Result<U, F>`
    *  - When your callback returns a `Result::ok` on one branch and a
    *    `Result::err` on another, psalm infers a union of two Result types and
    *    cannot bind it to the templates. Give the callback an explicit
    *    `@psalm-return Result<U, F>`, or build the result in one expression as
    *    above, and it binds
    *
    * @template U
    * @template F
    * @psalm-param callable(T):Result<U, F> $mapFunc
    * @psalm-return Result<U, E|F>
    */
   #[\NoDiscard(self::DISCARDED)]
   public function flatMap(callable $mapFunc): self {
      return $this->isOk
         ? $mapFunc($this->value)
         : self::err($this->error);
   }

   /**
    * Turns a `Result::ok` into `Result::err($error)` iff `$condition` is false, or a
    * callable that returns false for the value. A `Result::err` stays as it is.
    *
    * ```php
    * $adult = Result::ok(30)->filter(fn(int $age) => $age >= 18, 'too young');
    * $minor = Result::ok(12)->filter(fn(int $age) => $age >= 18, 'too young');
    *
    * echo (string) $adult, " ", (string) $minor, "\n"; // "Ok(30) Err(too young)"
    * ```
    *
    * _Notes:_
    *
    *  - Pass the callable rather than calling it. A bool is evaluated at the
    *    call site, before this method runs, so an expensive or throwing
    *    expression happens even on a `Result::err`
    *  - `$error` is evaluated at the call site too, whether or not it is used
    *
    * @template TE
    * @psalm-param bool|callable(T):bool $condition
    * @psalm-param TE $error
    * @psalm-return Result<T, E|TE>
    */
   #[\NoDiscard(self::DISCARDED)]
   public function filter(bool|callable $condition, mixed $error): self {
      if (!$this->isOk) {
         return $this;
      }

      $keep = is_callable($condition) ? $condition($this->value) : $condition;

      return $keep === true ? $this : self::err($error);
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
    * echo (string) Result::err('timeout')->orCreate($fallback), "\n"; // Ok(default, after timeout)
    * echo (string) Result::ok('found')->orCreate($fallback), "\n";    // Ok(found)
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
      /** @var Result<T|TT, never> an Ok never holds an error, which psalm cannot see */
      return $this->isOk
         ? $this
         : self::ok($alternativeFactory($this->error));
   }

   /**
    * Returns `$alternativeResult` iff this result is `Result::err`, otherwise this one
    *
    * ```php
    * $fromDisk = Result::ok('settings from disk');
    *
    * echo (string) Result::err('cache miss')->else($fromDisk), "\n";       // Ok(settings from disk)
    * echo (string) Result::ok('settings from cache')->else($fromDisk), "\n"; // Ok(settings from cache)
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
    * echo (string) Result::err('timeout')->elseCreate($retry), "\n"; // Ok(second try, after timeout)
    * echo (string) Result::ok('first try')->elseCreate($retry), "\n"; // Ok(first try)
    * ```
    *
    * _Notes:_
    *
    *  - `$alternativeResultFactory` must follow this interface `callable(E):Result<U, F>`
    *  - The same union caveat as `flatMap` applies to the callback's return type
    *  - Returns `Result<T|U, F>`
    *
    * @template U
    * @template F
    * @psalm-param callable(E):Result<U, F> $alternativeResultFactory
    * @psalm-return Result<T|U, F>
    */
   #[\NoDiscard(self::DISCARDED)]
   public function elseCreate(callable $alternativeResultFactory): self {
      /** @var Result<T|U, F> an Ok never holds an error, which psalm cannot see */
      return $this->isOk
         ? $this
         : $alternativeResultFactory($this->error);
   }

   /**
    * Runs `$sideEffect` on the value iff the result is `Result::ok`, then returns this result
    *
    * ```php
    * $doubled = Result::ok(5)
    *    ->tap(fn(int $x) => error_log("about to double {$x}"))
    *    ->map(fn(int $x) => $x * 2); // Ok(10), and the log line ran
    *
    * $untouched = Result::err('no')->tap(fn(int $x) => error_log("never runs")); // Err(no)
    * ```
    *
    * _Notes:_
    *
    *  - `$sideEffect` must follow this interface `callable(T):void`
    *  - Returns `Result<T, E>`, always this one
    *
    * @psalm-param callable(T):void $sideEffect
    * @psalm-return Result<T, E>
    */
   public function tap(callable $sideEffect): self {
      if ($this->isOk) {
         $sideEffect($this->value);
      }

      return $this;
   }

   /**
    * Runs `$sideEffect` on the error iff the result is `Result::err`, then returns this result
    *
    * For logging a failure without handling it.
    *
    * ```php
    * $failed = Result::err('timeout')
    *    ->tapErr(fn(string $error) => error_log("request failed: {$error}")); // Err(timeout), and the log line ran
    *
    * $fine = Result::ok(5)->tapErr(fn(string $error) => error_log("never runs")); // Ok(5)
    * ```
    *
    * _Notes:_
    *
    *  - `$sideEffect` must follow this interface `callable(E):void`
    *  - Returns `Result<T, E>`, always this one
    *
    * @psalm-param callable(E):void $sideEffect
    * @psalm-return Result<T, E>
    */
   public function tapErr(callable $sideEffect): self {
      if (!$this->isOk) {
         $sideEffect($this->error);
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
    * echo (string) Result::err('not found'), "\n"; // Err(not found)
    * ```
    *
    * @psalm-pure
    * @template TE
    * @psalm-param TE $error
    * @psalm-return Result<never, TE>
    */
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
    * $ok = Result::fromCallable(fn() => 7);                                // Ok(7)
    * $failed = Result::fromCallable(fn() => throw new \Exception("boom")); // Err(Exception: boom)
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
   public static function fromCallable(callable $valueFactory, string $catch = Throwable::class): self {
      try {
         return self::ok($valueFactory());
      } catch (Throwable $e) {
         if ($e instanceof $catch) {
            return self::err($e);
         }

         throw $e;
      }
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
