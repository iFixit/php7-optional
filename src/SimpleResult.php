<?php

declare(strict_types = 1);

namespace Optional;

use Optional\Either;

use Throwable;
use Exception;

/**
 * @psalm-immutable
 * @template TOkay
 * @template TError as Throwable
 */
class SimpleResult {
   /**
    * @psalm-var Either
    * @psalm-readonly
    */
   private $either;

   /**
    * @psalm-param Either $either
    **/
    private function __construct(Either $either) {
      $this->either = $either;
   }

   /**
    * @psalm-mutation-free
    * @psalm-pure
    * @psalm-param TOkay $data
    * @psalm-return SimpleResult<TOkay, Throwable>
    **/
   public static function okay($data): self {
      $either = Either::left($data);
      return new self($either);
   }

   /**
    * @psalm-mutation-free
    * @psalm-pure
    * @psalm-param Throwable $errorData
    * @psalm-return SimpleResult<TOkay, Throwable>
    **/
   public static function error(Throwable $errorData): self {
      $either = Either::right($errorData);
      return new self($either);
   }

   /**
    * Returns true iff the SimpleResult is `SimpleResult::okay`
    * @psalm-mutation-free
    * @psalm-pure
    **/
   public function isOkay(): bool {
      return $this->either->isLeft();
   }

   /**
    * Returns true iff the SimpleResult is `SimpleResult::error`
    * @psalm-mutation-free
    * @psalm-pure
    **/
   public function isError(): bool {
      return $this->either->isRight();
   }

   /**
    * Returns the SimpleResult value or throws the current error
    * @psalm-mutation-free
    * @psalm-pure
    *
    * @psalm-return TOkay
    **/
   public function dataOrThrow() {
      if ($this->either->isLeft()) {
         /** @psalm-var TOkay **/
         return $this->either->leftOr(null);
      } else {
         /** @psalm-var TError **/
         $ex = $this->either->rightOr(null);
         throw new Exception($ex->getMessage(), (int)$ex->getCode(), $ex);
      }
   }

   /**
    * Returns a `SimpleResult::okay($data)` iff the SimpleResult orginally was `SimpleResult::error($errorValue)`
    *
    * _Notes:_
    *
    *  - Returns `SimpleResult<TOkay, Throwable>`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @psalm-param TOkay $data
    * @psalm-return SimpleResult<TOkay, Throwable>
    **/
   public function orSetDataTo($data): self {
      $either = $this->either->orLeft($data);
      return new self($either);
   }

   /**
    * Returns a `SimpleResult::okay($value)` iff the the SimpleResult orginally was `SimpleResult::error($errorValue)`
    *
    * The `$alternativeFactory` is called lazily - iff the SimpleResult orginally was `SimpleResult::error($errorValue)`
    *
    * _Notes:_
    *
    *  - `$alternativeFactory` must follow this interface `callable(Throwable):TOkay`
    *  - Returns `SimpleResult<TOkay, Throwable>`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @psalm-param callable(Throwable):TOkay $alternativeFactory
    * @psalm-return SimpleResult<TOkay, Throwable>
    **/
   public function orCreateSimpleResultWithData(callable $alternativeFactory): self {
      try {
         $either = $this->either->orCreateLeft($alternativeFactory);
         return new self($either);
      } catch (\Throwable $e) {
         $either = Either::right($e);
         return new self($either);
      }
   }

   /**
    * iff `SimpleResult::error($errorValue)` return `$alternativeSimpleResult`, otherwise return the original `$SimpleResult`
    *
    * _Notes:_
    *
    *  - `$alternativeSimpleResult` must be of type `SimpleResult<TOkay, Throwable>`
    *  - Returns `SimpleResult<TOkay, Throwable>`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @psalm-param SimpleResult<TOkay, Throwable> $alternativeSimpleResult
    * @psalm-return SimpleResult<TOkay, Throwable>
    **/
   public function  okayOr(self $alternativeSimpleResult): self {
      $either = $this->either->elseLeft($alternativeSimpleResult->either);
      return new self($either);
   }

