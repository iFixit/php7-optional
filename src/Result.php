<?php

declare(strict_types = 1);

namespace Optional;

use Optional\Either;

/**
 * @template TOkay
 * @template TError
 */
class Result {
   /** @var Either */
   private $either;

   /**
    * @param Either $either
    **/
    private function __construct(Either $either) {
      $this->either = $either;
   }

   /**
    * @param TOkay $data
    * @return Result<TOkay, TError>
    **/
   public static function okay($data): self {
      $either = Either::left($data);
      return new self($either);
   }

   /**
    * @param TError $errorData
    * @return Result<TOkay, TError>
    **/
   public static function error($errorData): self {
      $either = Either::right($errorData);
      return new self($either);
   }

   /**
    * Returns true iff the Result is `Result::okay`
    **/
   public function isOkay(): bool {
      return $this->either->isLeft();
   }

   /**
    * Returns true iff the Result is `Result::error`
    **/
   public function isError(): bool {
      return $this->either->isRight();
   }

   /**
    * Returns the Result value or returns `$alternative`
    *
    * @param TOkay $alternative
    * @return TOkay
    **/
   public function dataOr($alternative) {
      /** @var TOkay **/
      return $this->either->leftOr($alternative);
   }

   /**
    * Returns the Result erro value or returns `$alternative`
    *
    * @param TError $alternative
    * @return TError
    **/
   public function errorOr($alternative) {
      /** @var TError **/
      return $this->either->rightOr($alternative);
   }

   /**
    * Returns a `Result::okay($data)` iff the Result orginally was `Result::error($errorValue)`
    *
    * _Notes:_
    *
    *  - Returns `Result<TOkay, TError>`
    *
    * @param TOkay $data
    * @return Result<TOkay, TError>
    **/
   public function orSetDataTo($data): self {
      $either = $this->either->orLeft($data);
      return new self($either);
   }

   /**
    * Returns the Result's value or calls `$alternativeFactory` and returns the value of that function
    *
    * _Notes:_
    *
    *  - `$alternativeFactory` must follow this interface `callable(TError):TOkay`
    *
    * @param callable(TError):TOkay $alternativeFactory
    * @return TOkay
    **/
   public function dataOrReturn(callable $alternativeFactory) {
      /** @var TOkay **/
      return $this->either->leftOrCreate($alternativeFactory);
   }

   /**
    * Returns a `Result::okay($value)` iff the the Result orginally was `Result::error($errorValue)`
    *
    * The `$alternativeFactory` is called lazily - iff the Result orginally was `Result::error($errorValue)`
    *
    * _Notes:_
    *
    *  - `$alternativeFactory` must follow this interface `callable(TError):TOkay`
    *  - Returns `Result<TOkay, TError>`
    *
    * @param callable(TError):TOkay $alternativeFactory
    * @return Result<TOkay, TError>
    **/
   public function orCreateResultWithData(callable $alternativeFactory): self {
      $either = $this->either->orCreateLeft($alternativeFactory);
      return new self($either);
   }

   /**
    * iff `Result::error($errorValue)` return `$alternativeResult`, otherwise return the original `$result`
    *
    * _Notes:_
    *
    *  - `$alternativeResult` must be of type `Result<TOkay, TError>`
    *  - Returns `Result<TOkay, TError>`
    *
    * @param Result<TOkay, TError> $alternativeResult
    * @return Result<TOkay, TError>
    **/
   public function  okayOr(self $alternativeResult): self {
      $either = $this->either->elseLeft($alternativeResult->either);
      return new self($either);
   }

   /**
    * iff `Result::error` return the `Result` returned by `$alternativeResultFactory`, otherwise return the orginal `$result`
    *
    * `$alternativeResultFactory` is run lazily
    *
    * _Notes:_
    *
    *  - `$alternativeResultFactory` must be of type `callable(TError):Result<TOkay, TError> `
    *  - Returns `Result<TOkay, TError>`
    *
    * @param callable(TError):Result<TOkay, TError> $alternativeResultFactory
    * @return Result<TOkay, TError>
    **/
   public function createIfError(callable $alternativeResultFactory): self {
      /** @var callable(TOkay):Either<TOkay, TError> **/
      $realFactory =
      /** @param TError $errorValue */
      function ($errorValue) use ($alternativeResultFactory): Either {
         $result = $alternativeResultFactory($errorValue);
         return $result->either;
      };

      $either = $this->either->elseCreateLeft($realFactory);
      return new self($either);
   }

