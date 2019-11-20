<?php

declare(strict_types = 1);

namespace Optional;

use Optional\Either;

/**
 * @psalm-immutable
 * @template TOkay
 * @template TError
 */
class UnsafeResult {
   /** @var Either */
   private $either;

   /**
    * @param Either $either
    **/
    private function __construct(Either $either) {
      $this->either = $either;
   }

   /**
    * @psalm-mutation-free
    * @psalm-pure
    * @param TOkay $data
    * @return UnsafeResult<TOkay, TError>
    **/
   public static function okay($data): self {
      $either = Either::left($data);
      return new self($either);
   }

   /**
    * @psalm-mutation-free
    * @psalm-pure
    * @param TError $errorData
    * @return UnsafeResult<TOkay, TError>
    **/
   public static function error($errorData): self {
      $either = Either::right($errorData);
      return new self($either);
   }

   /**
    * Returns true iff the UnsafeResult is `UnsafeResult::okay`
    * @psalm-mutation-free
    * @psalm-pure
    **/
   public function isOkay(): bool {
      return $this->either->isLeft();
   }

   /**
    * Returns true iff the UnsafeResult is `UnsafeResult::error`
    * @psalm-mutation-free
    * @psalm-pure
    **/
   public function isError(): bool {
      return $this->either->isRight();
   }

   /**
    * Returns the UnsafeResult value or returns `$alternative`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @param TOkay $alternative
    * @return TOkay
    **/
   public function dataOr($alternative) {
      /** @var TOkay **/
      return $this->either->leftOr($alternative);
   }

   /**
    * Returns the UnsafeResult erro value or returns `$alternative`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @param TError $alternative
    * @return TError
    **/
   public function errorOr($alternative) {
      /** @var TError **/
      return $this->either->rightOr($alternative);
   }

   /**
    * Returns a `UnsafeResult::okay($data)` iff the UnsafeResult orginally was `UnsafeResult::error($errorValue)`
    *
    * _Notes:_
    *
    *  - Returns `UnsafeResult<TOkay, TError>`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @param TOkay $data
    * @return UnsafeResult<TOkay, TError>
    **/
   public function orSetDataTo($data): self {
      $either = $this->either->orLeft($data);
      return new self($either);
   }

   /**
    * Returns the UnsafeResult's value or calls `$alternativeFactory` and returns the value of that function
    *
    * _Notes:_
    *
    *  - `$alternativeFactory` must follow this interface `callable(TError):TOkay`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @param callable(TError):TOkay $alternativeFactory
    * @return TOkay
    **/
   public function dataOrReturn(callable $alternativeFactory) {
      /** @var TOkay **/
      return $this->either->leftOrCreate($alternativeFactory);
   }

   /**
    * Returns a `UnsafeResult::okay($value)` iff the the UnsafeResult orginally was `UnsafeResult::error($errorValue)`
    *
    * The `$alternativeFactory` is called lazily - iff the UnsafeResult orginally was `UnsafeResult::error($errorValue)`
    *
    * _Notes:_
    *
    *  - `$alternativeFactory` must follow this interface `callable(TError):TOkay`
    *  - Returns `UnsafeResult<TOkay, TError>`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @param callable(TError):TOkay $alternativeFactory
    * @return UnsafeResult<TOkay, TError>
    **/
   public function orCreateResultWithData(callable $alternativeFactory): self {
      $either = $this->either->orCreateLeft($alternativeFactory);
      return new self($either);
   }

   /**
    * iff `UnsafeResult::error($errorValue)` return `$alternativeUnsafeResult`, otherwise return the original `$uResult`
    *
    * _Notes:_
    *
    *  - `$alternativeUnsafeResult` must be of type `UnsafeResult<TOkay, TError>`
    *  - Returns `UnsafeResult<TOkay, TError>`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @param UnsafeResult<TOkay, TError> $alternativeUnsafeResult
    * @return UnsafeResult<TOkay, TError>
    **/
   public function  okayOr(self $alternativeUnsafeResult): self {
      $either = $this->either->elseLeft($alternativeUnsafeResult->either);
      return new self($either);
   }

