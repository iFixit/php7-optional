<?php

declare(strict_types = 1);

namespace Optional;

use Closure;
use Exception;
use Optional\Exceptions\EarlyReturn;
use Optional\Exceptions\MissingValueException;
use Optional\Internal\ChecksItems;
use Optional\Internal\DescribesValues;
use Throwable;

/**
 * A box that optionally holds a value: `Option::some($value)` or `Option::none()`.
 *
 * Use it in place of a nullable return so a caller cannot forget the empty case.
 *
 * The class is readonly, so PHP itself refuses to change a box once it is
 * built. It is final because the static factories build with `new self`, so a
 * subclass would be silently downgraded to an `Option` by the first `map` call.
 *
 * Its type is covariant: an `Option<Dog>` is an `Option<Animal>`, and
 * `Option::none()` is an `Option` of anything. That is safe because nothing
 * can put a value into a box after it is built.
 *
 * @api
 * @template-covariant T
 * @psalm-suppress InvalidTemplateParam psalm reports T in every `callable(T)`
 *    parameter, where the box only hands its value out, which covariance allows.
 *    PHPStan checks those positions properly and accepts them.
 */
final readonly class Option {
   use ChecksItems;
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

   private bool $hasValue;

   /**
    * Callbacks get a local copy of this, never the property itself. A callback
    * that takes its argument by reference, such as `end(...)`, would otherwise
    * try to write to a readonly property and throw.
    *
    * @psalm-var T
    */
   private mixed $value;

   /**
    * @psalm-param T $value
    * @psalm-mutation-free
    */
   private function __construct(mixed $value, bool $hasValue) {
      $this->hasValue = $hasValue;
      $this->value = $value;
   }

   /**
    * Returns true iff the option is `Option::some`
    * ```php
    * echo Option::some(1)->hasValue() ? "some\n" : "none\n"; // "some"
    * echo Option::none()->hasValue() ? "some\n" : "none\n";  // "none"
    * ```
    *
    * @psalm-mutation-free
    **/
   public function hasValue(): bool {
      return $this->hasValue;
   }

   /**
    * Returns the value, or `$alternative` for a `Option::none`
    *
    * ```php
    * echo Option::some(1)->valueOr(0), "\n"; // 1
    * echo Option::none()->valueOr(0), "\n";  // 0
    *
    * // a stored null is a value like any other; see notNull()
    * echo var_export(Option::some(null)->valueOr('fallback'), true), "\n"; // NULL
    * ```
    *
    * _Notes:_
    *
    *  - `$alternative` is evaluated at the call site, whether or not it is used.
    *    For a default that is expensive to build, use `valueOrCreate()`
    *
    * @psalm-mutation-free
    * @template TT
    * @psalm-param TT $alternative
    * @psalm-return T|TT
    **/
   public function valueOr(mixed $alternative): mixed {
      return $this->hasValue
         ? $this->value
         : $alternative;
   }

   /**
    * Returns the value, or `null` when there is none
    *
    * The same result as `valueOr(null)`, with a name that says what it is for:
    * leaving the box at a boundary whose signature is `?T`. `valueOr(null)`
    * reads as "fall back to null", which is a different intention, and it does
    * not stand out when you go looking for every place the box is unwrapped.
    *
    * ```php
    * $name = Option::some('Ada')->toNullable();
    * echo $name === null ? "nobody\n" : "{$name}\n"; // "Ada"
    *
    * $missing = Option::none()->toNullable();
    * echo $missing === null ? "nobody\n" : "{$missing}\n"; // "nobody"
    * ```
    *
    * _Notes:_
    *
    *  - `Option::some(null)->toNullable()` is `null`, so a boundary cannot tell
    *    a stored null from an empty box. That is the cost of a `?T` signature,
    *    not of this method
    *
    * @psalm-mutation-free
    * @psalm-return T|null
    **/
   public function toNullable(): mixed {
      return $this->hasValue
         ? $this->value
         : null;
   }

   /**
    * Returns the value, or throws
    *
    * Say what was missing, which is almost always worth it. A message throws a
    * `MissingValueException` with it, and a closure builds the exception to
    * throw. With no argument the message is "Option is None"
    *
    * ```php
    * echo Option::some(1)->valueOrThrow('no id for this device'), "\n"; // 1
    *
    * try {
    *    Option::none()->valueOrThrow('no id for this device');
    * } catch (\Optional\Exceptions\MissingValueException $e) {
    *    echo $e->getMessage(), "\n"; // "no id for this device"
    * }
    *
    * try {
    *    Option::none()->valueOrThrow(fn() => new \DomainException('no id for this device'));
    * } catch (\DomainException $e) {
    *    echo get_class($e), "\n"; // "DomainException"
    * }
    *
    * try {
    *    Option::none()->valueOrThrow();
    * } catch (\Optional\Exceptions\MissingValueException $e) {
    *    echo $e->getMessage(), "\n"; // "Option is None"
    * }
    * ```
    *
    * _Notes:_
    *
    *  - `$orThrow` is a message, or a closure that follows the interface
    *    `callable():Throwable`. It takes a closure rather than any callable,
    *    because a string naming a function is also a message
    *  - A message that is exactly the name of an exception class, such as
    *    `DomainException::class`, gets a hint added, since you almost
    *    certainly meant to throw that class. Pass
    *    `fn() => new \DomainException(...)` for that
    *  - The closure runs only on a `Option::none`, so building the exception
    *    costs nothing when there is a value
    *  - There is no `value()`. A name that short hides the throw, and an
    *    unguarded call to it is the one mistake psalm cannot catch for you
    *
    * @psalm-param null|string|Closure():Throwable $orThrow
    * @psalm-return T
    **/
   public function valueOrThrow(string|Closure|null $orThrow = null): mixed {
      if (!$this->hasValue) {
         throw $orThrow instanceof Closure
            ? $orThrow()
            : new MissingValueException(self::missingMessage($orThrow, 'Option is None'));
      }

      return $this->value;
   }

   /**
    * Returns the value, or ends the enclosing `Option::do()` block with `Option::none()`
    *
    * The early exit for a block of steps that each need a value, like Rust's
    * `?`. See `Option::do()`.
    *
    * ```php
    * $ports = ['http' => 80];
    *
    * $both = Option::do(fn() => [
    *    Option::fromArray($ports, 'http')->valueOrReturn(),
    *    Option::fromArray($ports, 'https')->valueOrReturn(), // no such key, so the block ends here
    * ]);
    * echo (string) $both, "\n"; // None
    *
    * echo Option::some(5)->valueOrReturn(), "\n"; // 5, a value needs no block
    * ```
    *
    * _Notes:_
    *
    *  - On a `Option::none` outside any `Option::do()`, it throws an `Error`
    *    that says so
    *  - psalm and PHPStan type the result as `T`, so the code after it has the
    *    value
    *
    * @psalm-mutation-free
    * @psalm-return T
    */
   public function valueOrReturn(): mixed {
      if (!$this->hasValue) {
         throw new EarlyReturn('Option', null);
      }

      return $this->value;
   }

   /**
    * Runs `$sideEffect` on the value iff the option is `Option::some`, then returns the original option
    *
    * For a side effect such as logging, partway through a chain. The chain goes
    * on with the same box.
    *
    * ```php
    * echo (string) Option::some(5)
    *    ->tap(fn(int $x) => error_log("about to double {$x}"))
    *    ->map(fn(int $x) => $x * 2), "\n"; // Some(10), and the log line ran
    *
    * echo (string) Option::none()->tap(fn($x) => error_log("never runs")), "\n"; // None
    * ```
    *
    * _Notes:_
    *
    *  - `$sideEffect` must follow this interface `callable(T):mixed`. What it
    *    returns is ignored, so an arrow function around `error_log()` is fine
    *  - Returns `Option<T>`, always the original one
    *
    * @psalm-param callable(T):mixed $sideEffect
    * @psalm-return self<T>
    **/
   public function tap(callable $sideEffect): self {
      $value = $this->value;

      if ($this->hasValue) {
         $sideEffect($value);
      }

      return $this;
   }

   /**
    * Runs `$sideEffect` iff the option is `Option::none`, then returns the original option
    *
    * The counterpart to `tap`, for noting an empty result partway through a
    * chain.
    *
    * ```php
    * echo (string) Option::none()
    *    ->tapNone(fn() => error_log("cache miss"))
    *    ->orCreate(fn() => 'from source'), "\n"; // Some('from source'), and the log line ran
    *
    * echo (string) Option::some(1)->tapNone(fn() => error_log("never runs")), "\n"; // Some(1)
    * ```
    *
    * _Notes:_
    *
    *  - `$sideEffect` takes no argument, because a `Option::none` holds nothing.
    *    What it returns is ignored
    *  - Returns `Option<T>`, always the original one
    *
    * @psalm-param callable():mixed $sideEffect
    * @psalm-return self<T>
    **/
   public function tapNone(callable $sideEffect): self {
      if (!$this->hasValue) {
         $sideEffect();
      }

      return $this;
   }

   /**
    * Returns the value, or what `$alternativeFactory` builds for a `Option::none`
    *
    * The factory runs only on a `Option::none`, so an expensive default costs
    * nothing when there is a value.
    *
    * ```php
    * $calls = 0;
    * $lookUp = function() use (&$calls) { $calls++; return 42; };
    *
    * echo Option::some(1)->valueOrCreate($lookUp), "\n"; // 1
    * echo Option::none()->valueOrCreate($lookUp), "\n";  // 42
    * echo $calls, "\n";                                  // 1, it ran for the None only
    * ```
    *
    * _Notes:_
    *
    *  - `$alternativeFactory` must follow this interface `callable():TT`
    *
    * @template TT
    * @psalm-param callable():TT $alternativeFactory
    * @psalm-return T|TT
    **/
   public function valueOrCreate(callable $alternativeFactory): mixed {
      return $this->hasValue
         ? $this->value
         : $alternativeFactory();
   }

   /**
    * Returns `Option::some($alternative)` iff the option is `Option::none`, otherwise this one
    *
    * ```php
    * echo (string) Option::none()->or(10), "\n"; // Some(10)
    * echo (string) Option::some(5)->or(10), "\n"; // Some(5)
    * ```
    *
    * _Notes:_
    *
    *  - Returns `Option<T|TT>`
    *  - `$alternative` is evaluated at the call site, whether or not it is used.
    *    For one that is expensive to build, use `orCreate()`
    *
    * @psalm-mutation-free
    *
    * @template TT
    * @psalm-param TT $alternative
    *
    * @psalm-return self<T|TT>
    */
   #[\NoDiscard(self::DISCARDED)]
   public function or(mixed $alternative): self {
      return $this->hasValue
         ? $this
         : self::some($alternative);
   }

   /**
    * Returns `Option::some()` of what `$alternativeFactory` returns iff the option is `Option::none`, otherwise this one
    *
    * `$alternativeFactory` runs only on a `Option::none`, so an expensive default
    * costs nothing when there is a value.
    *
    * ```php
    * $fromSource = fn() => 'from source';
    *
    * echo (string) Option::none()->orCreate($fromSource), "\n";         // Some('from source')
    * echo (string) Option::some('cached')->orCreate($fromSource), "\n"; // Some('cached'), $fromSource never ran
    * ```
    *
    * _Notes:_
    *
    *  - `$alternativeFactory` must follow this interface `callable():TT`
    *  - Returns `Option<T|TT>`
    *
    * @template TT
    * @psalm-param callable():TT $alternativeFactory
    *
    * @psalm-return self<T|TT>
    */
   #[\NoDiscard(self::DISCARDED)]
   public function orCreate(callable $alternativeFactory): self {
      return $this->hasValue
         ? $this
         : self::some($alternativeFactory());
   }

   /**
    * Returns `$alternative` iff this option is `Option::none`, otherwise this one
    *
    * ```php
    * $fromDisk = Option::some('settings from disk');
    *
    * echo (string) Option::none()->else($fromDisk), "\n";                     // Some('settings from disk')
    * echo (string) Option::some('settings from cache')->else($fromDisk), "\n"; // Some('settings from cache')
    * ```
    *
    * _Notes:_
    *
    *  - Returns `Option<T|TT>`
    *
    * @psalm-mutation-free
    * @template TT
    * @psalm-param Option<TT> $alternative
    * @psalm-return Option<T|TT>
    **/
   #[\NoDiscard(self::DISCARDED)]
   public function else(self $alternative): self {
      return $this->hasValue
         ? $this
         : $alternative;
   }

   /**
    * Returns the option `$alternativeFactory` builds iff this one is `Option::none`, otherwise this one
    *
    * `$alternativeFactory` runs only on a `Option::none`.
    *
    * ```php
    * $retry = fn() => Option::some('second try');
    *
    * echo (string) Option::none()->elseCreate($retry), "\n";            // Some('second try')
    * echo (string) Option::some('first try')->elseCreate($retry), "\n"; // Some('first try'), $retry never ran
    * ```
    *
    * _Notes:_
    *
    *  - `$alternativeFactory` must follow this interface `callable():Option<TT>`
    *  - Returns `Option<T|TT>`
    *
    * @template TT
    * @psalm-param callable():Option<TT> $alternativeFactory
    * @psalm-return Option<T|TT>
    **/
   #[\NoDiscard(self::DISCARDED)]
   public function elseCreate(callable $alternativeFactory): self {
      return $this->hasValue
         ? $this
         : $alternativeFactory();
   }

   /**
    * Runs `$some` with the value iff the option is `Option::some`, or `$none` iff
    * it is `Option::none`, and returns what that one returned
    *
    * Name the arguments and the call reads like the two cases it handles.
    *
    * ```php
    * $squared = Option::some(4)->match(
    *    some: fn(int $x) => $x * $x,
    *    none: fn() => 0,
    * );
    * echo $squared, "\n"; // 16
    *
    * $greeting = Option::none()->match(
    *    some: fn(string $name) => "hello {$name}",
    *    none: fn() => "hello stranger",
    * );
    * echo $greeting, "\n"; // "hello stranger"
    * ```
    *
    * _Notes:_
    *
    *  - `$some` must follow this interface `callable(T):U`
    *  - `$none` must follow this interface `callable():U`
    *
    * @template U
    * @psalm-param callable(T):U $some
    * @psalm-param callable():U $none
    * @psalm-return U
    **/
   public function match(callable $some, callable $none): mixed {
      $value = $this->value;

      return $this->hasValue
         ? $some($value)
         : $none();
   }

   /**
    * Transforms the value, and passes a `Option::none` through
    *
    * `$mapFunc` runs only iff the option is `Option::some`.
    *
    * ```php
    * $square = fn(int $x) => $x * $x;
    *
    * echo (string) Option::some(5)->map($square), "\n"; // Some(25)
    * echo (string) Option::none()->map($square), "\n";  // None, $square never ran
    * ```
    *
    * _Notes:_
    *
    *  - `$mapFunc` must follow this interface `callable(T):U`
    *  - Returns `Option<U>`
    *
    * @template U
    * @psalm-param callable(T):U $mapFunc
    * @psalm-return Option<U>
    **/
   #[\NoDiscard(self::DISCARDED)]
   public function map(callable $mapFunc): self {
      if (!$this->hasValue) {
         /** @var Option<U> an empty box holds no value, so it stands in for any type */
         return $this; // @phpstan-ignore varTag.nativeType (PHPStan cannot see that a None holds no T)
      }

      $value = $this->value;

      return self::some($mapFunc($value));
   }

   /**
    * `map`, but a throw from `$mapFunc` gives you `Option::none()` instead of escaping
    *
    * `$mapFunc` runs only on a `Option::some`, and a `Option::none` passes through.
    *
    * ```php
    * $boom = fn(array $row) => throw new \Exception("boom");
    * echo (string) Option::some(['key' => 'value'])->mapSafely($boom), "\n"; // None
    *
    * // Name the class you expect, and anything else still throws
    * $parsed = Option::some('{bad')->mapSafely(
    *    fn(string $json) => json_decode($json, flags: JSON_THROW_ON_ERROR),
    *    \JsonException::class
    * );
    * echo (string) $parsed, "\n"; // None
    * ```
    *
    * _Notes:_
    *
    *  - `$mapFunc` must follow this interface `callable(T):U`
    *  - Returns `Option<U>`
    *  - `$catch` defaults to `Exception`, not `Throwable`. An `Error` is a bug in
    *    your code, and this box discards what it caught, so swallowing one would
    *    erase the bug with nothing left to find it by. Name an `Error` class in
    *    `$catch` to catch one anyway. `Result::mapSafely` defaults to
    *    `Throwable`, because it keeps what it caught
    *  - A subclass of `$catch` is caught too, the same as a `catch` clause
    *
    * @template U
    *
    * @psalm-param callable(T):U $mapFunc
    * @psalm-param class-string<Throwable> $catch
    *
    * @psalm-return self<U>
    */
   #[\NoDiscard(self::DISCARDED)]
   public function mapSafely(callable $mapFunc, string $catch = Exception::class): self {
      try {
         return $this->map($mapFunc);
      } catch (Throwable $e) {
         if ($e instanceof $catch && !$e instanceof EarlyReturn) {
            return self::none();
         }

         throw $e;
      }
   }

   /**
    * Runs a step that can itself come up empty on the value, and returns that step's option
    *
    * ```php
    * $somePerson = [
    *     'name' => [
    *        'first' => 'First',
    *        'last' => 'Last'
    *     ]
    *  ];
    *
    * $person = Option::fromArray($somePerson, 'name');
    *
    * $name = $person->flatMap(fn(array $person): Option => Option::someNotFalsy($person['first'] . $person['last']));
    *
    * echo (string) $name, "\n"; // Some('FirstLast')
    * ```
    *
    * _Notes:_
    *
    *  - `$mapFunc` must follow this interface `callable(T):Option<U>`
    *
    * @template U
    * @psalm-param callable(T):Option<U> $mapFunc
    * @psalm-return Option<U>
    **/
   #[\NoDiscard(self::DISCARDED)]
   public function flatMap(callable $mapFunc): self {
      if (!$this->hasValue) {
         /** @var Option<U> an empty box holds no value, so it stands in for any type */
         return $this; // @phpstan-ignore varTag.nativeType (PHPStan cannot see that a None holds no T)
      }

      $value = $this->value;

      return $mapFunc($value);
   }

   /**
    * Turns `Option::some($value)` into `Option::none()` iff `$condition` is false,
    * or a callable that returns false for the value. A `Option::none` stays one.
    *
    * ```php
    * $big = fn(int $x) => $x > 5;
    *
    * echo (string) Option::some(10)->filter($big), "\n"; // Some(10)
    * echo (string) Option::some(3)->filter($big), "\n";  // None
    * echo (string) Option::some(3)->filter(true), "\n";  // Some(3), a bool works too
    * ```
    *
    * _Notes:_
    *
    *  - Pass a `bool` and PHP evaluates it at the call site, before this method
    *    runs, so an expensive or throwing expression happens even on a
    *    `Option::none`. Pass a closure instead and it only runs on a
    *    `Option::some`
    *  - It takes a closure rather than any callable, so a string is never
    *    called: `filter('var_dump')` from a file without strict types is
    *    `filter(true)`, as it was in 3.x, not a call to `var_dump()`
    *  - A callable's result counts the way `if` counts it, so a callback that
    *    returns `1` or `'1'` keeps the value, as `array_filter` would. psalm
    *    and PHPStan still ask for a `bool`, which catches a callback that
    *    returns something always truthy, such as an object, by mistake
    *  - This method is not `@psalm-mutation-free`, because it may call code you
    *    supplied. That is the cost of taking a callable at all
    *  - Returns `Option<T>`
    *
    * ```php
    * $calls = 0;
    * $check = function() use (&$calls) { $calls++; return true; };
    *
    * $eager = Option::none()->filter($check()); // $check ran: PHP evaluated the argument
    * $lazy = Option::none()->filter($check);    // $check did not run
    *
    * echo $calls, "\n"; // 1
    * ```
    *
    * @psalm-param bool|Closure(T):bool $condition
    * @psalm-return self<T>
    */
   #[\NoDiscard(self::DISCARDED)]
   public function filter(bool|Closure $condition): self {
      $value = $this->value;

      if (!$this->hasValue) {
         return $this;
      }

      $keep = $condition instanceof Closure ? $condition($value) : $condition;

      return $keep ? $this : self::none();
   }

   /**
    * Turns `Option::some(null)` into `Option::none()`, and keeps every other value
    *
    * ```php
    * echo (string) Option::some(null)->notNull(), "\n"; // None, a stored null becomes an empty box
    * echo (string) Option::some(0)->notNull(), "\n";    // Some(0)
    * ```
    *
    * @psalm-mutation-free
    * @template TNotNull
    * @psalm-if-this-is Option<TNotNull|null>
    * @psalm-return self<TNotNull>
    * @phpstan-return (T is null ? self<never> : self<T>)
    */
   #[\NoDiscard(self::DISCARDED)]
   public function notNull(): self {
      /** @var self<TNotNull> a null value never survives the check */
      return $this->hasValue && is_null($this->value)
         ? self::none()
         : $this;
   }

   /**
    * Turn a `Option::some($value)` into an `Option::none()` iff `$value` is falsy
    *
    * ```php
    * $row = false; // what a row fetch hands back when it finds nothing
    *
    * echo (string) Option::some($row)->notFalsy(), "\n";  // None
    * echo (string) Option::some('')->notFalsy(), "\n";    // None
    * echo (string) Option::some('0.0')->notFalsy(), "\n"; // Some('0.0'), truthy in PHP
    * ```
    *
    * _Notes:_
    *
    *  - psalm drops `null` and `false` from the type, so an `Option<array|false>`
    *    comes out as `Option<array>`. The other falsy values (`0`, `''`, `'0'`,
    *    `[]`) are removed at runtime but stay in the type
    *  - For a box whose type is exactly `null`, psalm has nothing left to bind
    *    and keeps `Option<null>`. That is wider than the always-empty result,
    *    but still correct
    *
    * @psalm-mutation-free
    * @template TNotFalsy
    * @psalm-if-this-is Option<TNotFalsy|null|false>
    * @psalm-return self<TNotFalsy>
    * @phpstan-return (T is null ? self<never> : (T is false ? self<never> : self<T>))
    */
   #[\NoDiscard(self::DISCARDED)]
   public function notFalsy(): self {
      /** @var self<TNotFalsy> a falsy value never survives the check */
      return $this->hasValue && self::isFalsy($this->value)
         ? self::none()
         : $this;
   }

   /**
    * PHP's falsy set. `NAN` is truthy, and is checked first, because PHP 8.5
    * warns when it is turned into a bool.
    *
    * @psalm-pure
    */
   private static function isFalsy(mixed $value): bool {
      return !(is_float($value) && is_nan($value)) && !((bool) $value);
   }

   /**
    * Turn a `Option::some(false)` into an `Option::none()`, and keep every other value
    *
    * PHP's own functions say "not found" with `false`: `strpos`, `array_search`,
    * `file_get_contents`. Only `false` means that there. `notFalsy()` would also
    * drop a match at index `0`, or a file that is empty.
    *
    * ```php
    * echo (string) Option::some(array_search('a', ['a', 'b']))->notFalse(), "\n"; // Some(0)
    * echo (string) Option::some(array_search('z', ['a', 'b']))->notFalse(), "\n"; // None
    * ```
    *
    * _Notes:_
    *
    *  - psalm drops `false` from the type, so `Option<int|false>` comes out as
    *    `Option<int>`
    *
    * @psalm-mutation-free
    * @template TNotFalse
    * @psalm-if-this-is Option<TNotFalse|false>
    * @psalm-return self<TNotFalse>
    * @phpstan-return (T is false ? self<never> : self<T>)
    */
   #[\NoDiscard(self::DISCARDED)]
   public function notFalse(): self {
      /** @var self<TNotFalse> a false value never survives the check */
      return $this->hasValue && $this->value === false
         ? self::none()
         : $this;
   }

   /**
    * Returns true iff the option is `Option::some` and its value is `$value`, compared with `===`
    *
    * ```php
    * echo Option::some(10)->contains(10) ? "yes\n" : "no\n";   // "yes"
    * echo Option::some(10)->contains('10') ? "yes\n" : "no\n"; // "no", compared strictly
    * echo Option::none()->contains(null) ? "yes\n" : "no\n";   // "no", a None holds nothing
    * ```
    *
    * _Notes:_
    *
    *  - An object matches only the same instance. For any other comparison,
    *    pass it to `exists()`
    *
    * @psalm-mutation-free
    */
   public function contains(mixed $value): bool {
      return $this->hasValue && $this->value === $value;
   }

   /**
    * Returns true iff the option is `Option::some` and `$existsFunc` accepts its value
    *
    * ```php
    * $isAdult = fn(int $age) => $age >= 18;
    *
    * echo Option::some(30)->exists($isAdult) ? "yes\n" : "no\n"; // "yes"
    * echo Option::some(12)->exists($isAdult) ? "yes\n" : "no\n"; // "no"
    * echo Option::none()->exists($isAdult) ? "yes\n" : "no\n";   // "no", $isAdult never ran
    * ```
    *
    * _Notes:_
    *
    *  - `$existsFunc` must follow this interface `callable(T):bool`
    *  - Its result counts the way `if` counts it, as in `filter()`
    *
    * @psalm-param callable(T):bool $existsFunc
    **/
   public function exists(callable $existsFunc): bool {
      $value = $this->value;

      return $this->hasValue && $existsFunc($value);
   }

   /**
    * Turns the option into a `Result`: its value as `Result::ok`, or `Result::err($error)` for a `Option::none`
    *
    * This is how a missing value gets a reason, and it is how a `Result` starts
    * from a null, a falsy value or a missing key: any entrance here, then this.
    *
    * ```php
    * $row = ['id' => 7];
    *
    * echo (string) Option::fromArray($row, 'id')->toResult('no id'), "\n";       // Ok(7)
    * echo (string) Option::fromArray($row, 'email')->toResult('no email'), "\n"; // Err('no email')
    * ```
    *
    * _Notes:_
    *
    *  - `$error` is evaluated at the call site, whether or not it is used
    *
    * @psalm-mutation-free
    * @template TE
    * @psalm-param TE $error
    * @psalm-return Result<T, TE>
    */
   #[\NoDiscard(self::DISCARDED)]
   public function toResult(mixed $error): Result {
      return $this->hasValue
         ? Result::ok($this->value)
         : Result::err($error);
   }

   //////////////////////////////
   // STATIC FACTORY FUNCTIONS //
   //////////////////////////////

   /**
    * Creates an option holding `$someValue`
    *
    * ```php
    * echo (string) Option::some(1), "\n";    // Some(1)
    * echo (string) Option::some(null), "\n"; // Some(null), a stored null is still a value
    * ```
    *
    * _Notes:_
    *
    * - Returns `Option<TT>`
    *
    * @psalm-pure
    * @template TT
    * @psalm-param TT $someValue
    * @psalm-return Option<TT>
    **/
   #[\NoDiscard(self::UNREAD)]
   public static function some(mixed $someValue): self {
      return new self($someValue, true);
   }

   /**
    * Creates an empty option
    *
    * ```php
    * echo (string) Option::none(), "\n"; // None
    * ```
    *
    * _Notes:_
    *
    * - Returns `Option<never>`, which fits any declared `Option<T>`
    *
    * @psalm-pure
    * @psalm-return Option<never>
    **/
   #[\NoDiscard(self::UNREAD)]
   public static function none(): self {
      /** @var Option<never> */
      return new self(null, false);
   }

   /**
    * Boxes `$someValue` unless it is `null`, otherwise `Option::none()`
    *
    * ```php
    * echo (string) Option::someNotNull('Ada'), "\n"; // Some('Ada')
    * echo (string) Option::someNotNull(null), "\n";  // None
    * ```
    *
    * _Notes:_
    *
    * - Returns `Option<TT>`
    *
    * @psalm-pure
    * @template TT
    *
    * @psalm-param TT|null $someValue
    *
    * @psalm-return self<TT>
    */
   #[\NoDiscard(self::UNREAD)]
   public static function someNotNull(mixed $someValue): self {
      return $someValue === null ? self::none() : self::some($someValue);
   }

   /**
    * Boxes `$someValue` unless it is falsy, otherwise `Option::none()`
    *
    * The falsy counterpart to `Option::someNotNull`. Reach for it when PHP hands
    * you the usual "nothing found" value rather than null: `false` from a db
    * row fetch, `''` from a lookup, `[]` from a filter.
    *
    * ```php
    * echo (string) Option::someNotFalsy(false), "\n"; // None, e.g. a row fetch that found nothing
    * echo (string) Option::someNotFalsy(''), "\n";    // None
    * echo (string) Option::someNotFalsy([]), "\n";    // None
    * echo (string) Option::someNotFalsy('0.0'), "\n"; // Some('0.0'), which is truthy in PHP
    * ```
    *
    * _Notes:_
    *
    * - Returns `Option<TT>`
    * - PHP's falsy set, so `'0'` is a None but `'0.0'` is a Some. See `notFalsy()`
    *
    * @psalm-pure
    * @template TT
    *
    * @psalm-param TT|null|false $someValue
    *
    * @psalm-return self<TT>
    */
   #[\NoDiscard(self::UNREAD)]
   public static function someNotFalsy(mixed $someValue): self {
      /** @var self<TT> a falsy value never gets a box */
      return self::isFalsy($someValue) ? self::none() : self::some($someValue);
   }

   /**
    * Take a value, turn it into `Option::some($value)` unless it is `false`, otherwise `Option::none()`
    *
    * The entrance for PHP's own functions that return `false` on failure:
    * `strpos`, `array_search`, `file_get_contents`, `fopen`. Only `false` means
    * "not found" there, so a match at index `0` or an empty file is still a
    * Some, where `someNotFalsy` would make both a None.
    *
    * ```php
    * $index = Option::someNotFalse(array_search('a', ['a', 'b']));
    * echo (string) $index, "\n"; // Some(0), where someNotFalsy gives None
    *
    * echo (string) Option::someNotFalse(array_search('z', ['a', 'b'])), "\n"; // None
    * ```
    *
    * _Notes:_
    *
    *  - Returns `Option<TT>`: psalm drops `false`, so an `int|false` goes in and
    *    an `Option<int>` comes out
    *
    * @psalm-pure
    * @template TT
    *
    * @psalm-param TT|false $someValue
    *
    * @psalm-return self<TT>
    */
   #[\NoDiscard(self::UNREAD)]
   public static function someNotFalse(mixed $someValue): self {
      return $someValue === false ? self::none() : self::some($someValue);
   }

   /**
    * Runs `$valueFactory`, boxing what it returns, or returning `Option::none()` if it throws
    *
    * This is how a chain starts from code that signals failure by throwing. Pass
    * `$catch` to say which throw means "there isn't one", the way a `catch`
    * clause would, and let every other failure propagate.
    *
    * ```php
    * echo (string) Option::fromCallable(fn() => 7), "\n";                            // Some(7)
    * echo (string) Option::fromCallable(fn() => throw new \Exception("boom")), "\n"; // None
    *
    * // A missing record means None. Any other failure is a real error.
    * $missing = Option::fromCallable(
    *    fn() => throw new \OutOfBoundsException("no user 5"),
    *    \OutOfBoundsException::class
    * );
    * echo (string) $missing, "\n"; // None
    *
    * try {
    *    $user = Option::fromCallable(fn() => throw new \LogicException("a bug"), \OutOfBoundsException::class);
    * } catch (\LogicException $e) {
    *    echo $e->getMessage(), "\n"; // "a bug"
    * }
    * ```
    *
    * _Notes:_
    *
    *  - `$valueFactory` must follow this interface `callable():TT`
    *  - `$catch` defaults to `Exception`, not `Throwable`. An `Error` is a bug in
    *    your code, and this box discards what it caught, so swallowing one would
    *    erase the bug with nothing left to find it by. Name an `Error` class in
    *    `$catch` to catch one anyway. Use `Result::fromCallable` when you need the
    *    failure itself; it defaults to `Throwable`, because it keeps it
    *  - A subclass of `$catch` is caught too, the same as a `catch` clause
    *  - A factory returning null still gives you `Some(null)`. Chain `notNull()`
    *
    * @template TT
    *
    * @psalm-param callable():TT $valueFactory
    * @psalm-param class-string<Throwable> $catch
    *
    * @psalm-return self<TT>
    */
   #[\NoDiscard(self::UNREAD)]
   public static function fromCallable(callable $valueFactory, string $catch = Exception::class): self {
      try {
         return self::some($valueFactory());
      } catch (Throwable $e) {
         if ($e instanceof $catch && !$e instanceof EarlyReturn) {
            return self::none();
         }

         throw $e;
      }
   }

   /**
    * Runs `$block`, boxing what it returns, or `Option::none()` if a `valueOrReturn()` in it found nothing
    *
    * For steps that each need the one before, without a nested `flatMap` per
    * step: write them in order, unwrap each with `valueOrReturn()`, and the
    * first empty box ends the block.
    *
    * ```php
    * $config = ['db' => ['host' => 'db1', 'port' => 5432]];
    *
    * $dsn = Option::do(function() use ($config) {
    *    $db = Option::fromArray($config, 'db')->valueOrReturn();
    *    $host = Option::fromArray($db, 'host')->valueOrReturn();
    *    $port = Option::fromArray($db, 'port')->valueOrReturn();
    *
    *    return "pgsql:host={$host};port={$port}";
    * });
    * echo (string) $dsn, "\n"; // Some('pgsql:host=db1;port=5432')
    *
    * $user = Option::do(fn() => Option::fromArray($config['db'], 'user')->valueOrReturn());
    * echo (string) $user, "\n"; // None
    * ```
    *
    * _Notes:_
    *
    *  - `$block` must follow this interface `callable():TT`
    *  - `valueOrReturn()` ends the nearest `Option::do()`, even from inside a
    *    function the block calls, the way a throw reaches the nearest catch
    *  - A `catch (Throwable)` or `catch (Error)` in between also catches the
    *    early exit. Let it through by catching `EarlyReturn` first and
    *    rethrowing it. A `catch (Exception)` lets it through already
    *  - Return the plain value. A box returned from the block ends up inside
    *    another box, and a generator is returned without running
    *  - The early exit is an exception, so ending early costs more than an
    *    empty `flatMap()` chain, and more the deeper the call stack, since PHP
    *    records the stack in every exception. `bin/benchmark.php` measures it
    *  - Anything else the block throws propagates. `fromCallable()` inside the
    *    block catches a throw, and never the early exit
    *
    * @template TT
    * @psalm-param callable():TT $block
    * @psalm-return Option<TT>
    */
   #[\NoDiscard(self::UNREAD)]
   public static function do(callable $block): self {
      try {
         return self::some($block());
      } catch (EarlyReturn $exit) {
         if ($exit->box !== 'Option') {
            throw $exit;
         }

         return self::none();
      }
   }

   /**
    * Boxes every value, keyed as given, iff every option is `Option::some`, otherwise `Option::none()`
    *
    * For needing several optional things at once, such as the settings a
    * connection cannot open without.
    *
    * ```php
    * $settings = ['host' => Option::some('db1'), 'port' => Option::some(5432)];
    *
    * echo (string) Option::all($settings)->map(fn(array $s) => "{$s['host']}:{$s['port']}"), "\n"; // Some('db1:5432')
    * echo (string) Option::all([...$settings, 'user' => Option::none()]), "\n";                   // None
    * ```
    *
    * _Notes:_
    *
    *  - It stops at the first `Option::none`, so a generator is read no further
    *  - An empty input gives `Some([])`
    *  - Keys are kept, and a key that repeats keeps its last value, as with
    *    `iterator_to_array()`. A key that is not an int or a string, or an item
    *    that is not an `Option`, throws a `TypeError`
    *  - The type is `Option<array<K, TT>>`, so a list is not typed as a `list`,
    *    and a shape loses its per-key types: neither psalm nor PHPStan can map
    *    a type one key at a time
    *
    * @template K
    * @template TT
    * @psalm-param iterable<K, Option<TT>> $boxes
    * @psalm-return Option<array<K, TT>>
    */
   #[\NoDiscard(self::UNREAD)]
   public static function all(iterable $boxes): self {
      $values = [];

      foreach ($boxes as $key => $option) {
         self::checkItem('Option::all()', $key, $option);

         if (!$option->hasValue) {
            return self::none();
         }

         $values[$key] = $option->value;
      }

      return self::some($values);
   }

   /**
    * Boxes the first value of `$values` that `$predicate` accepts, or `Option::none()`
    *
    * The "did I find anything" shape, without the count check or the loop.
    * Leave out `$predicate` and it takes the first value there is. Works on any
    * iterable, so a generator stops at the first match rather than being
    * drained into an array first.
    *
    * ```php
    * echo (string) Option::first(['a', 'b']), "\n"; // Some('a')
    * echo (string) Option::first([]), "\n";         // None
    *
    * // keys are ignored, it is the first value in iteration order
    * echo (string) Option::first(['x' => 'a']), "\n"; // Some('a')
    *
    * // with a predicate, the first value it accepts
    * $users = [['name' => 'Ada', 'admin' => false], ['name' => 'Grace', 'admin' => true]];
    *
    * echo Option::first($users, fn(array $user) => $user['admin'] === true)
    *    ->map(fn(array $user) => $user['name'])
    *    ->valueOr('nobody'), "\n"; // "Grace"
    * ```
    *
    * _Notes:_
    *
    *  - `$predicate` must follow this interface `callable(TT):bool`. It runs on
    *    each value in turn and stops at the first result `if` would accept
    *  - PHP 8.4's `array_find` does the same search, but returns `null` for no
    *    match, which looks exactly like a match on a stored `null`, and it only
    *    takes arrays
    *  - A match on `null` gives you `Some(null)`, like every other factory
    *    here. Chain `notNull()` to collapse it
    *  - Returns `Option<TT>`
    *
    * @template TT
    *
    * @psalm-param iterable<mixed, TT> $values
    * @psalm-param null|callable(TT):bool $predicate
    *
    * @psalm-return self<TT>
    */
   #[\NoDiscard(self::UNREAD)]
   public static function first(iterable $values, ?callable $predicate = null): self {
      foreach ($values as $value) {
         // a copy, so a predicate that takes its argument by reference cannot change what is boxed
         $candidate = $value;

         if ($predicate === null || $predicate($candidate)) {
            return self::some($value);
         }
      }

      return self::none();
   }

   /**
    * Boxes `$array[$key]` iff the key exists, even when it holds `null`, otherwise `Option::none()`
    *
    * ```php
    * $row = ['name' => 'Ada', 'email' => null];
    *
    * echo (string) Option::fromArray($row, 'name'), "\n";  // Some('Ada')
    * echo (string) Option::fromArray($row, 'email'), "\n"; // Some(null), the key exists
    * echo (string) Option::fromArray($row, 'phone'), "\n"; // None
    * ```
    *
    * _Notes:_
    *
    *  - Returns `Option<value-of<TArray>>`. psalm does not resolve a
    *    `$key is key-of<TArray>` conditional here, taking the true branch even
    *    for a key it can see is absent, so there is no point writing one. This
    *    type is still sound, because the miss returns `Option<never>`
    *
    * @psalm-pure
    * @template TArray of array
    * @param TArray $array
    * @psalm-return self<value-of<TArray>>
    */
   #[\NoDiscard(self::UNREAD)]
   public static function fromArray(array $array, int|string $key): self {
      if (array_key_exists($key, $array)) {
         /** @psalm-var value-of<TArray> $value psalm does not narrow $array[$key] on its own */
         $value = $array[$key];

         return self::some($value);
      }

      return self::none();
   }

   /**
    * Shows the option for debugging, as `Some(value)` or `None`
    *
    * A value with no string form is shown by its type, and an exception by its
    * class and message, so this never warns and never throws.
    *
    * ```php
    * echo (string) Option::some(5), "\n";      // Some(5)
    * echo (string) Option::some([1, 2]), "\n"; // Some(array)
    * echo (string) Option::none(), "\n";       // None
    * ```
    */
   public function __toString(): string {
      return $this->hasValue ? 'Some(' . self::describe($this->value) . ')' : 'None';
   }
}
