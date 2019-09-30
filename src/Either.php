<?php

declare(strict_types = 1);

namespace Optional;

/**
 * @template TSome
 * @template TNone
 */
class Either {
   /** @var bool */
   private $hasValue;
   /** @var TSome */
   private $someValue;
   /** @var TNone */
   private $noneValue;

   /**
    * @param TSome $someValue
    * @param TNone $noneValue
    **/
   private function __construct($someValue, $noneValue, bool $hasValue) {
      $this->hasValue = $hasValue;
      $this->someValue = $someValue;
      $this->noneValue = $noneValue;
   }

   /**
    * Returns true iff the either is `Either::some`
    **/
   public function hasValue(): bool {
      return $this->hasValue;
   }

   /**
    * Returns the either value or returns `$alternative`
    *
    * ```php
    * $someThing = Either::some(1);
    * $someClass = Either::some(new SomeObject());
    *
    * $none = Either::none("Error Code 123");
    *
    * $myVar = $someThing->valueOr("Some other value!"); // 1
    * $myVar = $someClass->valueOr("Some other value!"); // instance of SomeObject
    *
    * $myVar = $none->valueOr("Some other value!"); // "Some other value!"
    *
    * $none = Either::some(null)->valueOr("Some other value!"); // null, See either->notNull()
    * ```
    *
    * @param TSome $alternative
    * @return TSome
    **/
   public function valueOr($alternative) {
      return $this->hasValue ? $this->someValue : $alternative;
   }

   /**
    * Returns the either's value or calls `$valueFactoryFunc` and returns the value of that function
    *
    * ```php
    * $someThing = Either::some(1);
    * $someClass = Either::some(new SomeObject());
    *
    * $none = Either::none("Error Code 123");
    *
    * $myVar = $someThing->valueOrCreate(function($noneValue) { return new NewObject(); }); // 1
    * $myVar = $someClass->valueOrCreate(function($noneValue) { return new NewObject(); }); // instance of SomeObject
    *
    * $myVar = $none->valueOrCreate(function($noneValue) { return new NewObject(); }); // instance of NewObject
    * ```
    *
    * _Notes:_
    *
    *  - `$valueFactoryFunc` must follow this interface `callable(TNone):TSome`
    *
    * @param callable(TNone):TSome $alternativeFactory
    * @return TSome
    **/
   public function valueOrCreate(callable $alternativeFactory) {
      return $this->hasValue ? $this->someValue : $alternativeFactory($this->noneValue);
   }

   /**
    * Returns a `Either::some($value)` iff the either orginally was `Either::none($noneValue)`
    *
    * ```php
    * $none = Either::none();
    * $myVar = $none->or(10); // A some instance, with value 10
    * ```
    *
    * _Notes:_
    *
    *  - Returns `Either<TSome, TNone>`
    *
    * @param TSome $alternative
    * @return Either<TSome, TNone>
    **/
   public function or($alternative): self {
      return $this->hasValue ? $this : self::some($alternative);
   }

   /**
    * Returns a `Either::some($value)` iff the the either orginally was `Either::none($noneValue)`
    *
    * The `$valueFactoryFunc` is called lazily - iff the either orginally was `Either::none($noneValue)`
    *
    * ```php
    * $none = Either::none();
    * $myVar = $none->orCreate(function($noneValue) { return 10; }); // A some instance, with value 10, but lazy
    * ```
    *
    * _Notes:_
    *
    *  - `$valueFactoryFunc` must follow this interface `callable(TNone):TSome`
    *  - Returns `Either<TSome, TNone>`
    *
    * @param callable(TNone):TSome $alternativeFactory
    * @return Either<TSome, TNone>
    **/
   public function orCreate(callable $alternativeFactory): self {
      return $this->hasValue ? $this : self::some($alternativeFactory($this->noneValue));
   }

   /**
    * iff `Either::none($noneValue)` return `$otherEither`, otherwise return the orginal `$either`
    *
    * ```php
    * $none = Either::none("Some Error Message");
    * $myVar = $none->else(Either::some(10)); // A some instance, with value 10
    * $myVar = $none->else(Either::none("Different Error Message")); // A new none instance
    * ```
    *
    * _Notes:_
    *
    *  - `$alternativeEither` must be of type `Either<TSome, TNone>`
    *  - Returns `Either<TSome, TNone>`
    *
    * @param Either<TSome, TNone> $alternativeEither
    * @return Either<TSome, TNone>
    **/
   public function else(self $alternativeEither): self {
      return $this->hasValue ? $this : $alternativeEither;
   }