   /**
    * iff `UnsafeResult::error` return the `UnsafeResult` returned by `$alternativeUnsafeResultFactory`, otherwise return the orginal `$uResult`
    *
    * `$alternativeUnsafeResultFactory` is run lazily
    *
    * _Notes:_
    *
    *  - `$alternativeUnsafeResultFactory` must be of type `callable(TError):UnsafeResult<TOkay, TError> `
    *  - Returns `UnsafeResult<TOkay, TError>`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @param callable(TError):UnsafeResult<TOkay, TError> $alternativeUnsafeResultFactory
    * @return UnsafeResult<TOkay, TError>
    **/
   public function createIfError(callable $alternativeUnsafeResultFactory): self {
      /** @var callable(TOkay):Either<TOkay, TError> **/
      $realFactory =
      /** @param TError $errorValue */
      function ($errorValue) use ($alternativeUnsafeResultFactory): Either {
         $uResult = $alternativeUnsafeResultFactory($errorValue);
         return $uResult->either;
      };

      $either = $this->either->elseCreateLeft($realFactory);
      return new self($either);
   }

   /**
    * Runs only 1 function:
    *
    *  - `$dataFunc` iff the UnsafeResult is `UnsafeResult::okay`
    *  - `$errorFunc` iff the UnsafeResult is `UnsafeResult::error`
    *
    * _Notes:_
    *
    *  - `$dataFunc` must follow this interface `callable(TOkay):U`
    *  - `$errorFunc` must follow this interface `callable(TError):U`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @template U
    * @param callable(TOkay):U $dataFunc
    * @param callable(TError):U $errorFunc
    * @return U
    **/
   public function run(callable $dataFunc, callable $errorFunc) {
      return $this->either->match($dataFunc, $errorFunc);
   }

   /**
    * Side effect function: Runs the function iff the UnsafeResult is `UnsafeResult::okay`
    *
    * _Notes:_
    *
    *  - `$dataFunc` must follow this interface `callable(TOkay):U`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @param callable(TOkay) $dataFunc
    **/
   public function runOnOkay(callable $dataFunc): void {
      $this->either->matchLeft($dataFunc);
   }

   /**
    * Side effect function: Runs the function iff the UnsafeResult is `UnsafeResult::error`
    *
    * _Notes:_
    *
    *  - `$errorFunc` must follow this interface `callable(TError):U`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @param callable(TError) $errorFunc
    **/
   public function runOnError(callable $errorFunc): void {
      $this->either->matchRight($errorFunc);
   }

   /**
    * Maps the `$value` of a `UnsafeResult::okay($value)`
    *
    * The `map` function runs iff the UnsafeResult is a `UnsafeResult::okay`
    * Otherwise the `UnsafeResult:error($errorValue)` is propagated
    *
    * _Notes:_
    *
    *  - `$mapFunc` must follow this interface `callable(TOkay):UOkay`
    *  - Returns `UnsafeResult<UOkay, TError>`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @template UOkay
    * @param callable(TOkay):UOkay $mapFunc
    * @return UnsafeResult<UOkay, TError>
    **/
   public function map(callable $mapFunc): self {
      $either = $this->either->mapLeft($mapFunc);
      return new self($either);
   }

   /**
    * `map`, but if an exception occurs, return `UnsafeResult::error(exception)`
    *
    * The `map` function runs iff the UnsafeResult is a `UnsafeResult::okay`
    * Otherwise the `UnsafeResult:error($errorValue)` is propagated
    *
    * _Notes:_
    *
    *  - `$mapFunc` must follow this interface `callable(TOkay):UOkay`
    *  - Returns `UnsafeResult<UOkay, TError>`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @template UOkay
    * @param callable(TOkay):UOkay $mapFunc
    * @return UnsafeResult<UOkay, TError>
    **/
   public function mapSafely(callable $mapFunc): self {
      $either = $this->either->mapLeftSafely($mapFunc);
      return new self($either);
   }

