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
    * Returns true iff the either is `Either::left`
    **/
   public function hasValue(): bool {
      return $this->isLeft;
   }

   /**
    * Returns the either value or returns `$alternative`
    *
    * ```php
    * $leftThing = Either::left(1);
    * $leftClass = Either::left(new SomeObject());
    *
    * $right = Either::right("Error Code 123");
    *
    * $myVar = $leftThing->valueOr("Some other value!"); // 1
    * $myVar = $leftClass->valueOr("Some other value!"); // instance of SomeObject
    *
    * $myVar = $right->valueOr("Some other value!"); // "Some other value!"
    *
    * $right = Either::left(null)->valueOr("Some other value!"); // null, See either->notNull()
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
    * $leftThing = Either::left(1);
    * $leftClass = Either::left(new SomeObject());
    *
    * $right = Either::right("Error Code 123");
    *
    * $myVar = $leftThing->valueOrCreate(function($rightValue) { return new NewObject(); }); // 1
    * $myVar = $leftClass->valueOrCreate(function($rightValue) { return new NewObject(); }); // instance of SomeObject
    *
    * $myVar = $right->valueOrCreate(function($rightValue) { return new NewObject(); }); // instance of NewObject
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
    * Returns a `Either::left($value)` iff the either orginally was `Either::right($rightValue)`
    *
    * ```php
    * $right = Either::right();
    * $myVar = $right->or(10); // A left instance, with value 10
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
      return $this->isLeft ? $this : self::left($alternative);
   }

   /**
    * Returns a `Either::left($value)` iff the the either orginally was `Either::right($rightValue)`
    *
    * The `$valueFactoryFunc` is called lazily - iff the either orginally was `Either::right($rightValue)`
    *
    * ```php
    * $right = Either::right();
    * $myVar = $right->orCreate(function($rightValue) { return 10; }); // A left instance, with value 10, but lazy
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
      return $this->isLeft ? $this : self::left($alternativeFactory($this->rightValue));
   }

   /**
    * iff `Either::right($rightValue)` return `$otherEither`, otherwise return the orginal `$either`
    *
    * ```php
    * $right = Either::right("Some Error Message");
    * $myVar = $right->else(Either::left(10)); // A left instance, with value 10
    * $myVar = $right->else(Either::right("Different Error Message")); // A new right instance
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
    * iff `Either::right` return the `Either` returned by `$otherEitherFactoryFunc`, otherwise return the orginal `$either`
    *
    * `$otherEitherFactoryFunc` is run lazily
    *
    * ```php
    * $right = Either::right();
    *
    * $myVar = $right->elseCreate(function($rightValue) { return Either::left(10); }); // A left instance, with value 10, but lazy
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
    *  - `$left` iff the either is `Either::left`
    *  - `$right` iff the either is `Either::right`
    *
    * ```php
    * $leftThing = Either::left(1);
    *
    * $leftThingSquared = $leftThing->match(
    *    function($x) { return $x * $x; },               // runs iff $leftThing == Either::left
    *    function($rightValue) { return $rightValue; }     // runs iff $leftThing == Either::right
    * );
    *
    *
    * $configEither = Either::left($config)->notNull("Config was missing!");
    *
    * $configEither->match(
    *    function($x) { var_dump("Your config: {$x}"); },
    *    function($errorMessage) { var_dump($errorMessage); }
    * );
    * ```
    *
    * _Notes:_
    *
    *  - `$left` must follow this interface `callable(TLeft):U`
    *  - `$right` must follow this interface `callable(TRight):U`
    *
    * @template U
    * @param callable(TLeft):U $left
    * @param callable(TRight):U $right
    * @return U
    **/
   public function match(callable $left, callable $right) {
      return $this->isLeft ? $left($this->leftValue) : $right($this->rightValue);
   }

   /**
    * Side effect function: Runs the function iff the either is `Either::left`
    *
    * ```php
    * $configEither = Either::left($config)->notNull("Config was missing!");
    *
    * $configEither->matchLeft(
    *    function($x) { var_dump("Your config: {$x}"); }
    * );
    * ```
    *
    * _Notes:_
    *
    *  - `$left` must follow this interface `callable(TLeft):U`
    *
    * @param callable(TLeft) $left
    **/
   public function matchLeft(callable $left): void {
      if (!$this->isLeft) {
         return;
      }

      $left($this->leftValue);
   }

   /**
    * Side effect function: Runs the function iff the either is `Either::right`
    *
    * ```php
    * $configEither = Either::left($config)->notNull("Config was missing!");
    *
    * $configEither->matchRight(
    *    function($errorMessage) { var_dump($errorMessage); }
    * );
    * ```
    *
    * _Notes:_
    *
    *  - `$right` must follow this interface `callable(TRight):U`
    *
    * @param callable(TRight) $right
    **/
   public function matchRight(callable $right): void {
      if ($this->isLeft) {
         return;
      }

      $right($this->rightValue);
   }

   /**
    * Maps the `$value` of a `Either::left($value)`
    *
    * The `map` function runs iff the either is a `Either::left`
    * Otherwise the `Either:right($rightValue)` is propagated
    *
    * ```php
    * $right = Either::right("Some Error Message");
    * $stillRight = $right->map(function($x) { return $x * $x; });
    *
    * $left = Either::left(5);
    * $leftSquared = $left->map(function($x) { return $x * $x; });
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
      $leftFunc =
      /** @param TLeft $value */
      function($value) use ($mapFunc): Either {
         return self::left($mapFunc($value));
      };

      /** @var callable(TRight):Either<ULeft, TRight> **/
      $rightFunc =
      /** @param TRight $rightValue */
      function($rightValue): Either {
         return self::right($rightValue);
      };

      return $this->match($leftFunc, $rightFunc);
   }


   /**
    * `map`, but if an exception occurs, return `Either::right(exception)`
    *
    * Maps the `$value` of a `Either::left($value)`
    *
    * The map function runs iff the either's is a `Either::left`
    * Otherwise the `Either:right($rightValue)` is propagated
    *
    * ```php
    * $left = Either::left(['key' => 'value']);
    * $right = $left->safeMap(function($array) { $thing = $array['Missing Key will cause error']; return 5; });
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
         return Either::right($e);
      }
   }

   /**
    * A passthrough for FlatMap.
    * A nicer name through
    *
    * ```php
    * $leftPerson = [
    *     'name' => [
    *        'first' => 'First',
    *        'last' => 'Last'
    *     ]
    *  ];
    *
    * $person = Either::fromArray($leftPerson, 'name', 'name was missing');
    *
    *   $name = $person->andThen(function($person) {
    *      $fullName = $person['first'] . $person['last'];
    *      try {
    *         $thing = SomeComplexThing::doWork($fullName);
    *      } catch (\Exception $e) {
    *         return Either::right('SomeComplexThing had an error!');
    *      }
    *      return Either::left($thing);
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
    * $right = Either::right(null);
    * $rightNotNull = $right->flatMap(function($rightValue) { return Either::left($rightValue)->notNull(); });
    *
    * $left = Either::left(null);
    * $leftNotNull = $left->flatMap(function($leftValue) { return Either::left($leftValue)->notNull(); });
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
      $rightFunc =
      /** @param TRight $rightValue */
      function($rightValue): Either {
         return self::right($rightValue);
      };

      return $this->match($mapFunc, $rightFunc);
   }

   /**
    * @param bool $condition
    * @param TRight $rightValue
    * @return Either<TLeft, TRight>
    **/
   public function filter(bool $condition, $rightValue): self {
      return $this->isLeft && !$condition ? self::right($rightValue) : $this;
   }

   /**
    * Change the `Either::left($value)` into `Either::right()` iff `$filterFunc` returns false,
    * otherwise propigate the `Either::right()`
    *
    * ```php
    * $right = Either::right("Some Error Message");
    * $stillRight = $right->filterIf(function($x) { return $x > 10; }, "New right value");
    *
    * $left = Either::left(10);
    * $stillleft = $left->filterIf(function($x) { return $x == 10; }, "New right value");
    * $right = $left->filterIf(function($x) { return $x != 10; }, "New right value");
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
      return $this->isLeft && !$filterFunc($this->leftValue) ? self::right($rightValue) : $this;
   }

   /**
    * Turn an `Either::left(null)` into an `Either::right($rightValue)` iff `is_null($value)`
    *
    * ```php
    * $leftThing = Either::left($myVar); // Valid
    * $rightThing = $leftThing->notNull("The var was null"); // Turn null into an Either::right($rightValue)
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
      return $this->isLeft && is_null($this->leftValue) ? self::right($rightValue) : $this;
   }

   /**
    * Turn an `Either::left(null)` into an `Either::right($rightValue)` iff `!$value == true`
    *
    * ```php
    * $leftThing = Either::left($myVar); // Valid
    * $rightThing = $leftThing->notNull("The var was null"); // Turn null into an Either::right($rightValue)
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
      return $this->isLeft && !$this->leftValue ? self::right($rightValue) : $this;
   }

   /**
    * Returns true if the either's value == `$value`, otherwise false.
    *
    * ```php
    * $right = Either::right("Some Error Message");
    * $false = $right->contains(1);
    *
    * $left = Either::left(10);
    * $true = $left->contains(10);
    * $false = $left->contains("Thing");
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
    * $right = Either::right("Some Error Message");
    * $false = $right->exists(function($x) { return $x == 10; });
    *
    * $left = Either::left(10);
    * $true = $left->exists(function($x) { return $x >= 10; });
    * $false = $left->exists(function($x) { return $x == "Thing"; });
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
    * Returns an `Option` which drops the right value.
    *
    * ```php
    * $either = Either::right("Some Error Message");
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
      $leftFunc =
      /** @param TLeft $value **/
      function($value): Option {
         return Option::some($value);
      };

      /** @var callable(TRight):Option<U> **/
      $rightFunc =
      /** @param TLeft $rightValue **/
      function($rightValue): Option {
         return Option::right();
      };

      return $this->match($leftFunc, $rightFunc);
   }

   //////////////////////////////
   // STATIC FACTORY FUNCTIONS //
   //////////////////////////////

   /**
    * Creates an either with a boxed value
    *
    * ```php
    * $leftThing = Either::left(1);
    * $leftClass = Either::left(new SomeObject());
    *
    * $leftNullThing = Either::left(null); // Valid
    * ```
    *
    * _Notes:_
    *
    *  - Returns `Either<TLeft, mixed>`
    *
    * @param TLeft $leftValue
    * @return Either<TLeft, mixed>
    **/
   public static function left($leftValue): self {
      return new self($leftValue, null, true);
   }

   /**
    * Creates an either which represents an empty box
    *
    * ```php
    * $right = Either::right("This is left string to show on no value");
    * ```
    *
    * _Notes:_
    *
    *  - Returns `Either<mixed, TRight>`
    *
    * @param TRight $rightValue
    * @return Either<mixed, TRight>
    **/
   public static function right($rightValue): self {
      return new self(null, $rightValue, false);
   }

   /**
    * Take a value, turn it a `Either::left($leftValue)` iff the `$filterFunc` returns true
    * otherwise an `Either::right($rightValue)`
    *
    * ```php
    * $positiveOne = Either::leftWhen(1, -1, function($x) { return $x > 0; });
    * $negativeOne = Either::leftWhen(1, -1, function($x) { return $x < 0; });
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
   public static function leftWhen($leftValue, $rightValue, callable $filterFunc): self {
      if ($filterFunc($leftValue)) {
         return self::left($leftValue);
      }
      return self::right($rightValue);
   }

   /**
    * Take a value, turn it a `Either::right($rightValue)` iff the `$filterFunc` returns true
    * otherwise an `Either::left($leftValue)`
    *
    * ```php
    * $positiveOne = Either::rightWhen(1, -1, function($x) { return $x < 0; });
    * $negativeOne = Either::rightWhen(1, -1, function($x) { return $x > 0; });
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
   public static function rightWhen($leftValue, $rightValue, callable $filterFunc): self {
      if ($filterFunc($leftValue)) {
         return self::right($rightValue);
      }
      return self::left($leftValue);
   }

   /**
    * Take a value, turn it a `Either::left($leftValue)` iff `!is_null($leftValue)`, otherwise returns `Either::right($rightValue)`
    *
    * ```php
    * $left = Either::left(null); // Valid, returns left(null)
    * $right = Either::leftNotNull(null); // Valid, returns Right()
    * ```
    * _Notes:_
    *
    * - Returns `Either<TLeft, TRight>`
    *
    * @param T $leftValue
    * @param TRight $rightValue
    * @return Either<TLeft, TRight>
    **/
    public static function leftNotNull($leftValue, $rightValue): self {
      return self::left($leftValue)->notNull($rightValue);
   }

   /**
    * Creates a either if the `$key` exists in `$array`
    *
    * ```php
    * $left = Either::fromArray(['hello' => 'world'], 'hello', 'oh no'); // left('world')
    * $right = Either::fromArray(['hello' => 'world'], 'nope', 'oh no'); //  Right('oh no')
    * $right = Either::fromArray(['hello' => 'world'], 'nope'); //  Right(Exception("Either got null for rightValue"))
    * $right = Either::fromArray(['hello' => 'world'], 'nope', null); //  Right(Exception("Either got null for rightValue"))
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
         return self::left($array[$key]);
      }

      if (is_null($rightValue)) {
         return self::right(new \Exception("Either got null for rightValue"));
      }

      return self::right($rightValue);
   }
}
