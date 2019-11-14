<?php

declare(strict_types = 1);

namespace Optional;

/**
 * @template TLeft
 * @template TRight
 */
class Either {
   /** @var bool */
   private $isLeft;
   /** @var TLeft */
   private $leftValue;
   /** @var TRight */
   private $rightValue;

   /**
    * @param TLeft $leftValue
    * @param TRight $rightValue
    **/
   private function __construct($leftValue, $rightValue, bool $isLeft) {
      $this->isLeft = $isLeft;
      $this->leftValue = $leftValue;
      $this->rightValue = $rightValue;
   }

   /**
    * Returns true iff the either is `Either::some`
    **/
   public function hasValue(): bool {
      return $this->isLeft;
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
    * @param TLeft $alternative
    * @return TLeft
    **/
   public function valueOr($alternative) {
      return $this->isLeft ? $this->leftValue : $alternative;
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
    * $myVar = $someThing->valueOrCreate(function($rightValue) { return new NewObject(); }); // 1
    * $myVar = $someClass->valueOrCreate(function($rightValue) { return new NewObject(); }); // instance of SomeObject
    *
    * $myVar = $none->valueOrCreate(function($rightValue) { return new NewObject(); }); // instance of NewObject
    * ```
    *
    * _Notes:_
    *
    *  - `$valueFactoryFunc` must follow this interface `callable(TRight):TLeft`
    *
    * @param callable(TRight):TLeft $alternativeFactory
    * @return TLeft
    **/
   public function valueOrCreate(callable $alternativeFactory) {
      return $this->isLeft ? $this->leftValue : $alternativeFactory($this->rightValue);
   }

   /**
    * Returns a `Either::some($value)` iff the either orginally was `Either::none($rightValue)`
    *
    * ```php
    * $none = Either::none();
    * $myVar = $none->or(10); // A some instance, with value 10
    * ```
    *
    * _Notes:_
    *
    *  - Returns `Either<TLeft, TRight>`
    *
    * @param TLeft $alternative
    * @return Either<TLeft, TRight>
    **/
   public function or($alternative): self {
      return $this->isLeft ? $this : self::some($alternative);
   }

   /**
    * Returns a `Either::some($value)` iff the the either orginally was `Either::none($rightValue)`
    *
    * The `$valueFactoryFunc` is called lazily - iff the either orginally was `Either::none($rightValue)`
    *
    * ```php
    * $none = Either::none();
    * $myVar = $none->orCreate(function($rightValue) { return 10; }); // A some instance, with value 10, but lazy
    * ```
    *
    * _Notes:_
    *
    *  - `$valueFactoryFunc` must follow this interface `callable(TRight):TLeft`
    *  - Returns `Either<TLeft, TRight>`
    *
    * @param callable(TRight):TLeft $alternativeFactory
    * @return Either<TLeft, TRight>
    **/
   public function orCreate(callable $alternativeFactory): self {
      return $this->isLeft ? $this : self::some($alternativeFactory($this->rightValue));
   }

   /**
    * iff `Either::none($rightValue)` return `$otherEither`, otherwise return the orginal `$either`
    *
    * ```php
    * $none = Either::none("Some Error Message");
    * $myVar = $none->else(Either::some(10)); // A some instance, with value 10
    * $myVar = $none->else(Either::none("Different Error Message")); // A new none instance
    * ```
    *
    * _Notes:_
    *
    *  - `$alternativeEither` must be of type `Either<TLeft, TRight>`
    *  - Returns `Either<TLeft, TRight>`
    *
    * @param Either<TLeft, TRight> $alternativeEither
    * @return Either<TLeft, TRight>
    **/
   public function else(self $alternativeEither): self {
      return $this->isLeft ? $this : $alternativeEither;
   }

   /**
    * iff `Either::none` return the `Either` returned by `$otherEitherFactoryFunc`, otherwise return the orginal `$either`
    *
    * `$otherEitherFactoryFunc` is run lazily
    *
    * ```php
    * $none = Either::none();
    *
    * $myVar = $none->elseCreate(function($rightValue) { return Either::some(10); }); // A some instance, with value 10, but lazy
    * ```
    *
    * _Notes:_
    *
    *  - `$alternativeEither` must be of type `callable(TRight):Either<TLeft, TRight> `
    *  - Returns `Either<TLeft, TRight>`
    *
    * @param callable(TRight):Either<TLeft, TRight> $alternativeEitherFactory
    * @return Either<TLeft, TRight>
    **/
   public function elseCreate(callable $alternativeEitherFactory): self {
      return $this->isLeft ? $this : $alternativeEitherFactory($this->rightValue);
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
    *    function($rightValue) { return $rightValue; }     // runs iff $someThing == Either::none
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
    *  - `$some` must follow this interface `callable(TLeft):U`
    *  - `$none` must follow this interface `callable(TRight):U`
    *
    * @template U
    * @param callable(TLeft):U $some
    * @param callable(TRight):U $none
    * @return U
    **/
   public function match(callable $some, callable $none) {
      return $this->isLeft ? $some($this->leftValue) : $none($this->rightValue);
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
    *  - `$some` must follow this interface `callable(TLeft):U`
    *
    * @param callable(TLeft) $some
    **/
   public function matchSome(callable $some): void {
      if (!$this->isLeft) {
         return;
      }

      $some($this->leftValue);
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
    *  - `$none` must follow this interface `callable(TRight):U`
    *
    * @param callable(TRight) $none
    **/
   public function matchNone(callable $none): void {
      if ($this->isLeft) {
         return;
      }

      $none($this->rightValue);
   }

   /**
    * Maps the `$value` of a `Either::some($value)`
    *
    * The `map` function runs iff the either is a `Either::some`
    * Otherwise the `Either:none($rightValue)` is propagated
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
    *  - `$mapFunc` must follow this interface `callable(TLeft):ULeft`
    *  - Returns `Either<TLeft, TRight>`
    *
    * @template ULeft
    * @param callable(TLeft):ULeft $mapFunc
    * @return Either<ULeft, TRight>
    **/
   public function map(callable $mapFunc): self {
      /** @var callable(TLeft):Either<ULeft, TRight> **/
      $someFunc =
      /** @param TLeft $value */
      function($value) use ($mapFunc): Either {
         return self::some($mapFunc($value));
      };

      /** @var callable(TRight):Either<ULeft, TRight> **/
      $noneFunc =
      /** @param TRight $rightValue */
      function($rightValue): Either {
         return self::none($rightValue);
      };

      return $this->match($someFunc, $noneFunc);
   }


   /**
    * `map`, but if an exception occurs, return `Either::none(exception)`
    *
    * Maps the `$value` of a `Either::some($value)`
    *
    * The map function runs iff the either's is a `Either::some`
    * Otherwise the `Either:none($rightValue)` is propagated
    *
    * ```php
    * $some = Either::some(['key' => 'value']);
    * $none = $some->safeMap(function($array) { $thing = $array['Missing Key will cause error']; return 5; });
    * ```
    *
    * _Notes:_
    *
    *  - `$mapFunc` must follow this interface `callable(TLeft):Either<ULeft, TRight>`
    *  - Returns `Either<TLeft, TRight>`
    *
    * @template ULeft
    * @param callable(TLeft):Either<ULeft, TRight> $mapFunc
    * @return Either<ULeft, TRight>
    **/
   public function mapSafely(callable $mapFunc): self {
      try {
         return $this->map($mapFunc);
      } catch (\Exception $e) {
         return Either::none($e);
      }
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
    * $person = Either::fromArray($somePerson, 'name', 'name was missing');
    *
    *   $name = $person->andThen(function($person) {
    *      $fullName = $person['first'] . $person['last'];
    *      try {
    *         $thing = SomeComplexThing::doWork($fullName);
    *      } catch (\Exception $e) {
    *         return Either::none('SomeComplexThing had an error!');
    *      }
    *      return Either::some($thing);
    *  });
    * ```
    *
    * _Notes:_
    *
    *  - `$mapFunc` must follow this interface `callable(TLeft):Either<ULeft, TRight>`
    *  - Returns `Either<TLeft, TRight>`
    *
    * @template ULeft
    * @param callable(TLeft):Either<ULeft, TRight> $mapFunc
    * @return Either<ULeft, TRight>
    **/
    public function andThen(callable $mapFunc): self {
      return $this->flatMap($mapFunc);
    }

   /**
    * Allows a function to map over the internal value, the function returns an Either
    *
    * ```php
    * $none = Either::none(null);
    * $noneNotNull = $none->flatMap(function($rightValue) { return Either::some($rightValue)->notNull(); });
    *
    * $some = Either::some(null);
    * $someNotNull = $some->flatMap(function($leftValue) { return Either::some($leftValue)->notNull(); });
    * ```
    *
    * _Notes:_
    *
    *  - `$alternativeFactory` must follow this interface `callable(TLeft):Either<ULeft, TRight>`
    *  - Returns `Either<ULeft, TRight>`
    *
    * @template ULeft
    * @param callable(TLeft):Either<ULeft, TRight> $mapFunc
    * @return Either<ULeft, TRight>
    **/
   public function flatMap(callable $mapFunc): self {
      /** @var callable(TRight):Either<ULeft, TRight> **/
      $noneFunc =
      /** @param TRight $rightValue */
      function($rightValue): Either {
         return self::none($rightValue);
      };

      return $this->match($mapFunc, $noneFunc);
   }

   /**
    * @param bool $condition
    * @param TRight $rightValue
    * @return Either<TLeft, TRight>
    **/
   public function filter(bool $condition, $rightValue): self {
      return $this->isLeft && !$condition ? self::none($rightValue) : $this;
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
    *  - `$filterFunc` must follow this interface `callable(TLeft):bool`
    *  - Returns `Either<TLeft, TRight>`
    *
    * @param callable(TLeft):bool $filterFunc
    * @param TRight $rightValue
    * @return Either<TLeft, TRight>
    **/
   public function filterIf(callable $filterFunc, $rightValue): self {
      return $this->isLeft && !$filterFunc($this->leftValue) ? self::none($rightValue) : $this;
   }

   /**
    * Turn an `Either::some(null)` into an `Either::none($rightValue)` iff `is_null($value)`
    *
    * ```php
    * $someThing = Either::some($myVar); // Valid
    * $noneThing = $someThing->notNull("The var was null"); // Turn null into an Either::none($rightValue)
    * ```
    *
    * _Notes:_
    *
    *  - Returns `Either<TLeft, TRight>`
    *
    * @param TRight $rightValue
    * @return Either<TLeft, TRight>
    **/
   public function notNull($rightValue): self {
      return $this->isLeft && is_null($this->leftValue) ? self::none($rightValue) : $this;
   }

   /**
    * Turn an `Either::some(null)` into an `Either::none($rightValue)` iff `!$value == true`
    *
    * ```php
    * $someThing = Either::some($myVar); // Valid
    * $noneThing = $someThing->notNull("The var was null"); // Turn null into an Either::none($rightValue)
    * ```
    *
    * _Notes:_
    *
    *  - Returns `Either<TLeft, TRight>`
    *
    * @param TRight $rightValue
    * @return Either<TLeft, TRight>
    **/
   public function notFalsy($rightValue): self {
      return $this->isLeft && !$this->leftValue ? self::none($rightValue) : $this;
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
      if (!$this->isLeft()) {
         return false;
      }

      return $this->leftValue == $value;
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
    *  - `$filterFunc` must follow this interface `callable(TLeft):bool`
    *
    * @param callable(TLeft):bool $existsFunc
    **/
   public function exists(callable $existsFunc): bool {
      if (!$this->isLeft()) {
         return false;
      }

      return $existsFunc($this->leftValue);
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

      /** @var callable(TLeft):Option<U> **/
      $someFunc =
      /** @param TLeft $value **/
      function($value): Option {
         return Option::some($value);
      };

      /** @var callable(TRight):Option<U> **/
      $noneFunc =
      /** @param TLeft $rightValue **/
      function($rightValue): Option {
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
    *  - Returns `Either<TLeft, mixed>`
    *
    * @param TLeft $leftValue
    * @return Either<TLeft, mixed>
    **/
   public static function some($leftValue): self {
      return new self($leftValue, null, true);
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
    *  - Returns `Either<mixed, TRight>`
    *
    * @param TRight $rightValue
    * @return Either<mixed, TRight>
    **/
   public static function none($rightValue): self {
      return new self(null, $rightValue, false);
   }

   /**
    * Take a value, turn it a `Either::some($leftValue)` iff the `$filterFunc` returns true
    * otherwise an `Either::none($rightValue)`
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
    *  - Returns `Either<mixed, TRight>`
    *
    * @param TLeft $leftValue
    * @param TRight $rightValue
    * @param callable(TLeft): bool $filterFunc
    * @return Either<TLeft, TRight>
    **/
   public static function someWhen($leftValue, $rightValue, callable $filterFunc): self {
      if ($filterFunc($leftValue)) {
         return self::some($leftValue);
      }
      return self::none($rightValue);
   }

   /**
    * Take a value, turn it a `Either::none($rightValue)` iff the `$filterFunc` returns true
    * otherwise an `Either::some($leftValue)`
    *
    * ```php
    * $positiveOne = Either::noneWhen(1, -1, function($x) { return $x < 0; });
    * $negativeOne = Either::noneWhen(1, -1, function($x) { return $x > 0; });
    * ```
    *
    * _Notes:_
    *
    *  - `$filterFunc` must follow this interface `callable():T`
    *  - Returns `Either<mixed, TRight>`
    *
    * @param TLeft $leftValue
    * @param TRight $rightValue
    * @param callable(TLeft): bool $filterFunc
    * @return Either<TLeft, TRight>
    **/
   public static function noneWhen($leftValue, $rightValue, callable $filterFunc): self {
      if ($filterFunc($leftValue)) {
         return self::none($rightValue);
      }
      return self::some($leftValue);
   }

   /**
    * Take a value, turn it a `Either::some($leftValue)` iff `!is_null($leftValue)`, otherwise returns `Either::none($rightValue)`
    *
    * ```php
    * $some = Either::some(null); // Valid, returns Some(null)
    * $none = Either::someNotNull(null); // Valid, returns None()
    * ```
    * _Notes:_
    *
    * - Returns `Either<TLeft, TRight>`
    *
    * @param T $leftValue
    * @param TRight $rightValue
    * @return Either<TLeft, TRight>
    **/
    public static function someNotNull($leftValue, $rightValue): self {
      return self::some($leftValue)->notNull($rightValue);
   }

   /**
    * Creates a either if the `$key` exists in `$array`
    *
    * ```php
    * $some = Either::fromArray(['hello' => 'world'], 'hello', 'oh no'); // Some('world')
    * $none = Either::fromArray(['hello' => 'world'], 'nope', 'oh no'); //  None('oh no')
    * $none = Either::fromArray(['hello' => 'world'], 'nope'); //  None(Exception("Either got null for noneValue"))
    * $none = Either::fromArray(['hello' => 'world'], 'nope', null); //  None(Exception("Either got null for noneValue"))
    * ```
    * _Notes:_
    *
    * - Returns `Either<TLeft, TRight>`
    *
    * @param array $array
    * @param mixed $key The key of the array
    * @param TRight $rightValue
    *  @return Either<TLeft, TRight>
    **/
    public static function fromArray(array $array, $key, $rightValue = null): self {
      if (isset($array[$key])) {
         return self::some($array[$key]);
      }

      if (is_null($rightValue)) {
         return self::none(new \Exception("Either got null for rightValue"));
      }

      return self::none($rightValue);
   }
}
