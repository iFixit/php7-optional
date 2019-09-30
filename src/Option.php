<?php

declare(strict_types = 1);

namespace Optional;

/**
 * @template T
 */
class Option {
   /** @var bool */
   private $hasValue;
   /** @var T */
   private $value;

   /** @param T $value */
   private function __construct($value, bool $hasValue) {
      $this->hasValue = $hasValue;
      $this->value = $value;
   }

   /**
    * Returns true iff the option is `Option::some`
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
    * @param T $alternative
    * @return T
    **/
   public function valueOr($alternative) {
      return $this->hasValue ? $this->value : $alternative;
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
    * @param callable():T $alternativeFactory
    * @return T
    **/
   public function valueOrCreate(callable $alternativeFactory) {
      return $this->hasValue ? $this->value : $alternativeFactory();
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
    * @param T $alternative
    * @return Option<T>
    **/
   public function or($alternative): self {
      return $this->hasValue ? $this : self::some($alternative);
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
    * @param callable():T $alternativeFactory
    * @return Option<T>
    **/
   public function orCreate(callable $alternativeFactory): self {
      return $this->hasValue ? $this : self::some($alternativeFactory());
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
    * @param Option<T> $alternativeOption
    * @return Option<T>
    **/
   public function else(self $alternativeOption): self {
      return $this->hasValue ? $this : $alternativeOption;
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
    * @param callable():Option<T> $alternativeOptionFactory
    * @return Option<T>
    **/
   public function elseCreate(callable $alternativeOptionFactory): self {
      return $this->hasValue ? $this : $alternativeOptionFactory();
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
    * @param callable(T):U $some
    * @param callable():U $none
    * @return U
    **/
   public function match(callable $some, callable $none) {
      return $this->hasValue ? $some($this->value) : $none();
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
    *
    * @param $some callable(T)
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
    * @param $none callable(T)
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
    * @param $mapFunc callable(T):U
    * @return Option<U>
    **/
   public function map(callable $mapFunc): self {
      /** @var callable(T):Option<U> **/
      $someFunc =
      /** @param T $value **/
      function($value) use ($mapFunc): self {
         return self::some($mapFunc($value));
      };

      /** @var callable():Option<U> **/
      $noneFunc = function(): self {
         return self::none();
      };

      return $this->match($someFunc, $noneFunc);
   }

   /**
    * ```php
    * $none = Option::none();
    * $noneNotNull = $none->flatMap(function($x) { return Option::some($x)->notNull(); });
    *
    * $some = Option::some(null);
    * $someNotNull = $some->flatMap(function($x) { return Option::some($x)->notNull(); });
    * ```
    *
    * Note: `$mapFunc` must follow this interface `function mapFunc(mixed $value): Option`
    * @template U
    * @param callable(T):Option<U> $mapFunc
    * @return Option<U>
    **/
   public function flatMap(callable $mapFunc): self {
      /** @var callable():Option<U> **/
      $noneFunc = function(): self {
         return self::none();
      };

      return $this->match($mapFunc, $noneFunc);
   }

   public function filter(bool $condition): self {
      return $this->hasValue && !$condition ? self::none() : $this;
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
    * @param callable(T):bool $filterFunc
    * @return Option<T>
    **/
   public function filterIf(callable $filterFunc): self {
      return $this->hasValue && !$filterFunc($this->value) ? self::none() : $this;
   }

   /**
    * Turn a `Option::some(null)` into an `Option::none()` iff `is_null($value)`
    *
    * ```php
    * $someThing = Option::some(null); // Valid
    * $noneThing = $someThing->notNull(); // Turn null into an none Option
    * ```
    *
    * @return Option<T>
    **/
   public function notNull(): self {
      return $this->hasValue && is_null($this->value) ? self::none() : $this;
   }

   /**
    * Turn a `Option::some(null)` into an `Option::none()` iff `!$value == true`
    *
    * ```php
    * $someThing = Option::some(null); // Valid
    * $none = $someThing->notFalsy(); // Turn null into an none Option
    * $none =  Option::some("")->notFalsy(); // Turn empty string into an none Option
    * ```
    *
    * @return Option<T>
    **/
    public function notFalsy(): self {
      return $this->hasValue && !$this->value ? self::none() : $this;
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
    * @param mixed $value
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
    * @param callable(T):bool $existsFunc
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
    * - Returns `Option<T>`
    *
    * @param T $someValue
    * @return Option<T>
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
    * - Returns `Option<T>`
    *
    **/
   public static function none(): self {
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
    *  - `$filterFunc` must follow this interface `callable(T):bool`
    *  - Returns `Option<T>`
    *
    * @param T $someValue
    * @param callable(T):bool $filterFunc
    * @return Option<T>
    **/
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
    *  - `$filterFunc` must follow this interface `callable(T):bool`
    *  - Returns `Option<T>`
    *
    * @param T $someValue
    * @param callable(T):bool $filterFunc
    * @return Option<T>
    **/
   public static function noneWhen($someValue, callable $filterFunc): self {
      if ($filterFunc($someValue)) {
         return self::none();
      }
      return self::some($someValue);
   }
}
