<?php

declare(strict_types = 1);

namespace Optional;

use Exception;
use Optional\Exceptions\MissingValueException;
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
 * @api
 * @template T
 */
final readonly class Option {
   private bool $hasValue;

   /** @psalm-var T */
   private mixed $value;

   /**
    * @psalm-param T $value
    * @psalm-mutation-free
    */
   private function __construct($value, bool $hasValue) {
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
    * Returns the options value or returns `$alternative`
    *
    * ```php
    * $someThing = Option::some(1);
    * $someClass = Option::some(new SomeObject());
    *
    * $none = Option::none();
    *
    * $myVar = $someThing->valueOr("Some other value!"); // 1
    * $myVar = $someClass->valueOr("Some other value!"); // instance of SomeObject
    *
    * $myVar = $none->valueOr("Some other value!"); // "Some other value!"
    *
    * $none = Option::some(null)->valueOr("Some other value!"); // null, See option->notNull()
    * ```
    *
    * @psalm-mutation-free
    * @template TT
    * @psalm-param TT $alternative
    * @psalm-return (T is never ? TT : T)
    **/
   public function valueOr($alternative) {
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
    * @psalm-return (T is never ? null : T|null)
    **/
   public function toNullable() {
      return $this->hasValue
         ? $this->value
         : null;
   }

   /**
    * Returns the options value, or throws
    *
    * With no argument it throws `MissingValueException("Value is missing.")`.
    * Pass `$exceptionFactory` to say what was missing instead, which is almost
    * always worth it.
    *
    * ```php
    * echo Option::some(1)->valueOrThrow(), "\n"; // 1
    *
    * try {
    *    Option::none()->valueOrThrow(fn() => new \RuntimeException("No id for this device"));
    * } catch (\RuntimeException $e) {
    *    echo $e->getMessage(), "\n"; // "No id for this device"
    * }
    *
    * try {
    *    Option::none()->valueOrThrow();
    * } catch (\Optional\Exceptions\MissingValueException $e) {
    *    echo $e->getMessage(), "\n"; // "Value is missing."
    * }
    * ```
    *
    * _Notes:_
    *
    *  - `$exceptionFactory` must follow this interface `callable():Throwable`
    *  - It is called lazily, so building the exception costs nothing on a `Option::some`
    *  - There is no `value()`. A name that short hides the throw, and an
    *    unguarded call to it is the one mistake psalm cannot catch for you
    *
    * @psalm-param null|callable():Throwable $exceptionFactory
    * @psalm-return (T is never ? never : T)
    **/
   public function valueOrThrow(?callable $exceptionFactory = null) {
      if (!$this->hasValue) {
         throw $exceptionFactory === null
            ? new MissingValueException("Value is missing.")
            : $exceptionFactory();
      }

      return $this->value;
   }

   /**
    * Runs `$sideEffect` on the value iff the option is `Option::some`, then returns the original option
    *
    * `matchSome` returns void, so a side effect ends a chain. This keeps it going.
    *
    * ```php
    * $logged = Option::some(5)
    *    ->tap(fn($x) => error_log("about to double {$x}"))
    *    ->map(fn($x) => $x * 2); // Some(10), and the log line ran
    *
    * Option::none()->tap(fn($x) => error_log("never runs")); // None
    * ```
    *
    * _Notes:_
    *
    *  - `$sideEffect` must follow this interface `callable(T):void`
    *  - Returns `Option<T>`, always the original one
    *
    * @psalm-param callable(T):void $sideEffect
    * @psalm-return self<T>
    **/
   public function tap(callable $sideEffect): self {
      if ($this->hasValue) {
         $sideEffect($this->value);
      }

      return $this;
   }

   /**
    * Runs `$sideEffect` iff the option is `Option::none`, then returns the original option
    *
    * The counterpart to `tap`. `matchNone` returns void, so noting an empty
    * result ends a chain. This keeps it going.
    *
    * ```php
    * $recovered = Option::none()
    *    ->tapNone(fn() => error_log("cache miss"))
    *    ->orCreate(fn() => 'from source'); // Some('from source'), and the log line ran
    *
    * Option::some(1)->tapNone(fn() => error_log("never runs")); // Some(1)
    * ```
    *
    * _Notes:_
    *
    *  - `$sideEffect` takes no argument, because a `Option::none` holds nothing
    *  - Returns `Option<T>`, always the original one
    *
    * @psalm-param callable():void $sideEffect
    * @psalm-return self<T>
    **/
   public function tapNone(callable $sideEffect): self {
      if (!$this->hasValue) {
         $sideEffect();
      }

      return $this;
   }

   /**
    * Returns the options value or calls `$alternativeFactory` and returns the value of that function
    *
    * ```php
    * $someThing = Option::some(1);
    * $someClass = Option::some(new SomeObject());
    *
    * $none = Option::none();
    *
    * $myVar = $someThing->valueOrCreate(fn() => new NewObject()); // 1
    * $myVar = $someClass->valueOrCreate(fn() => new NewObject()); // instance of SomeObject
    *
    * $myVar = $none->valueOrCreate(fn() => new NewObject()); // instance of NewObject
    * ```
    * _Notes:_
    *
    *  - `$alternativeFactory` must follow this interface `callable():TT`
    *
    * @template TT
    * @psalm-param callable():TT $alternativeFactory
    * @psalm-return (T is never ? TT : T)
    **/
   public function valueOrCreate(callable $alternativeFactory) {
      return $this->hasValue
         ? $this->value
         : $alternativeFactory();
   }

   /**
    * Returns `Option::some($alternative)` iff the option is `Option::none`, otherwise this one
    *
    * ```php
    * $none = Option::none();
    * $myVar = $none->or(10); // A some instance, with value 10
    * ```
    *
    * _Notes:_
    *
    *  - Returns `Option<T|TT>`
    *
    * @psalm-mutation-free
    *
    * @template TT
    * @psalm-param TT $alternative
    *
    * @psalm-return self<T|TT>
    */
   public function or($alternative): self {
      return $this->hasValue
         ? $this
         : self::some($alternative);
   }

   /**
    * Returns `Option::some()` of what `$alternativeFactory` returns iff the option is `Option::none`, otherwise this one
    *
    * `$alternativeFactory` runs only iff the option is `Option::none`, so an expensive default costs nothing when there is a value
    *
    * ```php
    * $none = Option::none();
    * $myVar = $none->orCreate(fn() => 10); // A some instance, with value 10, but lazy
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
   public function orCreate(callable $alternativeFactory): self {
      return $this->hasValue
         ? $this
         : self::some($alternativeFactory());
   }

   /**
    * Returns `$alternativeOption` iff this option is `Option::none`, otherwise this one
    *
    * ```php
    * $none = Option::none();
    * $myVar = $none->else(Option::some(10)); // A some instance, with value 10
    * $myVar = $none->else(Option::none()); // A new none instance
    * ```
    *
    * _Notes:_
    *
    *  - Returns `Option<T|TT>`
    *
    * @psalm-mutation-free
    * @template TT
    * @psalm-param Option<TT> $alternativeOption
    * @psalm-return Option<T|TT>
    **/
   public function else(self $alternativeOption): self {
      return $this->hasValue
         ? $this
         : $alternativeOption;
   }

   /**
    * Returns the option `$alternativeOptionFactory` builds iff this one is `Option::none`, otherwise this one
    *
    * `$alternativeOptionFactory` is run lazily
    *
    * ```php
    * $none = Option::none();
    *
    * $myVar = $none->elseCreate(fn() => Option::some(10)); // A some instance, with value 10, but lazy
    * ```
    *
    * _Notes:_
    *
    *  - `$alternativeOptionFactory` must follow this interface `callable():Option<TT>`
    *  - Returns `Option<T|TT>`
    *
    * @template TT
    * @psalm-param callable():Option<TT> $alternativeOptionFactory
    * @psalm-return Option<T|TT>
    **/
   public function elseCreate(callable $alternativeOptionFactory): self {
      return $this->hasValue
         ? $this
         : $alternativeOptionFactory();
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
   public function match(callable $some, callable $none) {
      return $this->hasValue
         ? $some($this->value)
         : $none();
   }

   /**
    * Runs `$some` with the value iff the option is `Option::some`, for a side effect
    *
    * It returns `void`, so it ends a chain. `tap()` is the same thing mid-chain.
    *
    * ```php
    * Option::some('Ada')->matchSome(function(string $name) { echo "hello {$name}\n"; }); // "hello Ada"
    * Option::none()->matchSome(function(string $name) { echo "hello {$name}\n"; });     // prints nothing
    * ```
    *
    * _Notes:_
    *
    *  - `$some` must follow this interface `callable(T)`
    * @psalm-param callable(T) $some
    **/
   public function matchSome(callable $some): void {
      if (!$this->hasValue) {
         return;
      }

      $some($this->value);
   }

   /**
    * Runs `$none` iff the option is `Option::none`, for a side effect
    *
    * It returns `void`, so it ends a chain. `tapNone()` is the same thing
    * mid-chain.
    *
    * ```php
    * Option::none()->matchNone(function() { echo "nobody there\n"; });     // "nobody there"
    * Option::some('Ada')->matchNone(function() { echo "nobody there\n"; }); // prints nothing
    * ```
    *
    * _Notes:_
    *
    *  - `$none` must follow this interface `callable()`
    *
    * @psalm-param callable() $none
    **/
   public function matchNone(callable $none): void {
      if ($this->hasValue) {
         return;
      }

      $none();
   }

   /**
    * Maps the `$value` of a `Option::some($value)`
    *
    * `$mapFunc` runs only iff the option is `Option::some`. A `Option::none` passes through.
    *
    * ```php
    * $none = Option::none();
    * $stillNone = $none->map(fn($x) => $x * $x);
    *
    * $some = Option::some(5);
    * $someSquared = $some->map(fn($x) => $x * $x);
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
   public function map(callable $mapFunc): self {
      return $this->hasValue
         ? self::some($mapFunc($this->value))
         : self::none();
   }

   /**
    * `map`, but a throw from `$mapFunc` gives you `Option::none()` instead of escaping
    *
    * The map function runs iff the option is a `Option::some`. Otherwise the
    * `Option::none` is propagated.
    *
    * ```php
    * $some = Option::some(['key' => 'value']);
    * $none = $some->mapSafely(fn(array $array) => throw new \Exception("boom")); // None
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
    *    `$catch` to catch one anyway. `Either::mapLeftSafely` defaults to
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
   public function mapSafely(callable $mapFunc, string $catch = Exception::class): self {
      try {
         return $this->map($mapFunc);
      } catch (Throwable $e) {
         if ($e instanceof $catch) {
            return self::none();
         }

         throw $e;
      }
   }

   /**
    * Allows a function to map over the internal value, the function returns an option
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
    * echo (string) $name, "\n"; // Some(FirstLast)
    * ```
    *
    * _Notes:_
    *
    *  - `$mapFunc` must follow this interface `callable(T):Option<U>`
    *  - When your callback returns a `Option::some` on one branch and a
    *    `Option::none` on another, psalm infers a union of two Option types
    *    and cannot bind it to `U`. Give the callback an explicit
    *    `@psalm-return Option<YourType>`, or use a named function with a
    *    declared return type, and it binds
    *
    * @template U
    * @psalm-param callable(T):Option<U> $mapFunc
    * @psalm-return Option<U>
    **/
   public function flatMap(callable $mapFunc): self {
      return $this->hasValue
         ? $mapFunc($this->value)
         : self::none();
   }

   /**
    * The same as `flatMap`, under the name most people guess first
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
    * $name = $person->andThen(fn(array $person): Option => Option::someNotFalsy($person['first'] . $person['last']));
    *
    * echo (string) $name, "\n"; // Some(FirstLast)
    * ```
    *
    * _Notes:_
    *
    *  - `$mapFunc` must follow this interface `callable(T):Option<U>`
    *  - When your callback returns a `Option::some` on one branch and a
    *    `Option::none` on another, psalm infers a union of two Option types
    *    and cannot bind it to `U`. Give the callback an explicit
    *    `@psalm-return Option<YourType>`, or use a named function with a
    *    declared return type, and it binds
    *
    * @template U
    * @psalm-param callable(T):Option<U> $mapFunc
    * @psalm-return Option<U>
    **/
   public function andThen(callable $mapFunc): self {
      return $this->flatMap($mapFunc);
   }

   /**
    * Turns `Option::some($value)` into `Option::none()` iff `$condition` is false,
    * or a callable that returns false for the value. A `Option::none` stays one.
    *
    * ```php
    * $some = Option::some(10);
    * $stillSome = $some->filter(true);
    * $none = $some->filter(false);
    *
    * $stillNone = Option::none()->filter(true);
    * ```
    *
    * _Notes:_
    *
    *  - Pass a `bool` and PHP evaluates it at the call site, before this method
    *    runs, so an expensive or throwing expression happens even on a
    *    `Option::none`. Pass the callable instead and it only runs on a
    *    `Option::some`. That is what the deprecated `filterIf()` was for
    *  - This method is not `@psalm-mutation-free`, because it may call code you
    *    supplied. That is the cost of taking a callable at all
    *  - Returns `Option<T>`
    *
    * ```php
    * $calls = 0;
    * $check = function() use (&$calls) { $calls++; return true; };
    *
    * Option::none()->filter($check()); // $check ran: PHP evaluated the argument
    * Option::none()->filter($check);   // $check did not run
    *
    * echo $calls, "\n"; // 1
    * ```
    *
    * @psalm-param bool|callable(T):bool $condition
    * @psalm-return self<T>
    */
   public function filter(bool|callable $condition): self {
      if (!$this->hasValue) {
         return $this;
      }

      $keep = is_callable($condition) ? $condition($this->value) : $condition;

      return $keep === true ? $this : self::none();
   }

   /**
    * Deprecated since 4.0: pass the callable to `filter()` instead
    *
    * `filter()` takes a callable as well as a bool since 4.0, which made this a
    * second spelling of the same call. It still works as it always did.
    *
    * _Notes:_
    *
    *  - `$filterFunc` must follow this interface `callable(T):bool`
    *  - Returns `Option<T>`
    *
    * @deprecated Pass the callable to filter() instead
    * @psalm-param callable(T):bool $filterFunc
    *
    * @psalm-return self<T>
    */
   #[\Deprecated('pass the callable to filter() instead', since: '4.0')]
   public function filterIf(callable $filterFunc): self {
      return $this->hasValue && !$filterFunc($this->value)
         ? self::none()
         : $this;
   }

   /**
    * Turn a `Option::some(null)` into an `Option::none()` iff `is_null($value)`
    *
    * ```php
    * $someThing = Option::some(null); // Valid
    * $noneThing = $someThing->notNull(); // None: a stored null becomes an empty box
    * ```
    * @psalm-mutation-free
    * @template TNotNull
    * @psalm-if-this-is Option<TNotNull|null>
    * @psalm-return self<TNotNull>
    */
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
    * echo (string) Option::some('0.0')->notFalsy(), "\n"; // Some(0.0), truthy in PHP
    * ```
    *
    * _Notes:_
    *
    *  - psalm drops `null` and `false` from the type, so an `Option<array|false>`
    *    comes out as `Option<array>`. The other falsy values (`0`, `''`, `'0'`,
    *    `[]`) are removed at runtime but stay in the type
    *  - psalm reports `IfThisIsMismatch` for a box whose type is exactly `null`,
    *    because there is nothing left to bind. Use `notNull()` there
    *
    * @psalm-mutation-free
    * @template TNotFalsy
    * @psalm-if-this-is Option<TNotFalsy|null|false>
    * @psalm-return self<TNotFalsy>
    */
   public function notFalsy(): self {
      /** @var self<TNotFalsy> a falsy value never survives the check */
      return $this->hasValue && !((bool) $this->value)
         ? self::none()
         : $this;
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
    */
   public function notFalse(): self {
      /** @var self<TNotFalse> a false value never survives the check */
      return $this->hasValue && $this->value === false
         ? self::none()
         : $this;
   }

   /**
    * Returns true if the option's value == `$value`, otherwise false.
    *
    * ```php
    * $none = Option::none();
    * $false = $none->contains(1);
    *
    * $some = Option::some(10);
    * $true = $some->contains(10);
    * $false = $some->contains("Thing");
    * ```
    *
    * @psalm-mutation-free
    * @psalm-param mixed $value
    **/
   public function contains($value): bool {
      if (!$this->hasValue()) {
         return false;
      }

      return $this->value == $value;
   }

   /**
    * Returns true if the `$existsFunc` returns true, otherwise false.
    *
    * ```php
    * $none = Option::none();
    * $false = $none->exists(fn($x) => $x == 10);
    *
    * $some = Option::some(10);
    * $true = $some->exists(fn($x) => $x >= 10);
    * $false = $some->exists(fn($x) => $x == "Thing");
    * ```
    *
    * _Notes:_
    *
    *  - `$existsFunc` must follow this interface `callable(T):bool`
    *
    * @psalm-param callable(T):bool $existsFunc
    **/
   public function exists(callable $existsFunc): bool {
      if (!$this->hasValue()) {
         return false;
      }

      return $existsFunc($this->value);
   }

   //////////////////////////////
   // STATIC FACTORY FUNCTIONS //
   //////////////////////////////

   /**
    * Creates an option holding `$someValue`
    *
    * ```php
    * $someThing = Option::some(1);
    * $someClass = Option::some(new SomeObject());
    *
    * $someNullThing = Option::some(null); // Valid
    * ```
    * _Notes:_
    *
    * - Returns `Option<TT>`
    *
    *
    * @psalm-pure
    * @template TT
    * @psalm-param TT $someValue
    * @psalm-return Option<TT>
    **/
   public static function some($someValue): self {
      return new self($someValue, true);
   }

   /**
    * Creates an empty option
    *
    * ```php
    * $none = Option::none();
    * ```
    *
    * _Notes:_
    *
    * - Returns `Option<never>`
    *
    * @psalm-pure
    * @psalm-return Option<never>
    **/
   public static function none(): self {
      /** @var Option<never> */
      return new self(null, false);
   }

   /**
    * Boxes `$someValue` iff `$filterFunc` returns true for it, otherwise `Option::none()`
    *
    * ```php
    * $positiveThing = Option::someWhen(1, fn($x) => $x > 0);
    * $negativeThing = Option::someWhen(1, fn($x) => $x < 0);
    * ```
    *
    * _Notes:_
    *
    *  - `$filterFunc` must follow this interface `callable(TT):bool`
    *  - Returns `Option<TT>`
    *
    *
    * @template TT
    *
    * @psalm-param TT $someValue
    * @psalm-param callable(TT):bool $filterFunc
    *
    * @psalm-return self<TT>
    */
   public static function someWhen($someValue, callable $filterFunc): self {
      if ($filterFunc($someValue)) {
         return self::some($someValue);
      }
      return self::none();
   }

   /**
    * Returns `Option::none()` iff `$filterFunc` returns true for `$someValue`, otherwise boxes it
    *
    * ```php
    * $positiveThing = Option::noneWhen(1, fn($x) => $x < 0);
    * $negativeThing = Option::noneWhen(1, fn($x) => $x > 0);
    * ```
    *
    * _Notes:_
    *
    *  - `$filterFunc` must follow this interface `callable(TT):bool`
    *  - Returns `Option<TT>`
    *
    * @template TT
    *
    * @psalm-param TT $someValue
    * @psalm-param callable(TT):bool $filterFunc
    *
    * @psalm-return self<TT>
    */
   public static function noneWhen($someValue, callable $filterFunc): self {
      if ($filterFunc($someValue)) {
         return self::none();
      }
      return self::some($someValue);
   }

   /**
    * Boxes `$someValue` unless it is `null`, otherwise `Option::none()`
    *
    * ```php
    * $some = Option::some(null); // Valid, returns Some(null)
    * $none = Option::someNotNull(null); // Valid, returns None()
    * ```
    * _Notes:_
    *
    * - Returns `Option<TT>`
    *
    * @template TT
    *
    * @psalm-param TT|null $someValue
    *
    * @psalm-return self<TT>
    */
   public static function someNotNull($someValue): self {
      /** @var self<TT> */
      return self::some($someValue)->notNull();
   }

   /**
    * Boxes `$someValue` unless it is falsy, otherwise `Option::none()`
    *
    * The falsy counterpart to `Option::someNotNull`. Reach for it when PHP hands
    * you the usual "nothing found" value rather than null: `false` from a db
    * row fetch, `''` from a lookup, `[]` from a filter.
    *
    * ```php
    * $none = Option::someNotFalsy(false); // None, e.g. a row fetch that found nothing
    * $none = Option::someNotFalsy('');    // None
    * $none = Option::someNotFalsy([]);    // None
    * $some = Option::someNotFalsy('0.0'); // Some('0.0'), which is truthy in PHP
    * ```
    *
    * _Notes:_
    *
    * - Returns `Option<TT>`
    * - PHP's falsy set, so `'0'` is a None but `'0.0'` is a Some. See `notFalsy()`
    *
    * @template TT
    *
    * @psalm-param TT|null|false $someValue
    *
    * @psalm-return self<TT>
    */
   public static function someNotFalsy($someValue): self {
      /** @var self<TT> a falsy value never survives notFalsy() */
      return self::some($someValue)->notFalsy();
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
    * @template TT
    *
    * @psalm-param TT|false $someValue
    *
    * @psalm-return self<TT>
    */
   public static function someNotFalse($someValue): self {
      /** @var self<TT> a false value never survives notFalse() */
      return self::some($someValue)->notFalse();
   }

   /**
    * Runs `$valueFactory`, boxing what it returns, or returning `Option::none()` if it throws
    *
    * This is how a chain starts from code that signals failure by throwing. Pass
    * `$catch` to say which throw means "there isn't one", the way a `catch`
    * clause would, and let every other failure propagate.
    *
    * ```php
    * $some = Option::fromCallable(fn() => 7);                            // Some(7)
    * $none = Option::fromCallable(fn() => throw new \Exception("boom")); // None
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
    *    `$catch` to catch one anyway. Use `Either::fromCallable` when you need the
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
   public static function fromCallable(callable $valueFactory, string $catch = Exception::class): self {
      try {
         return self::some($valueFactory());
      } catch (Throwable $e) {
         if ($e instanceof $catch) {
            return self::none();
         }

         throw $e;
      }
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
    * echo (string) Option::first(['a', 'b']), "\n"; // Some(a)
    * echo (string) Option::first([]), "\n";         // None
    *
    * // keys are ignored, it is the first value in iteration order
    * echo (string) Option::first(['x' => 'a']), "\n"; // Some(a)
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
    *    each value in turn and stops at the first `true`
    *  - PHP 8.4's `array_find` does the same search, but returns `null` for no
    *    match, which looks exactly like a match on a stored `null`, and it only
    *    takes arrays
    *  - A match on `null` gives you `Some(null)`, like every other factory
    *    here. Chain `notNull()` to collapse it
    *  - Returns `Option<TT>`
    *
    * @template TT
    *
    * @psalm-param iterable<array-key, TT> $values
    * @psalm-param null|callable(TT):bool $predicate
    *
    * @psalm-return self<TT>
    */
   public static function first(iterable $values, ?callable $predicate = null): self {
      foreach ($values as $value) {
         if ($predicate === null || $predicate($value)) {
            return self::some($value);
         }
      }

      return self::none();
   }

   /**
    * Boxes `$array[$key]` iff the key exists, even when it holds `null`, otherwise `Option::none()`
    *
    * ```php
    * $some = Option::fromArray(['hello' => ' world'], 'hello');
    * $none = Option::fromArray(['hello' => ' world'], 'nope');
    * ```
    * @psalm-pure
    *
    * @template TArray of array
    * @param TArray $array
    * @param array-key $key
    *
    * _Notes:_
    *
    *  - Returns `Option<value-of<TArray>>`. psalm does not resolve a
    *    `$key is key-of<TArray>` conditional here, taking the true branch even
    *    for a key it can see is absent, so there is no point writing one. This
    *    type is still sound, because the miss returns `Option<never>`
    *
    * @psalm-return self<value-of<TArray>>
    */
   public static function fromArray(array $array, $key): self {
      if (array_key_exists($key, $array)) {
         /** @psalm-var value-of<TArray> $value psalm does not narrow $array[$key] on its own */
         $value = $array[$key];

         return self::some($value);
      }

      return self::none();
   }

   /**
    * @psalm-suppress InvalidCast
    *
    * This is due to this class being a box.
    * I can't ensure the boxed value is stringable.
    * https://github.com/vimeo/psalm/issues/1982
    * @psalm-mutation-free
    */
   public function __toString() {
      if ($this->hasValue) {
         if ($this->value === null) {
            return "Some(null)";
         }
         return "Some({$this->value})";
      }
      return "None";
   }
}