   /**
    * iff `Either::none` return the `Either` returned by `$otherEitherFactoryFunc`, otherwise return the orginal `$either`
    *
    * `$otherEitherFactoryFunc` is run lazily
    *
    * ```php
    * $none = Either::none();
    *
    * $myVar = $none->elseCreate(function($noneValue) { return Either::some(10); }); // A some instance, with value 10, but lazy
    * ```
    *
    * _Notes:_
    *
    *  - `$alternativeEither` must be of type `callable(TNone):Either<TSome, TNone> `
    *  - Returns `Either<TSome, TNone>`
    *
    * @param callable(TNone):Either<TSome, TNone> $alternativeEitherFactory
    * @return Either<TSome, TNone>
    **/
   public function elseCreate(callable $alternativeEitherFactory): self {
      return $this->hasValue ? $this : $alternativeEitherFactory($this->noneValue);
   }

   /**
    * Runs only 1 function:
    *
    *  - `$some` iff the either is `Either::some`
    *  - `$none` iff the either is `Either::none`
    *
    * ```php
    * $someThing = Either::some(1);
    *
    * $someThingSquared = $someThing->match(
    *    function($x) { return $x * $x; },               // runs iff $someThing == Either::some
    *    function($noneValue) { return $noneValue; }     // runs iff $someThing == Either::none
    * );
    *
    *
    * $configEither = Either::some($config)->notNull("Config was missing!");
    *
    * $configEither->match(
    *    function($x) { var_dump("Your config: {$x}"); },
    *    function($errorMessage) { var_dump($errorMessage); }
    * );
    * ```
    *
    * _Notes:_
    *
    *  - `$some` must follow this interface `callable(TSome):U`
    *  - `$none` must follow this interface `callable(TNone):U`
    *
    * @template U
    * @param callable(TSome):U $some
    * @param callable(TNone):U $none
    * @return U
    **/
   public function match(callable $some, callable $none) {
      return $this->hasValue ? $some($this->someValue) : $none($this->noneValue);
   }

   /**
    * Side effect function: Runs the function iff the either is `Either::some`
    *
    * ```php
    * $configEither = Either::some($config)->notNull("Config was missing!");
    *
    * $configEither->matchSome(
    *    function($x) { var_dump("Your config: {$x}"); }
    * );
    * ```
    *
    * _Notes:_
    *
    *  - `$some` must follow this interface `callable(TSome):U`
    *
    * @param callable(TSome) $some
    **/
   public function matchSome(callable $some): void {
      if (!$this->hasValue) {
         return;
      }

      $some($this->someValue);
   }

   /**
    * Side effect function: Runs the function iff the either is `Either::none`
    *
    * ```php
    * $configEither = Either::some($config)->notNull("Config was missing!");
    *
    * $configEither->matchNone(
    *    function($errorMessage) { var_dump($errorMessage); }
    * );
    * ```
    *
    * _Notes:_
    *
    *  - `$none` must follow this interface `callable(TNone):U`
    *
    * @param callable(TNone) $none
    **/
   public function matchNone(callable $none): void {
      if ($this->hasValue) {
         return;
      }

      $none($this->noneValue);
   }

   /**
    * Maps the `$value` of a `Either::some($value)`
    *
    * The map function runs iff the either's is a `Either::some`
    * Otherwise the `Either:none($noneValue)` is propagated
    *
    * ```php
    * $none = Either::none("Some Error Message");
    * $stillNone = $none->map(function($x) { return $x * $x; });
    *
    * $some = Either::some(5);
    * $someSquared = $some->map(function($x) { return $x * $x; });
    * ```
    *
    * _Notes:_
    *
    *  - `$mapFunc` must follow this interface `callable(TSome):Either<USome, TNone>`
    *  - Returns `Either<TSome, TNone>`
    *
    * @template USome
    * @param callable(TSome):Either<USome, TNone> $mapFunc
    * @return Either<USome, TNone>
    **/
   public function map(callable $mapFunc): self {
      /** @var callable(TSome):Either<USome, TNone> **/
      $someFunc =
      /** @param TSome $value */
      function($value) use ($mapFunc): Either {
         return self::some($mapFunc($value));
      };

      /** @var callable(TNone):Either<USome, TNone> **/
      $noneFunc =
      /** @param TNone $noneValue */
      function($noneValue): Either {
         return self::none($noneValue);
      };

      return $this->match($someFunc, $noneFunc);
   }