   /**
    * Runs only 1 function:
    *
    *  - `$dataFunc` iff the Result is `Result::okay`
    *  - `$errorFunc` iff the Result is `Result::error`
    *
    * _Notes:_
    *
    *  - `$dataFunc` must follow this interface `callable(TOkay):U`
    *  - `$errorFunc` must follow this interface `callable(TError):U`
    *
    * @template U
    * @param callable(TOkay):U $dataFunc
    * @param callable(TError):U $errorFunc
    * @return U
    **/
   public function run(callable $dataFunc, callable $errorFunc) {
      return $this->either->match($dataFunc, $errorFunc);
   }

   /**
    * Side effect function: Runs the function iff the Result is `Result::okay`
    *
    * _Notes:_
    *
    *  - `$dataFunc` must follow this interface `callable(TOkay):U`
    *
    * @param callable(TOkay) $dataFunc
    **/
   public function runOnOkay(callable $dataFunc): void {
      $this->either->matchLeft($dataFunc);
   }

   /**
    * Side effect function: Runs the function iff the Result is `Result::error`
    *
    * _Notes:_
    *
    *  - `$errorFunc` must follow this interface `callable(TError):U`
    *
    * @param callable(TError) $errorFunc
    **/
   public function runOnError(callable $errorFunc): void {
      $this->either->matchRight($errorFunc);
   }

   /**
    * Maps the `$value` of a `Result::okay($value)`
    *
    * The `map` function runs iff the Result is a `Result::okay`
    * Otherwise the `Result:error($errorValue)` is propagated
    *
    * _Notes:_
    *
    *  - `$mapFunc` must follow this interface `callable(TOkay):UOkay`
    *  - Returns `Result<UOkay, TError>`
    *
    * @template UOkay
    * @param callable(TOkay):UOkay $mapFunc
    * @return Result<UOkay, TError>
    **/
   public function map(callable $mapFunc): self {
      $either = $this->either->mapLeft($mapFunc);
      return new self($either);
   }

   /**
    * `map`, but if an exception occurs, return `Result::error(exception)`
    *
    * The `map` function runs iff the Result is a `Result::okay`
    * Otherwise the `Result:error($errorValue)` is propagated
    *
    * _Notes:_
    *
    *  - `$mapFunc` must follow this interface `callable(TOkay):UOkay`
    *  - Returns `Result<UOkay, TError>`
    *
    * @template UOkay
    * @param callable(TOkay):UOkay $mapFunc
    * @return Result<UOkay, TError>
    **/
   public function mapSafely(callable $mapFunc): self {
      $either = $this->either->mapLeftSafely($mapFunc);
      return new self($either);
   }

   /**
    * Maps the `$value` of a `Result::error($errorValue)`
    *
    * The `map` function runs iff the Result is a `Result::error`
    * Otherwise the `Result:okay($data)` is propagated
    *
    * _Notes:_
    *
    *  - `$mapFunc` must follow this interface `callable(TError):UError`
    *  - Returns `Result<TOkay, UError>`
    *
    * @template UError
    * @param callable(TError):UError $mapFunc
    * @return Result<TOkay, UError>
    **/
   public function mapError(callable $mapFunc): self {
      $either = $this->either->mapRight($mapFunc);
      return new self($either);
   }

   /**
    * A copy of flatMapData
    * Allows a function to map over the internal value, the function returns an Result
    *
    * _Notes:_
    *
    *  - `$alternativeFactory` must follow this interface `callable(TOkay):Result<UOkay, TError>`
    *  - Returns `Result<UOkay, TError>`
    *
    * @template UOkay
    * @param callable(TOkay):Result<UOkay, TError> $mapFunc
    * @return Result<UOkay, TError>
    **/
   public function andThen(callable $mapFunc): self {
      return $this->flatMap($mapFunc);
   }

   /**
    * Allows a function to map over the internal value, the function returns an Result
    *
    * _Notes:_
    *
    *  - `$alternativeFactory` must follow this interface `callable(TOkay):Result<UOkay, TError>`
    *  - Returns `Result<UOkay, TError>`
    *
    * @template UOkay
    * @param callable(TOkay):Result<UOkay, TError> $mapFunc
    * @return Result<UOkay, TError>
    **/
   public function flatMap(callable $mapFunc): self {
      /** @var callable(TOkay):Either<UOkay, TError> **/
      $realMap =
      /** @param TOkay $data */
      function ($data) use ($mapFunc): Either {
         $result = $mapFunc($data);
         return $result->either;
      };

      $either = $this->either->flatMapLeft($realMap);
      return new self($either);
   }

   /**
    * @param TError $errorValue
    * @return Result<TOkay, TError>
    **/
   public function toError($errorValue): self {
      $either = $this->either->filterLeft(false, $errorValue);
      return new self($either);
   }

   /**
    * @param TOkay $dataValue
    * @return Result<TOkay, TError>
    **/
   public function toOkay($dataValue): self {
      $either = $this->either->filterRight(false, $dataValue);
      return new self($either);
   }