   /**
    * iff `SimpleResult::error` return the `SimpleResult` returned by `$alternativeSimpleResultFactory`, otherwise return the orginal `$SimpleResult`
    *
    * `$alternativeSimpleResultFactory` is run lazily
    *
    * _Notes:_
    *
    *  - `$alternativeSimpleResultFactory` must be of type `callable(Throwable):SimpleResult<TOkay, Throwable> `
    *  - Returns `SimpleResult<TOkay, Throwable>`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @psalm-param callable(Throwable):SimpleResult<TOkay, Throwable> $alternativeSimpleResultFactory
    * @psalm-return SimpleResult<TOkay, Throwable>
    **/
   public function createIfError(callable $alternativeSimpleResultFactory): self {
      /** @psalm-var callable(TOkay):Either<TOkay, Throwable> **/
      $realFactory =
      /** @psalm-param Throwable $errorValue */
      function (Throwable $errorValue) use ($alternativeSimpleResultFactory): Either {
         $SimpleResult = $alternativeSimpleResultFactory($errorValue);
         return $SimpleResult->either;
      };

      try {
         $either = $this->either->elseCreateLeft($realFactory);
         return new self($either);
      } catch (\Throwable $e) {
         $either = Either::right($e);
         return new self($either);
      }
   }

   /**
    * Runs only 1 function:
    *
    *  - `$dataFunc` iff the SimpleResult is `SimpleResult::okay`
    *  - `$errorFunc` iff the SimpleResult is `SimpleResult::error`
    *
    * _Notes:_
    *
    *  - `$dataFunc` must follow this interface `callable(TOkay):U`
    *  - `$errorFunc` must follow this interface `callable(Throwable):U`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @template U
    * @psalm-param callable(TOkay):U $dataFunc
    * @psalm-param callable(Throwable):U $errorFunc
    * @psalm-return U
    **/
   public function run(callable $dataFunc, callable $errorFunc) {
      return $this->either->match($dataFunc, $errorFunc);
   }

   /**
    * Side effect function: Runs the function iff the SimpleResult is `SimpleResult::okay`
    *
    * _Notes:_
    *
    *  - `$dataFunc` must follow this interface `callable(TOkay):U`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @psalm-param callable(TOkay) $dataFunc
    **/
   public function runOnOkay(callable $dataFunc): void {
      $this->either->matchLeft($dataFunc);
   }

   /**
    * Side effect function: Runs the function iff the SimpleResult is `SimpleResult::error`
    *
    * _Notes:_
    *
    *  - `$errorFunc` must follow this interface `callable(Throwable):U`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @psalm-param callable(Throwable) $errorFunc
    **/
   public function runOnError(callable $errorFunc): void {
      $this->either->matchRight($errorFunc);
   }

   /**
    * `map`, but if an Throwable occurs, return `SimpleResult::error(Throwable)`
    *
    * The `map` function runs iff the SimpleResult is a `SimpleResult::okay`
    * Otherwise the `SimpleResult:error($errorValue)` is propagated
    *
    * _Notes:_
    *
    *  - `$mapFunc` must follow this interface `callable(TOkay):UOkay`
    *  - Returns `SimpleResult<UOkay, Throwable>`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @template UOkay
    * @psalm-param callable(TOkay):UOkay $mapFunc
    * @psalm-return SimpleResult<UOkay, Throwable>
    **/
   public function map(callable $mapFunc): self {
      $either = $this->either->mapLeftSafely($mapFunc);
      return new self($either);
   }

   /**
    * Maps the `$value` of a `SimpleResult::error($errorValue)`
    *
    * The `map` function runs iff the SimpleResult is a `SimpleResult::error`
    * Otherwise the `SimpleResult:okay($data)` is propagated
    *
    * _Notes:_
    *
    *  - `$mapFunc` must follow this interface `callable(Throwable):Throwable`
    *  - Returns `SimpleResult<TOkay, Throwable>`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @psalm-param callable(Throwable):Throwable $mapFunc
    * @psalm-return SimpleResult<TOkay, Throwable>
    **/
   public function mapError(callable $mapFunc): self {
      try {
         $either = $this->either->mapRight($mapFunc);
         return new self($either);
      } catch (\Throwable $e) {
         $either = Either::right($e);
         return new self($either);
      }
   }

   /**
    * A copy of flatMapData
    * Allows a function to map over the internal value, the function returns an SimpleResult
    *
    * _Notes:_
    *
    *  - `$alternativeFactory` must follow this interface `callable(TOkay):SimpleResult<UOkay, Throwable>`
    *  - Returns `SimpleResult<UOkay, Throwable>`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @template UOkay
    * @psalm-param callable(TOkay):SimpleResult<UOkay, Throwable> $mapFunc
    * @psalm-return SimpleResult<UOkay, Throwable>
    **/
   public function andThen(callable $mapFunc): self {
      return $this->flatMap($mapFunc);
   }