   /**
    * ```php
    * $none = Either::none(null);
    * $noneNotNull = $none->flatMap(function($noneValue) { return Either::some($noneValue)->notNull(); });
    *
    * $some = Either::some(null);
    * $someNotNull = $some->flatMap(function($someValue) { return Either::some($someValue)->notNull(); });
    * ```
    *
    * _Notes:_
    *
    *  - `$alternativeFactory` must follow this interface `callable(TSome):Either<USome, TNone>`
    *  - Returns `Either<USome, TNone>`
    *
    * @template USome
    * @param callable(TSome):Either<USome, TNone> $mapFunc
    * @return Either<USome, TNone>
    **/
   public function flatMap(callable $mapFunc): self {
      /** @var callable(TNone):Either<USome, TNone> **/
      $noneFunc =
      /** @param TNone $noneValue */
      function($noneValue): Either {
         return self::none($noneValue);
      };

      return $this->match($mapFunc, $noneFunc);
   }

   /**
    * @param bool $condition
    * @param TNone $noneValue
    * @return Either<TSome, TNone>
    **/
   public function filter(bool $condition, $noneValue): self {
      return $this->hasValue && !$condition ? self::none($noneValue) : $this;
   }

   /**
    * Change the `Either::some($value)` into `Either::none()` iff `$filterFunc` returns false,
    * otherwise propigate the `Either::none()`
    *
    * ```php
    * $none = Either::none("Some Error Message");
    * $stillNone = $none->filterIf(function($x) { return $x > 10; }, "New none value");
    *
    * $some = Either::some(10);
    * $stillSome = $some->filterIf(function($x) { return $x == 10; }, "New none value");
    * $none = $some->filterIf(function($x) { return $x != 10; }, "New none value");
    * ```
    *
    * _Notes:_
    *
    *  - `$filterFunc` must follow this interface `callable(TSome):bool`
    *  - Returns `Either<TSome, TNone>`
    *
    * @param callable(TSome):bool $filterFunc
    * @param TNone $noneValue
    * @return Either<TSome, TNone>
    **/
   public function filterIf(callable $filterFunc, $noneValue): self {
      return $this->hasValue && !$filterFunc($this->someValue) ? self::none($noneValue) : $this;
   }

   /**
    * Turn an `Either::some(null)` into an `Either::none($noneValue)` iff `is_null($value)`
    *
    * ```php
    * $someThing = Either::some($myVar); // Valid
    * $noneThing = $someThing->notNull("The var was null"); // Turn null into an Either::none($noneValue)
    * ```
    *
    * _Notes:_
    *
    *  - Returns `Either<TSome, TNone>`
    *
    * @param TNone $noneValue
    * @return Either<TSome, TNone>
    **/
   public function notNull($noneValue): self {
      return $this->hasValue && is_null($this->someValue) ? self::none($noneValue) : $this;
   }

   /**
    * Turn an `Either::some(null)` into an `Either::none($noneValue)` iff `!$value == true`
    *
    * ```php
    * $someThing = Either::some($myVar); // Valid
    * $noneThing = $someThing->notNull("The var was null"); // Turn null into an Either::none($noneValue)
    * ```
    *
    * _Notes:_
    *
    *  - Returns `Either<TSome, TNone>`
    *
    * @param TNone $noneValue
    * @return Either<TSome, TNone>
    **/
   public function notFalsy($noneValue): self {
      return $this->hasValue && !$this->someValue ? self::none($noneValue) : $this;
   }

   /**
    * Returns true if the either's value == `$value`, otherwise false.
    *
    * ```php
    * $none = Either::none("Some Error Message");
    * $false = $none->contains(1);
    *
    * $some = Either::some(10);
    * $true = $some->contains(10);
    * $false = $some->contains("Thing");
    * ```
    * @param mixed $value
    **/
   public function contains($value): bool {
      if (!$this->hasValue()) {
         return false;
      }

      return $this->someValue == $value;
   }

   /**
    * Returns true if the `$existsFunc` returns true, otherwise false.
    *
    * ```php
    * $none = Either::none("Some Error Message");
    * $false = $none->exists(function($x) { return $x == 10; });
    *
    * $some = Either::some(10);
    * $true = $some->exists(function($x) { return $x >= 10; });
    * $false = $some->exists(function($x) { return $x == "Thing"; });
    * ```
    *
    * _Notes:_
    *
    *  - `$filterFunc` must follow this interface `callable(TSome):bool`
    *
    * @param callable(TSome):bool $existsFunc
    **/
   public function exists(callable $existsFunc): bool {
      if (!$this->hasValue()) {
         return false;
      }

      return $existsFunc($this->someValue);
   }