   /**
    * Change the `Result::okay($value)` into `Result::error($errorValue)` iff `$filterFunc` returns false,
    * otherwise propigate the `Result::error()`
    *
    * _Notes:_
    *
    *  - `$filterFunc` must follow this interface `callable(TOkay):bool`
    *  - Returns `Result<TOkay, TError>`
    *
    * @param callable(TOkay):bool $filterFunc
    * @param TError $errorValue
    * @return Result<TOkay, TError>
    **/
   public function toErrorIf(callable $filterFunc, $errorValue): self {
      $either = $this->either->filterLeftIf($filterFunc, $errorValue);
      return new self($either);
   }

   /**
    * Change the `Result::error($errorValue)` into `Result::okay($data)` iff `$filterFunc` returns false,
    * otherwise propigate the `Result::okay()`
    *
    * _Notes:_
    *
    *  - `$filterFunc` must follow this interface `callable(TError):bool`
    *  - Returns `Result<TOkay, TError>`
    *
    * @param callable(TError):bool $filterFunc
    * @param TOkay $data
    * @return Result<TOkay, TError>
    **/
   public function toOkayIf(callable $filterFunc, $data): self {
      $either = $this->either->filterRightIf($filterFunc, $data);
      return new self($either);
   }

   /**
    * Turn an `Result::okay(null)` into an `Result::error($errorValue)` iff `is_null($value)`
    *
    * _Notes:_
    *
    *  - Returns `Result<TOkay, TError>`
    *
    * @param TError $errorValue
    * @return Result<TOkay, TError>
    **/
   public function notNull($errorValue): self {
      $either = $this->either->leftNotNull($errorValue);
      return new self($either);
   }

   /**
    * Turn an `Result::okay($value)` into an `Result::error($errorValue)` iff `!$value == true`
    *
    * _Notes:_
    *
    *  - Returns `Result<TOkay, TError>`
    *
    * @param TError $errorValue
    * @return Result<TOkay, TError>
    **/
   public function notFalsy($errorValue): self {
      $either = $this->either->leftNotFalsy($errorValue);
      return new self($either);
   }

   /**
    * Returns true if the Result's data == `$value`, otherwise false.
    *
    * @param mixed $value
    **/
   public function contains($value): bool {
      return $this->either->leftContains($value);
   }

   /**
    * Returns true if the Result's error == `$value`, otherwise false.
    *
    * @param mixed $value
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
    * @param callable(TOkay):bool $existsFunc
    **/
   public function exists(callable $existsFunc): bool {
      return $this->either->existsLeft($existsFunc);
   }

   /**
    * Take a value, turn it a `Result::okay($data)` iff the `$filterFunc` returns true
    * otherwise an `Result::error($errorValue)`
    *
    * _Notes:_
    *
    *  - `$filterFunc` must follow this interface `callable(TOkay): bool`
    *  - Returns `Result<TOkay, TError>`
    *
    * @param TOkay $data
    * @param TError $errorValue
    * @param callable(TOkay): bool $filterFunc
    * @return Result<TOkay, TError>
    **/
   public static function okayWhen($data, $errorValue, callable $filterFunc): self {
      $either = Either::leftWhen($data, $errorValue, $filterFunc);
      return new self($either);
   }

   /**
    * Take a value, turn it a `Result::error($errorValue)` iff the `$filterFunc` returns true
    * otherwise an `Result::okay($data)`
    *
    * _Notes:_
    *
    *  - `$filterFunc` must follow this interface `callable(TOkay): bool`
    *  - Returns `Result<TOkay, TError>`
    *
    * @param TOkay $data
    * @param TError $errorValue
    * @param callable(TOkay): bool $filterFunc
    * @return Result<TOkay, TError>
    **/
   public static function errorWhen($data, $errorValue, callable $filterFunc): self {
      $either = Either::rightWhen($data, $errorValue, $filterFunc);
      return new self($either);
   }

   /**
    * Take a value, turn it a `Result::okay($data)` iff `!is_null($data)`, otherwise returns `Result::error($errorValue)`
    *
    * _Notes:_
    *
    * - Returns `Result<TOkay, TError>`
    *
    * @param TOkay $data
    * @param TError $errorValue
    * @return Result<TOkay, TError>
    **/
   public static function okayNotNull($data, $errorValue): self {
      $either = Either::notNullLeft($data, $errorValue);
      return new self($either);
   }

   /**
    * Creates a Result if the `$key` exists in `$array`
    *
    * _Notes:_
    *
    * - Returns `Result<TOkay, TError>`
    *
    * @param array<array-key, mixed> $array
    * @param array-key $key The key of the array
    * @param TError $rightValue
    *  @return Result<TOkay, TError>
    **/
   public static function fromArray(array $array, $key, $rightValue = null): self {
      $either = Either::fromArray($array, $key, $rightValue);
      return new self($either);
   }
}