   /**
    * Maps the `$value` of a `UnsafeResult::error($errorValue)`
    *
    * The `map` function runs iff the UnsafeResult is a `UnsafeResult::error`
    * Otherwise the `UnsafeResult:okay($data)` is propagated
    *
    * _Notes:_
    *
    *  - `$mapFunc` must follow this interface `callable(TError):UError`
    *  - Returns `UnsafeResult<TOkay, UError>`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @template UError
    * @param callable(TError):UError $mapFunc
    * @return UnsafeResult<TOkay, UError>
    **/
   public function mapError(callable $mapFunc): self {
      $either = $this->either->mapRight($mapFunc);
      return new self($either);
   }

   /**
    * A copy of flatMapData
    * Allows a function to map over the internal value, the function returns an UnsafeResult
    *
    * _Notes:_
    *
    *  - `$alternativeFactory` must follow this interface `callable(TOkay):UnsafeResult<UOkay, TError>`
    *  - Returns `UnsafeResult<UOkay, TError>`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @template UOkay
    * @param callable(TOkay):UnsafeResult<UOkay, TError> $mapFunc
    * @return UnsafeResult<UOkay, TError>
    **/
   public function andThen(callable $mapFunc): self {
      return $this->flatMap($mapFunc);
   }

   /**
    * Allows a function to map over the internal value, the function returns an UnsafeResult
    *
    * _Notes:_
    *
    *  - `$alternativeFactory` must follow this interface `callable(TOkay):UnsafeResult<UOkay, TError>`
    *  - Returns `UnsafeResult<UOkay, TError>`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @template UOkay
    * @param callable(TOkay):UnsafeResult<UOkay, TError> $mapFunc
    * @return UnsafeResult<UOkay, TError>
    **/
   public function flatMap(callable $mapFunc): self {
      /** @var callable(TOkay):Either<UOkay, TError> **/
      $realMap =
      /** @param TOkay $data */
      function ($data) use ($mapFunc): Either {
         $uResult = $mapFunc($data);
         return $uResult->either;
      };

      $either = $this->either->flatMapLeft($realMap);
      return new self($either);
   }

   /**
    * @psalm-mutation-free
    * @psalm-pure
    * @param TError $errorValue
    * @return UnsafeResult<TOkay, TError>
    **/
   public function toError($errorValue): self {
      $either = $this->either->filterLeft(false, $errorValue);
      return new self($either);
   }

   /**
    * @psalm-mutation-free
    * @psalm-pure
    * @param TOkay $dataValue
    * @return UnsafeResult<TOkay, TError>
    **/
   public function toOkay($dataValue): self {
      $either = $this->either->filterRight(false, $dataValue);
      return new self($either);
   }

   /**
    * Change the `UnsafeResult::okay($value)` into `UnsafeResult::error($errorValue)` iff `$filterFunc` returns false,
    * otherwise propigate the `UnsafeResult::error()`
    *
    * _Notes:_
    *
    *  - `$filterFunc` must follow this interface `callable(TOkay):bool`
    *  - Returns `UnsafeResult<TOkay, TError>`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @param callable(TOkay):bool $filterFunc
    * @param TError $errorValue
    * @return UnsafeResult<TOkay, TError>
    **/
   public function toErrorIf(callable $filterFunc, $errorValue): self {
      $either = $this->either->filterLeftIf($filterFunc, $errorValue);
      return new self($either);
   }

   /**
    * Change the `UnsafeResult::error($errorValue)` into `UnsafeResult::okay($data)` iff `$filterFunc` returns false,
    * otherwise propigate the `UnsafeResult::okay()`
    *
    * _Notes:_
    *
    *  - `$filterFunc` must follow this interface `callable(TError):bool`
    *  - Returns `UnsafeResult<TOkay, TError>`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @param callable(TError):bool $filterFunc
    * @param TOkay $data
    * @return UnsafeResult<TOkay, TError>
    **/
   public function toOkayIf(callable $filterFunc, $data): self {
      $either = $this->either->filterRightIf($filterFunc, $data);
      return new self($either);
   }

