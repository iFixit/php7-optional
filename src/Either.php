<?php

declare(strict_types = 1);

namespace Optional;

use Optional\Exceptions\MissingValueException;

/**
 * @template TLeft
 * @template TRight
 */
class Either {
   /**
    * @psalm-var bool
    * @psalm-readonly
    */
   private $isLeft;

   /**
    * @psalm-var TLeft
    * @psalm-readonly
    */
    private $leftValue;

    /**
     * @psalm-var TRight
     * @psalm-readonly
     */
   private $rightValue;

   /**
    * @psalm-param TLeft $leftValue
    * @psalm-param TRight $rightValue
    * @psalm-mutation-free
    **/
   private function __construct($leftValue, $rightValue, bool $isLeft) {
      $this->isLeft = $isLeft;
      $this->leftValue = $leftValue;
      $this->rightValue = $rightValue;
   }

   /**
    * Returns true iff the either is `Either::left`
    * @psalm-mutation-free
    **/
   public function isLeft(): bool {
      return $this->isLeft;
   }

   /**
    * Returns true iff the either is `Either::right`
    * @psalm-mutation-free
    **/
    public function isRight(): bool {
      return !$this->isLeft;
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
    * $myVar = $leftThing->leftOr("Some other value!"); // 1
    * $myVar = $leftClass->leftOr("Some other value!"); // instance of SomeObject
    *
    * $myVar = $right->leftOr("Some other value!"); // "Some other value!"
    *
    * $right = Either::left(null)->leftOr("Some other value!"); // null, See either->leftNotNull()
    * ```
    *
    * @psalm-mutation-free
    * @template TTLeft
    * @psalm-param TTLeft $alternative
    * @psalm-return (TLeft is never ? TTLeft : TLeft)
    **/
   public function leftOr($alternative) {
      return $this->isLeft
         ? $this->leftValue
         : $alternative;
   }

   /**
    * Returns the either value or throws
    *
    * @psalm-mutation-free
    * @psalm-return (TLeft is never ? never : TLeft)
    **/
    public function getLeft() {
      if (!$this->isLeft) {
         throw new MissingValueException("Left value is missing.");
      }

      return $this->leftValue;
   }

   /**
    * Returns the either value or returns `$alternative`
    *
    * @psalm-mutation-free
    * @template TTRight
    * @psalm-param TTRight $alternative
    * @psalm-return (TRight is never ? TTRight : TRight)
    **/
    public function rightOr($alternative) {
      return !$this->isLeft
         ? $this->rightValue
         : $alternative;
   }

   /**
    * Returns the either value or throws
    *
    * @psalm-mutation-free
    * @psalm-return (TRight is never ? never : TRight)
    **/
    public function getRight() {
      if ($this->isLeft) {
         throw new MissingValueException("Right value is missing.");
      }

      return $this->rightValue;
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
    * $myVar = $leftThing->leftOrCreate(function($rightValue) { return new NewObject(); }); // 1
    * $myVar = $leftClass->leftOrCreate(function($rightValue) { return new NewObject(); }); // instance of SomeObject
    *
    * $myVar = $right->leftOrCreate(function($rightValue) { return new NewObject(); }); // instance of NewObject
    * ```
    *
    * _Notes:_
    *
    *  - `$valueFactoryFunc` must follow this interface `callable(TRight):TLeft`
    *
    * @template TTLeft
    * @psalm-param callable(TRight):TTLeft $alternativeFactory
    * @psalm-return (TRight is never ? TLeft : TTLeft)
    **/
   public function leftOrCreate(callable $alternativeFactory) {
      return $this->isLeft
         ? $this->leftValue
         : $alternativeFactory($this->rightValue);
   }

   /**
    * Returns the either's value or calls `$valueFactoryFunc` and returns the value of that function
    *
    * _Notes:_
    *
    *  - `$valueFactoryFunc` must follow this interface `callable(TRight):TLeft`
    *
    * @template TTRight
    * @psalm-param callable(TLeft):TTRight $alternativeFactory
    * @psalm-return (TLeft is never ? TRight : TTRight)
    **/
   public function rightOrCreate(callable $alternativeFactory) {
      return !$this->isLeft
         ? $this->rightValue
         : $alternativeFactory($this->leftValue);
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
    * @psalm-mutation-free
    * @template TTLeft
    * @psalm-param TTLeft $alternative
    * @psalm-return (TRight is never ? Either<TLeft, TRight> : Either<TTLeft, never>)
    **/
   public function orLeft($alternative): self {
      return $this->isLeft
         ? $this
         : self::left($alternative);
   }

   /**
    * Returns a `Either::right($value)` iff the either orginally was `Either::left($leftValue)`
    *
    * _Notes:_
    *
    *  - Returns `Either<TLeft, TRight>`
    *
    * @psalm-mutation-free
    * @template TTRight
    * @psalm-param TTRight $alternative
    * @psalm-return (TLeft is never ? Either<never, TTRight> : Either<TLeft, TRight>)
    **/
   public function orRight($alternative): self {
      return !$this->isLeft
         ? $this
         : self::right($alternative);
   }

   /**
    * Returns a `Either::left($value)` iff the the either orginally was `Either::right($rightValue)`
    *
    * The `$valueFactoryFunc` is called lazily - iff the either orginally was `Either::right($rightValue)`
    *
    * ```php
    * $right = Either::right();
    * $myVar = $right->orCreateLeft(function($rightValue) { return 10; }); // A left instance, with value 10, but lazy
    * ```
    *
    * _Notes:_
    *
    *  - `$valueFactoryFunc` must follow this interface `callable(TRight):TLeft`
    *  - Returns `Either<TLeft, TRight>`
    *
    * @template TTLeft
    * @psalm-param callable(TRight):TTLeft $alternativeFactory
    * @psalm-return (TRight is never ? Either<TLeft, TRight> : self<TTLeft, never>)
    **/
   public function orCreateLeft(callable $alternativeFactory): self {
      return $this->isLeft
         ? $this
         : self::left($alternativeFactory($this->rightValue));
   }

   /**
    * Returns a `Either::right($value)` iff the the either orginally was `Either::left($leftValue)`
    *
    * The `$alternativeFactory` is called lazily - iff the either orginally was `Either::left($leftValue)`
    *
    * _Notes:_
    *
    *  - `$alternativeFactory` must follow this interface `callable(TRight):TLeft`
    *  - Returns `Either<TLeft, TRight>`
    *
    * @template TTRight
    * @psalm-param callable(TLeft):TTRight $alternativeFactory
    * @psalm-return (TLeft is never ? Either<TLeft, TRight> : self<never, TTRight>)
    **/
   public function orCreateRight(callable $alternativeFactory): self {
      return !$this->isLeft
         ? $this
         : self::right($alternativeFactory($this->leftValue));
   }

   /**
    * iff `Either::right($rightValue)` return `$otherEither`, otherwise return the orginal `$either`
    *
    * ```php
    * $right = Either::right("Some Error Message");
    * $myVar = $right->elseLeft(Either::left(10)); // A left instance, with value 10
    * $myVar = $right->elseLeft(Either::right("Different Error Message")); // A new right instance
    * ```
    *
    * _Notes:_
    *
    *  - `$alternativeEither` must be of type `Either<TLeft, TRight>`
    *  - Returns `Either<TLeft, TRight>`
    *
    * @psalm-mutation-free
    * @template TLeftOther
    * @template TRightOther
    * @psalm-param Either<TLeftOther, TRightOther> $alternativeEither
    * @psalm-return (TRight is never ? Either<TLeft, TRight> : Either<TLeftOther, TRightOther>)
    **/
   public function elseLeft(self $alternativeEither): self {
      return $this->isLeft
         ? $this
         : $alternativeEither;
   }

   /**
    * iff `Either::left($leftValue)` return `$otherEither`, otherwise return the orginal `$either`
    *
    * _Notes:_
    *
    *  - `$alternativeEither` must be of type `Either<TLeft, TRight>`
    *  - Returns `Either<TLeft, TRight>`
    *
    * @psalm-mutation-free
    * @template TLeftOther
    * @template TRightOther
    * @psalm-param Either<TLeftOther, TRightOther> $alternativeEither
    * @psalm-return (TLeft is never ? Either<TLeft, TRight> : Either<TLeftOther, TRightOther>)
    **/
   public function elseRight(self $alternativeEither): self {
      return !$this->isLeft
         ? $this
         : $alternativeEither;
   }

   /**
    * iff `Either::right` return the `Either` returned by `$otherEitherFactoryFunc`, otherwise return the orginal `$either`
    *
    * `$otherEitherFactoryFunc` is run lazily
    *
    * ```php
    * $right = Either::right();
    *
    * $myVar = $right->elseCreateLeft(function($rightValue) { return Either::left(10); }); // A left instance, with value 10, but lazy
    * ```
    *
    * _Notes:_
    *
    *  - `$alternativeEither` must be of type `callable(TRight):Either<TLeft, TRight> `
    *  - Returns `Either<TLeft, TRight>`
    *
    * @template TLeftOther
    * @template TRightOther
    * @psalm-param callable(TRight):Either<TLeftOther, TRightOther> $alternativeEitherFactory
    * @psalm-return (TRight is never ? Either<TLeft, TRight> : Either<TLeftOther, TRightOther>)
    **/
   public function elseCreateLeft(callable $alternativeEitherFactory): self {
      return $this->isLeft
         ? $this
         : $alternativeEitherFactory($this->rightValue);
   }

   /**
    * iff `Either::left` return the `Either` returned by `$otherEitherFactoryFunc`, otherwise return the orginal `$either`
    *
    * _Notes:_
    *
    *  - `$alternativeEither` must be of type `callable(TLeft):Either<TLeft, TRight> `
    *  - Returns `Either<TLeft, TRight>`
    *
    * @template TLeftOther
    * @template TRightOther
    * @psalm-param callable(TLeft):Either<TLeftOther, TRightOther> $alternativeEitherFactory
    * @psalm-return (TLeft is never ? Either<TLeft, TRight> : Either<TLeftOther, TRightOther>)
    **/
   public function elseCreateRight(callable $alternativeEitherFactory): self {
      return !$this->isLeft
         ? $this
         : $alternativeEitherFactory($this->leftValue);
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
    * $configEither = Either::left($config)->leftNotNull("Config was missing!");
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
    * @template ULeft
    * @template URight
    * @psalm-param callable(TLeft):ULeft $left
    * @psalm-param callable(TRight):URight $right
    * @psalm-return (TRight is never ? ULeft : URight)
    **/
   public function match(callable $left, callable $right) {
      return $this->isLeft
         ? $left($this->leftValue)
         : $right($this->rightValue);
   }

   /**
    * Side effect function: Runs the function iff the either is `Either::left`
    *
    * ```php
    * $configEither = Either::left($config)->leftNotNull("Config was missing!");
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
    * @psalm-param callable(TLeft) $left
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
    * $configEither = Either::left($config)->leftNotNull("Config was missing!");
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
    * @psalm-param callable(TRight) $right
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
    * $stillRight = $right->mapLeft(function($x) { return $x * $x; });
    *
    * $left = Either::left(5);
    * $leftSquared = $left->mapLeft(function($x) { return $x * $x; });
    * ```
    *
    * _Notes:_
    *
    *  - `$mapFunc` must follow this interface `callable(TLeft):ULeft`
    *  - Returns `Either<TLeft, TRight>`
    *
    * @template ULeft
    * @psalm-param callable(TLeft):ULeft $mapFunc
    * @psalm-return Either<ULeft, TRight>
    **/
   public function mapLeft(callable $mapFunc): self {
      /** @psalm-var callable(TLeft):Either<ULeft, TRight> **/
      $leftFunc =
      /** @psalm-param TLeft $value */
      function($value) use ($mapFunc): Either {
         return self::left($mapFunc($value));
      };

      /** @psalm-var callable(TRight):Either<ULeft, TRight> **/
      $rightFunc =
      /** @psalm-param TRight $rightValue */
      function($rightValue): Either {
         return self::right($rightValue);
      };

      return $this->match($leftFunc, $rightFunc);
   }

   /**
    * Maps the `$value` of a `Either::right($value)`
    *
    * The `map` function runs iff the either is a `Either::right`
    * Otherwise the `Either:left($leftValue)` is propagated
    *
    * _Notes:_
    *
    *  - `$mapFunc` must follow this interface `callable(TRight):URight`
    *  - Returns `Either<TLeft, URight>`
    *
    * @template URight
    * @psalm-param callable(TRight):URight $mapFunc
    * @psalm-return (TLeft is never ? Either<TLeft, URight> : Either<TLeft, TRight>)
    **/
    public function mapRight(callable $mapFunc): self {
      /** @psalm-var callable(TLeft):Either<TLeft, URight> **/
      $leftFunc =
      /** @psalm-param TLeft $value */
      function($value): Either {
         return self::left($value);
      };

      /** @psalm-var callable(TRight):Either<TLeft, URight> **/
      $rightFunc =
      /** @psalm-param TRight $rightValue */
      function($rightValue) use ($mapFunc): Either {
         return self::right($mapFunc($rightValue));
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
    * $right = $left->mapLeftSafely(function($array) { $thing = $array['Missing Key will cause error']; return 5; });
    * ```
    *
    * _Notes:_
    *
    *  - `$mapFunc` must follow this interface `callable(TLeft):Either<ULeft, TRight>`
    *  - Returns `Either<TLeft, TRight>`
    *
    * @template ULeft
    * @psalm-param callable(TLeft):ULeft $mapFunc
    * @psalm-return Either<ULeft, TRight>|Either<never, \Exception>
    **/
   public function mapLeftSafely(callable $mapFunc): self {
      try {
         return $this->mapLeft($mapFunc);
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
    * @psalm-param callable(TLeft):Either<ULeft, TRight> $mapFunc
    * @psalm-return (TRight is never ? Either<ULeft, TRight> : Either<TLeft, TRight>)
    **/
    public function andThen(callable $mapFunc): self {
      return $this->flatMapLeft($mapFunc);
    }

   /**
    * Allows a function to map over the internal value, the function returns an Either
    *
    * ```php
    * $right = Either::right(null);
    * $rightNotNull = $right->flatMapLeft(function($rightValue) { return Either::left($rightValue)->leftNotNull(); });
    *
    * $left = Either::left(null);
    * $leftNotNull = $left->flatMapLeft(function($leftValue) { return Either::left($leftValue)->leftNotNull(); });
    * ```
    *
    * _Notes:_
    *
    *  - `$alternativeFactory` must follow this interface `callable(TLeft):Either<ULeft, TRight>`
    *  - Returns `Either<ULeft, TRight>`
    *
    * @template ULeft
    * @psalm-param callable(TLeft):Either<ULeft, TRight> $mapFunc
    * @psalm-return (TRight is never ? Either<ULeft, TRight> : Either<TLeft, TRight>)
    **/
   public function flatMapLeft(callable $mapFunc): self {
      /** @psalm-var callable(TRight):Either<ULeft, TRight> **/
      $rightFunc =
      /** @psalm-param TRight $rightValue */
      function($rightValue): self {
         return self::right($rightValue);
      };

      return $this->match($mapFunc, $rightFunc);
   }

   /**
    * @psalm-mutation-free
    * @psalm-param bool $condition
    * @template TTRight
    * @psalm-param TTRight $rightValue
    * @psalm-return (
    *   $condition is true ? Either<TLeft, TRight> : (
    *   TRight is never ? Either<never, TTRight> :
    *   Either<TLeft, TRight>
    * ))
    **/
   public function filterLeft(bool $condition, $rightValue): self {
      return $this->isLeft && !$condition
         ? self::right($rightValue)
         : $this;
   }

   /**
    * @psalm-mutation-free
    * @psalm-param bool $condition
    * @template TTLeft
    * @psalm-param TTLeft $leftValue
    * @psalm-return (
    *   $condition is true ? Either<TLeft, TRight> : (
    *   TLeft is never ? Either<TTLeft, never> :
    *   Either<TLeft, TRight>
    * ))
    **/
   public function filterRight(bool $condition, $leftValue): self {
      return !$this->isLeft && !$condition
         ? self::left($leftValue)
         : $this;
   }

   /**
    * Change the `Either::left($value)` into `Either::right()` iff `$filterFunc` returns false,
    * otherwise propigate the `Either::right()`
    *
    * ```php
    * $right = Either::right("Some Error Message");
    * $stillRight = $right->filterLeftIf(function($x) { return $x > 10; }, "New right value");
    *
    * $left = Either::left(10);
    * $stillleft = $left->filterLeftIf(function($x) { return $x == 10; }, "New right value");
    * $right = $left->filterLeftIf(function($x) { return $x != 10; }, "New right value");
    * ```
    *
    * _Notes:_
    *
    *  - `$filterFunc` must follow this interface `callable(TLeft):bool`
    *  - Returns `Either<TLeft, TRight>`
    *
    * @template TTRight
    * @psalm-param callable(TLeft):bool $filterFunc
    * @psalm-param TTRight $rightValue
    * @psalm-return (
    *   TLeft is never ? Either<TLeft, TRight> : Either<never, TTRight>| Either<TLeft, TRight>
    * )
    **/
   public function filterLeftIf(callable $filterFunc, $rightValue): self {
      return $this->isLeft && !$filterFunc($this->leftValue)
         ? self::right($rightValue)
         : $this;
   }

   /**
    * Change the `Either::right($value)` into `Either::left()` iff `$filterFunc` returns false,
    * otherwise propigate the `Either::left()`
    *
    * _Notes:_
    *
    *  - `$filterFunc` must follow this interface `callable(TRight):bool`
    *  - Returns `Either<TLeft, TRight>`
    *
    * @template TTLeft
    * @psalm-param callable(TRight):bool $filterFunc
    * @psalm-param TTLeft $leftValue
    * @psalm-return (
    *   TRight is never ? Either<TLeft, TRight> : Either<TTLeft, never>|Either<TLeft, TRight>
    * )
    **/
    public function filterRightIf(callable $filterFunc, $leftValue): self {
      return !$this->isLeft && !$filterFunc($this->rightValue)
         ? self::left($leftValue)
         : $this;
   }

   /**
    * Turn an `Either::left(null)` into an `Either::right($rightValue)` iff `is_null($value)`
    *
    * ```php
    * $leftThing = Either::left($myVar); // Valid
    * $rightThing = $leftThing->leftNotNull("The var was null"); // Turn null into an Either::right($rightValue)
    * ```
    *
    * _Notes:_
    *
    *  - Returns `Either<TLeft, TRight>`
    *
    * @psalm-mutation-free
    * @template TTRight
    * @psalm-param TTRight $rightValue
    * @psalm-return (TLeft is null ? Either<never, TTRight> : Either<TLeft, TRight>)
    **/
   public function leftNotNull($rightValue): self {
      return $this->isLeft && is_null($this->leftValue)
      ? self::right($rightValue): $this;
   }

   /**
    * Turn an `Either::right(null)` into an `Either::left($leftValue)` iff `is_null($value)`
    *
    * _Notes:_
    *
    *  - Returns `Either<TLeft, TRight>`
    *
    * @psalm-mutation-free
    * @template TTLeft
    * @psalm-param TTLeft $leftValue
    * @psalm-return (TRight is null ? Either<TTLeft, never> : Either<TLeft, TRight>)
    **/
    public function rightNotNull($leftValue): self {
      return !$this->isLeft && is_null($this->rightValue)
         ? self::left($leftValue)
         : $this;
   }

   /**
    * Turn an `Either::left(null)` into an `Either::right($rightValue)` iff `!$value == true`
    *
    * ```php
    * $leftThing = Either::left($myVar); // Valid
    * $rightThing = $leftThing->leftNotNull("The var was null"); // Turn null into an Either::right($rightValue)
    * ```
    *
    * _Notes:_
    *
    *  - Returns `Either<TLeft, TRight>`
    *
    * @psalm-mutation-free
    * @template TTRight
    * @psalm-param TTRight $rightValue
    * @psalm-return (
    *     TLeft is null                ? self<never, TTRight> : (
    *     TLeft is false               ? self<never, TTRight> : (
    *     TLeft is array<never, never> ? self<never, TTRight> : (
    *     TLeft is 0                   ? self<never, TTRight> : (
    *     TLeft is 0.0                 ? self<never, TTRight> : (
    *     TLeft is ''                  ? self<never, TTRight> : (
    *     TLeft is '0'                 ? self<never, TTRight> :
    *     self<TLeft, TRight>
    * )))))))
    **/
   public function leftNotFalsy($rightValue): self {
      return $this->isLeft && !$this->leftValue
         ? self::right($rightValue)
         : $this;
   }

   /**
    * Turn an `Either::right(null)` into an `Either::left($leftValue)` iff `!$value == true`
    *
    * _Notes:_
    *
    *  - Returns `Either<TLeft, TRight>`
    *
    * @psalm-mutation-free
    * @template TTLeft
    * @psalm-param TTLeft $leftValue
    * @psalm-return (
    *     TRight is null                ? self<TTLeft, never> : (
    *     TRight is false               ? self<TTLeft, never> : (
    *     TRight is array<never, never> ? self<TTLeft, never> : (
    *     TRight is 0                   ? self<TTLeft, never> : (
    *     TRight is 0.0                 ? self<TTLeft, never> : (
    *     TRight is ''                  ? self<TTLeft, never> : (
    *     TRight is '0'                 ? self<TTLeft, never> :
    *     self<TLeft, TRight>
    * )))))))
    **/
   public function rightNotFalsy($leftValue): self {
      return !$this->isLeft && !$this->rightValue
         ? self::left($leftValue)
         : $this;
   }

   /**
    * Returns true if the either's value == `$value`, otherwise false.
    *
    * ```php
    * $right = Either::right("Some Error Message");
    * $false = $right->leftContains(1);
    *
    * $left = Either::left(10);
    * $true = $left->leftContains(10);
    * $false = $left->leftContains("Thing");
    * ```
    *
    * @psalm-mutation-free
    * @psalm-param mixed $value
    **/
   public function leftContains($value): bool {
      if (!$this->isLeft()) {
         return false;
      }

      return $this->leftValue == $value;
   }

   /**
    * Returns true if the either's value == `$value`, otherwise false.
    *
    * @psalm-mutation-free
    * @psalm-param mixed $value
    **/
   public function rightContains($value): bool {
      if (!$this->isRight()) {
         return false;
      }

      return $this->rightValue == $value;
   }

   /**
    * Returns true if the `$existsFunc` returns true, otherwise false.
    *
    * ```php
    * $right = Either::right("Some Error Message");
    * $false = $right->existsLeft(function($x) { return $x == 10; });
    *
    * $left = Either::left(10);
    * $true = $left->existsLeft(function($x) { return $x >= 10; });
    * $false = $left->existsLeft(function($x) { return $x == "Thing"; });
    * ```
    *
    * _Notes:_
    *
    *  - `$filterFunc` must follow this interface `callable(TLeft):bool`
    *
    * @psalm-param callable(TLeft):bool $existsFunc
    **/
   public function existsLeft(callable $existsFunc): bool {
      if (!$this->isLeft()) {
         return false;
      }

      return $existsFunc($this->leftValue);
   }

   /**
    * Returns true if the `$existsFunc` returns true, otherwise false.
    *
    * _Notes:_
    *
    *  - `$filterFunc` must follow this interface `callable(TRight):bool`
    *
    * @psalm-param callable(TRight):bool $existsFunc
    **/
   public function existsRight(callable $existsFunc): bool {
      if (!$this->isRight()) {
         return false;
      }

      return $existsFunc($this->rightValue);
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
    * @psalm-return Option<U>
    **/
   public function toOptionFromLeft(): Option {

      /** @psalm-var callable(TLeft):Option<U> **/
      $leftFunc =
      /** @psalm-param TLeft $value **/
      function($value): Option {
         return Option::some($value);
      };

      /** @psalm-var callable(TRight):Option<U> **/
      $rightFunc =
      /** @psalm-param TRight $_rightValue **/
      function($_rightValue): Option {
         return Option::none();
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
    *  - Returns `Either<TTLeft, mixed>`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @template TTLeft
    * @template TTRight
    * @psalm-param TTLeft $leftValue
    * @psalm-return Either<TTLeft, never>
    **/
   public static function left($leftValue): self {
      /** @var self<TTLeft, never> */
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
    *  - Returns `Either<TTLeft, TTRight>`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @template TTLeft
    * @template TTRight
    * @psalm-param TTRight $rightValue
    * @psalm-return Either<never, TTRight>
    **/
   public static function right($rightValue): self {
      /** @psalm-var Either<never, TTRight> */
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
    *  - `$filterFunc` must follow this interface `callable(TTLeft): bool`
    *  - Returns `Either<mixed, TTRight>`
    *
    * @template TTLeft
    * @template TTRight
    * @psalm-param TTLeft $leftValue
    * @psalm-param TTRight $rightValue
    * @psalm-param callable(TTLeft): bool $filterFunc
    * @psalm-return Either<TTLeft, TTRight>
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
    *  - `$filterFunc` must follow this interface `callable(TTLeft): bool`
    *  - Returns `Either<mixed, TTRight>`
    *
    * @template TTLeft
    * @template TTRight
    * @psalm-param TTLeft $leftValue
    * @psalm-param TTRight $rightValue
    * @psalm-param callable(TTLeft): bool $filterFunc
    * @psalm-return Either<TTLeft, TTRight>
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
    * - Returns `Either<TTLeft, TTRight>`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @template TTLeft
    * @template TTRight
    * @psalm-param TTLeft $leftValue
    * @psalm-param TTRight $rightValue
    * @psalm-return (TTLeft is null ? self<never, TTRight> : self<TTLeft, never>)
    **/
    public static function notNullLeft($leftValue, $rightValue): self {
      return self::left($leftValue)->leftNotNull($rightValue);
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
    * - Returns `Either<TTLeft, TTRight>`
    *
    * @psalm-mutation-free
    * @template TTLeft
    * @template TTRight
    * @psalm-param array<array-key, mixed> $array
    * @psalm-param array-key $key The key of the array
    * @psalm-param TTRight $rightValue

    * @template TArray of array
    * @param TArray $array
    * @param array-key $key
    * @psalm-return ($key is key-of<TArray> ? self<value-of<TArray>, never>|self<mixed, never> :(TTRight is null ? self<never, \Exception> : self<never, TTRight>))
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

   /**
    * @psalm-suppress InvalidCast
    *
    * This is due to this class being a box.
    * I can't ensure the boxed value is stringable.
    * https://github.com/vimeo/psalm/issues/1982
    * @psalm-mutation-free
    */
    public function __toString() {
      if ($this->isLeft) {
         if ($this->leftValue === null) {
            return "Left(null)";
         }
         return "Left({$this->leftValue})";
      } else {
         if ($this->rightValue === null) {
            return "Right(null)";
         }
         return "Right({$this->rightValue})";
      }
   }
}