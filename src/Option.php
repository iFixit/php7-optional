<?php

declare(strict_types = 1);

namespace Optional;

use Exception;
use Optional\Exceptions\MissingValueException;

/**
 * @template T
 *
 */
class Option {
   /**
    * @psalm-var bool
    * @psalm-readonly
    */
   private $hasValue;

   /**
    * @psalm-var T
    * @psalm-readonly
    */
   private $value;

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
    * Returns the options value or throws
    *
    * ```php
    * $someThing = Option::some(1);
    * $someClass = Option::some(new SomeObject());
    *
    * $none = Option::none();
    *
    * $myVar = $someThing->value(); // 1
    * $myVar = $someClass->value(); // instance of SomeObject
    *
    * $myVar = $none->valueOr(); // throws Exception("Value is missing.")
    * ```
    *
    * @psalm-mutation-free
    * @psalm-return (T is never ? never : T)
    **/
    public function value() {
      if(!$this->hasValue) {
         throw new MissingValueException("Value is missing.");
      }
      return $this->value;
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
    * $myVar = $someThing->valueOrCreate(function() { return new NewObject(); }); // 1
    * $myVar = $someClass->valueOrCreate(function() { return new NewObject(); }); // instance of SomeObject
    *
    * $myVar = $none->valueOrCreate(function() { return new NewObject(); }); // instance of NewObject
    * ```
    * _Notes:_
    *
    *  - `$alternativeFactory` must follow this interface `callable():T`
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
    * Returns a `Option::some($value)` iff the the option orginally was `Option::none`
    *
    * ```php
    * $none = Option::none();
    * $myVar = $none->or(10); // A some instance, with value 10
    * ```
    *
    * _Notes:_
    *
    *  - Returns `Option<T>`
    *
    * @psalm-mutation-free
    *
    * @template TT
    * @psalm-param TT $alternative
    *
    * @psalm-return (T is never ? self<TT> : self<T>)
    */
   public function or($alternative): self {
      return $this->hasValue
         ? $this
         : self::some($alternative);
   }

   /**
    * Returns a `Option::some($value)` iff the the option orginally was `Option::none`
    *
    * The `$alternativeFactory` is called lazily - iff the option orginally was `Option::none`
    *
    * ```php
    * $none = Option::none();
    * $myVar = $none->orCreate(function() { return 10; }); // A some instance, with value 10, but lazy
    * ```
    *
    * _Notes:_
    *
    *  - `$alternativeFactory` must follow this interface `callable():T`
    *  - Returns `Option<T>`
    *
    * @template TT
    * @psalm-param callable():TT $alternativeFactory
    *
    * @psalm-return (T is never ? self<TT> : self<T>)
    */
   public function orCreate(callable $alternativeFactory): self {
      return $this->hasValue
         ? $this
         : self::some($alternativeFactory());
   }

   /**
    * iff `Option::none` return `$alternativeOption`, otherwise return the orginal `$option`
    *
    * ```php
    * $none = Option::none();
    * $myVar = $none->else(Option::some(10)); // A some instance, with value 10
    * $myVar = $none->else(Option::none()); // A new none instance
    * ```
    *
    * _Notes:_
    *
    *  - Returns `Option<T>`
    *
    * @psalm-mutation-free
    * @template TT
    * @psalm-param Option<TT> $alternativeOption
    * @psalm-return (T is never ? Option<TT> : Option<T>)
    **/
   public function else(self $alternativeOption): self {
      return $this->hasValue
         ? $this
         : $alternativeOption;
   }

   /**
    * iff `Option::none` return the `Option` returned by `$alternativeOptionFactory`, otherwise return the orginal `$option`
    *
    * `$alternativeOptionFactory` is run lazily
    *
    * ```php
    * $none = Option::none();
    *
    * $myVar = $none->elseCreate(function() { return Option::some(10); }); // A some instance, with value 10, but lazy
    * ```
    *
    * _Notes:_
    *
    *  - `$alternativeOptionFactory` must follow this interface `callable():Option<T>`
    *  - Returns `Option<T>`
    *
    * @template TT
    * @psalm-param callable():Option<TT> $alternativeOptionFactory
    * @psalm-return (T is never ? Option<TT> : Option<T>)
    **/
   public function elseCreate(callable $alternativeOptionFactory): self {
      return $this->hasValue
         ? $this
         : $alternativeOptionFactory();
   }

   /**
    * Runs only 1 function:
    *
    * - `$someFunc` iff the option is `Option::some`
    * - `$noneFunc` iff the option is `Option::none`
    *
    * ```php
    * $someThing = Option::some(1);
    *
    * $someThingSquared = $someThing->match(
    *    function($x) { return $x * $x; },    // runs iff $someThing == Option::some
    *    function() { return 0; }             // runs iff $someThing == Option::none
    * );
    *
    *
    * $configOption = Option::some($config)->notNull();
    *
    * $configOption->match(
    *    function($x) { var_dump("Your config: {$x}"); },
    *    function() { var_dump("Config was missing!"); }
    * );
    * ```
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
    * Side effect function: Runs the function iff the option is `Option::some`
    *
    * ```php
    * $configOption = Option::some($config)->notNull();
    *
    * $configOption->matchSome(
    *    function($x) { var_dump("Your config: {$x}"); }
    * );
    * ```
    * _Notes:_
    *
    *  - `$some` must follow this interface `callable(T):U`
    * @psalm-param callable(T) $some
    **/
   public function matchSome(callable $some): void {
      if (!$this->hasValue) {
         return;
      }

      $some($this->value);
   }

   /**
    * Side effect function: Runs the function iff the option is `Option::none`
    *
    * ```php
    * $configOption = Option::some($config)->notNull();
    *
    * $configOption->matchNone(
    *    function() { var_dump("Config was missing!"); }
    * );
    * ```
    *
    * _Notes:_
    *
    *  - `$none` must follow this interface `callable():U`
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
    * The map function runs iff the options is a `Option::some`
    * Otherwise the `Option:none` is propagated
    *
    * ```php
    * $none = Option::none();
    * $stillNone = $none->map(function($x) { return $x * $x; });
    *
    * $some = Option::some(5);
    * $someSquared = $some->map(function($x) { return $x * $x; });
    * ```
    *
    * _Notes:_
    *
    *  - `$mapFunc` must follow this interface `callable(T):U`
    *  - Returns `Option<U>`
    *
    * @template U
    * @psalm-param $mapFunc callable(T):U
    * @psalm-return Option<U>
    **/
   public function map(callable $mapFunc): self {
      /** @psalm-var callable(T):Option<U> **/
      $someFunc =
      /** @psalm-param T $value **/
      function($value) use ($mapFunc): self {
         return self::some($mapFunc($value));
      };

      /** @psalm-var callable():Option<U> **/
      $noneFunc = function(): self {
         return self::none();
      };

      return $this->match($someFunc, $noneFunc);
   }

   /**
    * `map`, but if an exception occurs, return `Option::none`
    *
    * Maps the `$value` of a `Option::some($value)`
    *
    * The map function runs iff the options is a `Option::some`
    * Otherwise the `Option:none` is propagated
    *
    * ```php
    * $some = Option::some(['key' => 'value']);
    * $none = $some->safeMap(function($array) { $thing = $array['Missing Key will cause error']; return 5; });
    * ```
    *
    * _Notes:_
    *
    *  - `$mapFunc` must follow this interface `callable(T):U`
    *  - Returns `Option<U>`
    *
    * @template U
    *
    * @psalm-param $mapFunc callable(T):U
    *
    * @psalm-return self<T>
    */
   public function mapSafely(callable $mapFunc): self {
      try {
         return $this->map($mapFunc);
      } catch (Exception $_e) {
         return Option::none();
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
    *   $name = $person->flatMap(function($person) {
    *      $fullName = $person['first'] . $person['last'];
    *      try {
    *         $thing = SomeComplexThing::doWork($fullName, "Forcing some exception");
    *      } catch (\Exception $e) {
    *         return Option::none();
    *      }
    *      return Option::some($thing);
    *  });
    * ```
    *
    * Note: `$mapFunc` must follow this interface `function mapFunc(mixed $value): Option`
    * @template U
    * @psalm-param callable(T):Option<U> $mapFunc
    * @psalm-return Option<U>
    **/
   public function flatMap(callable $mapFunc): self {
      /** @psalm-var callable():Option<U> **/
      $noneFunc = function(): self {
         return self::none();
      };

      return $this->match($mapFunc, $noneFunc);
   }

   /**
    * A passthrough for FlatMap.
    * A nicer name through
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
    *   $name = $person->andThen(function($person) {
    *      $fullName = $person['first'] . $person['last'];
    *      try {
    *         $thing = SomeComplexThing::doWork($fullName);
    *      } catch (\Exception $e) {
    *         return Option::none();
    *      }
    *      return Option::some($thing);
    *  });
    * ```
    *
    * Note: `$mapFunc` must follow this interface `function mapFunc(mixed $value): Option`
    *
    * @template U
    * @psalm-param callable(T):Option<U> $mapFunc
    * @psalm-return Option<U>
    **/
    public function andThen(callable $mapFunc): self {
      return $this->flatMap($mapFunc);
    }

   public function filter(bool $condition): self {
      return $this->hasValue && !$condition
         ? self::none()
         : $this;
   }

   /**
    * Change the `Option::some($value)` into `Option::none()` iff `$filterFunc` returns false,
    * otherwise propigate the `Option::none()`
    *
    * ```php
    * $none = Option::none();
    * $stillNone = $none->filterIf(function($x) { return $x > 10; });
    *
    * $some = Option::some(10);
    * $stillSome = $some->filterIf(function($x) { return $x == 10; });
    * $none = $some->filterIf(function($x) { return $x != 10; });
    * ```
    *
    * _Notes:_
    *
    *  - `$filterFunc` must follow this interface `callable(T):bool`
    *  - Returns `Option<T>`
    *
    * @psalm-param callable(T):bool $filterFunc
    *
    * @psalm-return self<T>
    */
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
    * $noneThing = $someThing->notNull(); // Turn null into an none Option
    * ```
    * @psalm-mutation-free
    * @psalm-return (T is null ? self<never> : self<T>)
    */
   public function notNull(): self {
      return $this->hasValue && is_null($this->value)
         ? self::none()
         : $this;
   }

   /**
     * Turn a `Option::some(null)` into an `Option::none()` iff `!$value == true`
     *
     * ```php
     * $someThing = Option::some(null); // Valid
     * $none = $someThing->notFalsy(); // Turn null into an none Option
     * $none =  Option::some("")->notFalsy(); // Turn empty string into an none Option
     * ```
     * @psalm-mutation-free
     * @psalm-return (
     *     T is null                ? self<never> : (
     *     T is false               ? self<never> : (
     *     T is array<never, never> ? self<never> : (
     *     T is 0                   ? self<never> : (
     *     T is 0.0                 ? self<never> : (
     *     T is ''                  ? self<never> : (
     *     T is '0'                 ? self<never> :
     *     self<T>
     * )))))))
     */
    public function notFalsy(): self {
      return $this->hasValue && !$this->value
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
    * $false = $none->exists(function($x) { return $x == 10; });
    *
    * $some = Option::some(10);
    * $true = $some->exists(function($x) { return $x >= 10; });
    * $false = $some->exists(function($x) { return $x == "Thing"; });
    * ```
    *
    * _Notes:_
    *
    *  - `$existsFunc` must follow this interface `callable(T):bool`
    *  - Returns `Option<T>`
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
    * Creates a option with a boxed value
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
    * @psalm-mutation-free
    * @psalm-pure
    * @template TT
    * @psalm-param TT $someValue
    * @psalm-return Option<TT>
    **/
   public static function some($someValue): self {
      return new self($someValue, true);
   }

   /**
    * Creates a option which represents an empty box
    *
    * ```php
    * $none = Option::none();
    * ```
    *
    * _Notes:_
    *
    * - Returns `Option<never>`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @psalm-return Option<never>
    **/
   public static function none(): self {
      /** @var Option<never> */
      return new self(null, false);
   }

   /**
    * Take a value, turn it a `Option::some($thing)` iff the `$filterFunc` returns true
    *
    * ```php
    * $positiveThing = Option::someWhen(1, function($x) { return $x > 0; });
    * $negativeThing = Option::someWhen(1, function($x) { return $x < 0; });
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
    * Take a value, turn it a `Option::none()` iff the `$filterFunc` returns true
    *
    * ```php
    * $positiveThing = Option::noneWhen(1, function($x) { return $x < 0; });
    * $negativeThing = Option::noneWhen(1, function($x) { return $x > 0; });
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
    * Take a value, turn it a `Option::some($value)` iff `!is_null($value)`, otherwise returns `Option::none()`
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
    * @psalm-param TT $someValue
    *
    * @psalm-return self<TT>
    */
   public static function someNotNull($someValue): self {
      return self::some($someValue)->notNull();
   }

   /**
    * Creates a option if the `$key` exists in `$array`
    *
    * ```php
    * $some = Option::fromArray(['hello' => ' world'], 'hello');
    * $none = Option::fromArray(['hello' => ' world'], 'nope');
    * ```
    * _Notes:_
    *
    * - Returns `Option<TT>`
    *
    * @psalm-pure
    *
    * @psalm-mutation-free
    *
    * @template TArray of array
    * @param TArray $array
    * @param array-key $key
    *
    * @psalm-return ($key is key-of<TArray> ? self<value-of<TArray>>|self<mixed> : self<never>)
    */
   public static function fromArray(array $array, $key): self {
      if (array_key_exists($key, $array)) {
         return self::some($array[$key]);
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