   /**
    * Returns an `Option` which drops the none value.
    *
    * ```php
    * $either = Either::none("Some Error Message");
    * $option = $either->toOption();
    * ```
    *
    * _Notes:_
    *
    *  - Returns `Option<U>`
    *
    * @template U
    * @return Option<U>
    **/
   public function ToOption(): Option {

      /** @var callable(TSome):Option<U> **/
      $someFunc =
      /** @param TSome $value **/
      function($value): Option {
         return Option::some($value);
      };

      /** @var callable(TNone):Option<U> **/
      $noneFunc =
      /** @param TSome $noneValue **/
      function($noneValue): Option {
         return Option::none();
      };

      return $this->match($someFunc, $noneFunc);
   }

   //////////////////////////////
   // STATIC FACTORY FUNCTIONS //
   //////////////////////////////

   /**
    * Creates an either with a boxed value
    *
    * ```php
    * $someThing = Either::some(1);
    * $someClass = Either::some(new SomeObject());
    *
    * $someNullThing = Either::some(null); // Valid
    * ```
    *
    * _Notes:_
    *
    *  - Returns `Either<TSome, mixed>`
    *
    * @param TSome $someValue
    * @return Either<TSome, mixed>
    **/
   public static function some($someValue): self {
      return new self($someValue, null, true);
   }

   /**
    * Creates an either which represents an empty box
    *
    * ```php
    * $none = Either::none("This is some string to show on no value");
    * ```
    *
    * _Notes:_
    *
    *  - Returns `Either<mixed, TNone>`
    *
    * @param TNone $noneValue
    * @return Either<mixed, TNone>
    **/
   public static function none($noneValue): self {
      return new self(null, $noneValue, false);
   }

   /**
    * Take a value, turn it a `Either::some($someValue)` iff the `$filterFunc` returns true
    * otherwise an `Either::none($noneValue)`
    *
    * ```php
    * $positiveOne = Either::someWhen(1, -1, function($x) { return $x > 0; });
    * $negativeOne = Either::someWhen(1, -1, function($x) { return $x < 0; });
    * ```
    * Note: `$filterFunc` must follow this interface `function filterFunc(mixed $value): bool`
    *
    * _Notes:_
    *
    *  - `$filterFunc` must follow this interface `callable():T`
    *  - Returns `Either<mixed, TNone>`
    *
    * @param TSome $someValue
    * @param TNone $noneValue
    * @param callable(TSome): bool $filterFunc
    * @return Either<TSome, TNone>
    **/
   public static function someWhen($someValue, $noneValue, callable $filterFunc): self {
      if ($filterFunc($someValue)) {
         return self::some($someValue);
      }
      return self::none($noneValue);
   }

   /**
    * Take a value, turn it a `Either::none($noneValue)` iff the `$filterFunc` returns true
    * otherwise an `Either::some($someValue)`
    *
    * ```php
    * $positiveOne = Either::noneWhen(1, -1, function($x) { return $x < 0; });
    * $negativeOne = Either::noneWhen(1, -1, function($x) { return $x > 0; });
    * ```
    *
    * _Notes:_
    *
    *  - `$filterFunc` must follow this interface `callable():T`
    *  - Returns `Either<mixed, TNone>`
    *
    * @param TSome $someValue
    * @param TNone $noneValue
    * @param callable(TSome): bool $filterFunc
    * @return Either<TSome, TNone>
    **/
   public static function noneWhen($someValue, $noneValue, callable $filterFunc): self {
      if ($filterFunc($someValue)) {
         return self::none($noneValue);
      }
      return self::some($someValue);
   }

   /**
    * Take a value, turn it a `Either::some($someValue)` iff `!is_null($someValue)`, otherwise returns `Either::none($noneValue)`
    *
    * ```php
    * $some = Either::some(null); // Valid, returns Some(null)
    * $none = Either::someNotNull(null); // Valid, returns None()
    * ```
    * _Notes:_
    *
    * - Returns `Either<TSome, TNone>`
    *
    * @param T $someValue
    * @param TNone $noneValue
    * @return Either<TSome, TNone>
    **/
    public static function someNotNull($someValue, $noneValue): self {
      return self::some($someValue)->notNull($noneValue);
   }

   /**
    * Creates a either if the `$key` exists in `$array`
    *
    * ```php
    * $some = Either::fromArray(['hello' => ' world'], 'hello', 'oh no');
    * $none = Either::fromArray(['hello' => ' world'], 'nope', 'oh no');
    * ```
    * _Notes:_
    *
    * - Returns `Either<TSome, TNone>`
    *
    * @param array $array
    * @param mixed $key The key of the array
    * @param TNone $noneValue
    *  @return Either<TSome, TNone>
    **/
    public static function fromArray(array $array, $key, $noneValue): self {
      if (isset($array[$key])) {
         return self::some($array[$key]);
      }

      return self::none($noneValue);
   }
}