   /**
    * Turn an `UnsafeResult::okay(null)` into an `UnsafeResult::error($errorValue)` iff `is_null($value)`
    *
    * _Notes:_
    *
    *  - Returns `UnsafeResult<TOkay, TError>`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @param TError $errorValue
    * @return UnsafeResult<TOkay, TError>
    **/
   public function notNull($errorValue): self {
      $either = $this->either->leftNotNull($errorValue);
      return new self($either);
   }

   /**
    * Turn an `UnsafeResult::okay($value)` into an `UnsafeResult::error($errorValue)` iff `!$value == true`
    *
    * _Notes:_
    *
    *  - Returns `UnsafeResult<TOkay, TError>`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @param TError $errorValue
    * @return UnsafeResult<TOkay, TError>
    **/
   public function notFalsy($errorValue): self {
      $either = $this->either->leftNotFalsy($errorValue);
      return new self($either);
   }

   /**
    * Returns true if the UnsafeResult's data == `$value`, otherwise false.
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @param mixed $value
    **/
   public function contains($value): bool {
      return $this->either->leftContains($value);
   }

   /**
    * Returns true if the UnsafeResult's error == `$value`, otherwise false.
    *
    * @psalm-mutation-free
    * @psalm-pure
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
    * @psalm-mutation-free
    * @psalm-pure
    * @param callable(TOkay):bool $existsFunc
    **/
   public function exists(callable $existsFunc): bool {
      return $this->either->existsLeft($existsFunc);
   }

   /**
    * Take a value, turn it a `UnsafeResult::okay($data)` iff the `$filterFunc` returns true
    * otherwise an `UnsafeResult::error($errorValue)`
    *
    * _Notes:_
    *
    *  - `$filterFunc` must follow this interface `callable(TOkay): bool`
    *  - Returns `UnsafeResult<TOkay, TError>`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @param TOkay $data
    * @param TError $errorValue
    * @param callable(TOkay): bool $filterFunc
    * @return UnsafeResult<TOkay, TError>
    **/
   public static function okayWhen($data, $errorValue, callable $filterFunc): self {
      $either = Either::leftWhen($data, $errorValue, $filterFunc);
      return new self($either);
   }

   /**
    * Take a value, turn it a `UnsafeResult::error($errorValue)` iff the `$filterFunc` returns true
    * otherwise an `UnsafeResult::okay($data)`
    *
    * _Notes:_
    *
    *  - `$filterFunc` must follow this interface `callable(TOkay): bool`
    *  - Returns `UnsafeResult<TOkay, TError>`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @param TOkay $data
    * @param TError $errorValue
    * @param callable(TOkay): bool $filterFunc
    * @return UnsafeResult<TOkay, TError>
    **/
   public static function errorWhen($data, $errorValue, callable $filterFunc): self {
      $either = Either::rightWhen($data, $errorValue, $filterFunc);
      return new self($either);
   }

   /**
    * Take a value, turn it a `UnsafeResult::okay($data)` iff `!is_null($data)`, otherwise returns `UnsafeResult::error($errorValue)`
    *
    * _Notes:_
    *
    * - Returns `UnsafeResult<TOkay, TError>`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @param TOkay $data
    * @param TError $errorValue
    * @return UnsafeResult<TOkay, TError>
    **/
   public static function okayNotNull($data, $errorValue): self {
      $either = Either::notNullLeft($data, $errorValue);
      return new self($either);
   }

   /**
    * Creates a UnsafeResult if the `$key` exists in `$array`
    *
    * _Notes:_
    *
    * - Returns `UnsafeResult<TOkay, TError>`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @param array<array-key, mixed> $array
    * @param array-key $key The key of the array
    * @param TError $rightValue
    *  @return UnsafeResult<TOkay, TError>
    **/
   public static function fromArray(array $array, $key, $rightValue = null): self {
      $either = Either::fromArray($array, $key, $rightValue);
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

      /** @var TOkay|null **/
      $lv = $either->leftOr(null);

      /** @var TError|null **/
      $rv = $either->rightOr(null);

      if ($either->isLeft()) {
         if ($lv === null) {
            return "Okay(null)";
         }
         return "Okay({$lv})";
      } else {
         if ($rv === null) {
            return "Error(null)";
         }
         return "Error({$rv})";
      }
   }
}