   /**
    * Allows a function to map over the internal value, the function returns an SimpleResult
    *
    * _Notes:_
    *
    *  - `$alternativeFactory` must follow this interface `callable(TOkay):SimpleResult<UOkay, Throwable>`
    *  - Returns `SimpleResult<UOkay, Throwable>`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @template UOkay
    * @psalm-param callable(TOkay):SimpleResult<UOkay, Throwable> $mapFunc
    * @psalm-return SimpleResult<UOkay, Throwable>
    **/
   public function flatMap(callable $mapFunc): self {
      /** @psalm-var callable(TOkay):Either<UOkay, Throwable> **/
      $realMap =
      /** @psalm-param TOkay $data */
      function ($data) use ($mapFunc): Either {
         $SimpleResult = $mapFunc($data);
         return $SimpleResult->either;
      };

      try {
         $either = $this->either->flatMapLeft($realMap);
         return new self($either);
      } catch (\Throwable $e) {
         $either = Either::right($e);
         return new self($either);
      }
   }

   /**
    * @psalm-mutation-free
    * @psalm-pure
    * @psalm-param Throwable $errorValue
    * @psalm-return SimpleResult<TOkay, Throwable>
    **/
   public function toError($errorValue): self {
      $either = $this->either->filterLeft(false, $errorValue);
      return new self($either);
   }

   /**
    * @psalm-mutation-free
    * @psalm-pure
    * @psalm-param TOkay $dataValue
    * @psalm-return SimpleResult<TOkay, Throwable>
    **/
   public function toOkay($dataValue): self {
      $either = $this->either->filterRight(false, $dataValue);
      return new self($either);
   }

   /**
    * Change the `SimpleResult::okay($value)` into `SimpleResult::error($errorValue)` iff `$filterFunc` returns false,
    * otherwise propigate the `SimpleResult::error()`
    *
    * _Notes:_
    *
    *  - `$filterFunc` must follow this interface `callable(TOkay):bool`
    *  - Returns `SimpleResult<TOkay, Throwable>`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @psalm-param callable(TOkay):bool $filterFunc
    * @psalm-param Throwable $errorValue
    * @psalm-return SimpleResult<TOkay, Throwable>
    **/
   public function toErrorIf(callable $filterFunc, Throwable $errorValue): self {
      try {
         $either = $this->either->filterLeftIf($filterFunc, $errorValue);
         return new self($either);
      } catch (\Throwable $e) {
         $either = Either::right($e);
         return new self($either);
      }
   }

   /**
    * Change the `SimpleResult::error($errorValue)` into `SimpleResult::okay($data)` iff `$filterFunc` returns false,
    * otherwise propigate the `SimpleResult::okay()`
    *
    * _Notes:_
    *
    *  - `$filterFunc` must follow this interface `callable(Throwable):bool`
    *  - Returns `SimpleResult<TOkay, Throwable>`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @psalm-param callable(Throwable):bool $filterFunc
    * @psalm-param TOkay $data
    * @psalm-return SimpleResult<TOkay, Throwable>
    **/
   public function toOkayIf(callable $filterFunc, $data): self {
      try {
         $either = $this->either->filterRightIf($filterFunc, $data);
         return new self($either);
      } catch (\Throwable $e) {
         $either = Either::right($e);
         return new self($either);
      }
   }

   /**
    * Turn an `SimpleResult::okay(null)` into an `SimpleResult::error($errorValue)` iff `is_null($value)`
    *
    * _Notes:_
    *
    *  - Returns `SimpleResult<TOkay, Throwable>`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @psalm-param string $errorValue
    * @psalm-return SimpleResult<TOkay, Throwable>
    **/
   public function notNull(string $errorValue): self {
      $ex = new Exception($errorValue);
      $either = $this->either->leftNotNull($ex);
      return new self($either);
   }

   /**
    * Turn an `SimpleResult::okay($value)` into an `SimpleResult::error($errorValue)` iff `!$value == true`
    *
    * _Notes:_
    *
    *  - Returns `SimpleResult<TOkay, Throwable>`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @psalm-param string $errorValue
    * @psalm-return SimpleResult<TOkay, Throwable>
    **/
   public function notFalsy(string $errorValue): self {
      $ex = new Exception($errorValue);
      $either = $this->either->leftNotFalsy($ex);
      return new self($either);
   }

   /**
    * Returns true if the SimpleResult's data == `$value`, otherwise false.
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @psalm-param mixed $value
    **/
   public function contains($value): bool {
      return $this->either->leftContains($value);
   }

   /**
    * Returns true if the SimpleResult's error == `$value`, otherwise false.
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @psalm-param mixed $value
    **/
   public function errorContains($value): bool {
      return $this->either->rightContains($value);
   }

    /**
    * Returns true if the `$existsFunc` returns true, otherwise false.
    *
    * _Notes:_
    *
    *  - `$filterFunc` must follow this interface `callable(TOkay):bool`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @psalm-param callable(TOkay):bool $existsFunc
    **/
   public function exists(callable $existsFunc): bool {
      return $this->either->existsLeft($existsFunc);
   }

   /**
    * Take a value, turn it a `SimpleResult::okay($data)` iff the `$filterFunc` returns true
    * otherwise an `SimpleResult::error($errorValue)`
    *
    * _Notes:_
    *
    *  - `$filterFunc` must follow this interface `callable(TOkay): bool`
    *  - Returns `SimpleResult<TOkay, Throwable>`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @psalm-param TOkay $data
    * @psalm-param string $reason
    * @psalm-param callable(TOkay): bool $filterFunc
    * @psalm-return SimpleResult<TOkay, Throwable>
    **/
   public static function okayWhen($data, string $reason, callable $filterFunc): self {
      try {
         $ex = new Exception($reason);
         $either = Either::leftWhen($data, $ex, $filterFunc);
         return new self($either);
      } catch (\Throwable $e) {
         $either = Either::right($e);
         return new self($either);
      }
   }

   /**
    * Take a value, turn it a `SimpleResult::error($errorValue)` iff the `$filterFunc` returns true
    * otherwise an `SimpleResult::okay($data)`
    *
    * _Notes:_
    *
    *  - `$filterFunc` must follow this interface `callable(TOkay): bool`
    *  - Returns `SimpleResult<TOkay, Throwable>`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @psalm-param TOkay $data
    * @psalm-param string $reason
    * @psalm-param callable(TOkay): bool $filterFunc
    * @psalm-return SimpleResult<TOkay, Throwable>
    **/
   public static function errorWhen($data, string $reason, callable $filterFunc): self {
      try {
         $ex = new Exception($reason);
         $either = Either::rightWhen($data, $ex, $filterFunc);
         return new self($either);
      } catch (\Throwable $e) {
         $either = Either::right($e);
         return new self($either);
      }
   }

   /**
    * Take a value, turn it a `SimpleResult::okay($data)` iff `!is_null($data)`, otherwise returns `SimpleResult::error($errorValue)`
    *
    * _Notes:_
    *
    * - Returns `SimpleResult<TOkay, Throwable>`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @psalm-param TOkay $data
    * @psalm-param string $reason
    * @psalm-return SimpleResult<TOkay, Throwable>
    **/
   public static function okayNotNull($data, string $reason): self {
      $ex = new Exception($reason);
      $either = Either::notNullLeft($data, $ex);
      return new self($either);
   }

   /**
    * Creates a SimpleResult if the `$key` exists in `$array`
    *
    * _Notes:_
    *
    * - Returns `SimpleResult<TOkay, Throwable>`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @psalm-param array<array-key, mixed> $array
    * @psalm-param array-key $key The key of the array
    * @psalm-param string $reason
    *  @psalm-return SimpleResult<TOkay, Throwable>
    **/
   public static function fromArray(array $array, $key, string $reason = null): self {
      $ex = $reason
         ? new Exception($reason)
         : new \Exception("SimpleResult could not grab $key from array. No reason given.");

      $either = Either::fromArray($array, $key, $ex);
      return new self($either);
   }

   /**
    * @psalm-suppress InvalidCast
    *
    * This is due to this class being a box.
    * I can't ensure the boxed value is stringable.
    * https://github.com/vimeo/psalm/issues/1982
    * @psalm-mutation-free
    * @psalm-pure
    */
    public function __toString() {
      $either = $this->either;

      /** @psalm-var TOkay|null **/
      $lv = $either->leftOr(null);

      /** @psalm-var TError **/
      $rv = $either->rightOr(new Exception("SimpleResult::error was not a SimpleResult::error?"));

      if ($either->isLeft()) {
         if ($lv === null) {
            return "Okay(null)";
         }
         return "Okay({$lv})";
      } else {
         return "Error({$rv->getMessage()})";
      }
   }
}
