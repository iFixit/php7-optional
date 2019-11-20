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
    * @psalm-mutation-free
    * @psalm-pure
    * @param TOkay $data
    * @return Result<TOkay, Throwable>
    **/
   public static function okay($data): self {
      $either = Either::left($data);
      return new self($either);
   }

   /**
    * @psalm-mutation-free
    * @psalm-pure
    * @param Throwable $errorData
    * @return Result<TOkay, Throwable>
    **/
   public static function error(Throwable $errorData): self {
      $either = Either::right($errorData);
      return new self($either);
   }

   /**
    * Returns true iff the Result is `Result::okay`
    * @psalm-mutation-free
    * @psalm-pure
    **/
   public function isOkay(): bool {
      return $this->either->isLeft();
   }

   /**
    * Returns true iff the Result is `Result::error`
    * @psalm-mutation-free
    * @psalm-pure
    **/
   public function isError(): bool {
      return $this->either->isRight();
   }

   /**
    * Returns the Result value or throws the current error
    * @psalm-mutation-free
    * @psalm-pure
    *
    * @return TOkay
    **/
   public function dataOrThrow() {
      if ($this->either->isLeft()) {
         /** @var TOkay **/
         return $this->either->leftOr(null);
      } else {
         /** @var TError **/
         $ex = $this->either->rightOr(null);
         throw new Exception($ex->getMessage(), (int)$ex->getCode(), $ex);
      }
   }

   /**
    * Returns a `Result::okay($data)` iff the Result orginally was `Result::error($errorValue)`
    *
    * _Notes:_
    *
    *  - Returns `Result<TOkay, Throwable>`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @param TOkay $data
    * @return Result<TOkay, Throwable>
    **/
   public function orSetDataTo($data): self {
      $either = $this->either->orLeft($data);
      return new self($either);
   }

   /**
    * Returns a `Result::okay($value)` iff the the Result orginally was `Result::error($errorValue)`
    *
    * The `$alternativeFactory` is called lazily - iff the Result orginally was `Result::error($errorValue)`
    *
    * _Notes:_
    *
    *  - `$alternativeFactory` must follow this interface `callable(Throwable):TOkay`
    *  - Returns `Result<TOkay, Throwable>`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @param callable(Throwable):TOkay $alternativeFactory
    * @return Result<TOkay, Throwable>
    **/
   public function orCreateResultWithData(callable $alternativeFactory): self {
      try {
         $either = $this->either->orCreateLeft($alternativeFactory);
         return new self($either);
      } catch (\Throwable $e) {
         $either = Either::right($e);
         return new self($either);
      }
   }

   /**
    * iff `Result::error($errorValue)` return `$alternativeResult`, otherwise return the original `$result`
    *
    * _Notes:_
    *
    *  - `$alternativeResult` must be of type `Result<TOkay, Throwable>`
    *  - Returns `Result<TOkay, Throwable>`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @param Result<TOkay, Throwable> $alternativeResult
    * @return Result<TOkay, Throwable>
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
    *  - `$alternativeResultFactory` must be of type `callable(Throwable):Result<TOkay, Throwable> `
    *  - Returns `Result<TOkay, Throwable>`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @param callable(Throwable):Result<TOkay, Throwable> $alternativeResultFactory
    * @return Result<TOkay, Throwable>
    **/
   public function createIfError(callable $alternativeResultFactory): self {
      /** @var callable(TOkay):Either<TOkay, Throwable> **/
      $realFactory =
      /** @param Throwable $errorValue */
      function (Throwable $errorValue) use ($alternativeResultFactory): Either {
         $result = $alternativeResultFactory($errorValue);
         return $result->either;
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
    *  - `$dataFunc` iff the Result is `Result::okay`
    *  - `$errorFunc` iff the Result is `Result::error`
    *
    * _Notes:_
    *
    *  - `$dataFunc` must follow this interface `callable(TOkay):U`
    *  - `$errorFunc` must follow this interface `callable(Throwable):U`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @template U
    * @param callable(TOkay):U $dataFunc
    * @param callable(Throwable):U $errorFunc
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
    * @psalm-mutation-free
    * @psalm-pure
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
    *  - `$errorFunc` must follow this interface `callable(Throwable):U`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @param callable(Throwable) $errorFunc
    **/
   public function runOnError(callable $errorFunc): void {
      $this->either->matchRight($errorFunc);
   }

   /**
    * `map`, but if an Throwable occurs, return `Result::error(Throwable)`
    *
    * The `map` function runs iff the Result is a `Result::okay`
    * Otherwise the `Result:error($errorValue)` is propagated
    *
    * _Notes:_
    *
    *  - `$mapFunc` must follow this interface `callable(TOkay):UOkay`
    *  - Returns `Result<UOkay, Throwable>`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @template UOkay
    * @param callable(TOkay):UOkay $mapFunc
    * @return Result<UOkay, Throwable>
    **/
   public function map(callable $mapFunc): self {
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
    *  - `$mapFunc` must follow this interface `callable(Throwable):Throwable`
    *  - Returns `Result<TOkay, Throwable>`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @param callable(Throwable):Throwable $mapFunc
    * @return Result<TOkay, Throwable>
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
    * Allows a function to map over the internal value, the function returns an Result
    *
    * _Notes:_
    *
    *  - `$alternativeFactory` must follow this interface `callable(TOkay):Result<UOkay, Throwable>`
    *  - Returns `Result<UOkay, Throwable>`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @template UOkay
    * @param callable(TOkay):Result<UOkay, Throwable> $mapFunc
    * @return Result<UOkay, Throwable>
    **/
   public function andThen(callable $mapFunc): self {
      return $this->flatMap($mapFunc);
   }

   /**
    * Allows a function to map over the internal value, the function returns an Result
    *
    * _Notes:_
    *
    *  - `$alternativeFactory` must follow this interface `callable(TOkay):Result<UOkay, Throwable>`
    *  - Returns `Result<UOkay, Throwable>`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @template UOkay
    * @param callable(TOkay):Result<UOkay, Throwable> $mapFunc
    * @return Result<UOkay, Throwable>
    **/
   public function flatMap(callable $mapFunc): self {
      /** @var callable(TOkay):Either<UOkay, Throwable> **/
      $realMap =
      /** @param TOkay $data */
      function ($data) use ($mapFunc): Either {
         $result = $mapFunc($data);
         return $result->either;
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
    * @param Throwable $errorValue
    * @return Result<TOkay, Throwable>
    **/
   public function toError($errorValue): self {
      $either = $this->either->filterLeft(false, $errorValue);
      return new self($either);
   }

   /**
    * @psalm-mutation-free
    * @psalm-pure
    * @param TOkay $dataValue
    * @return Result<TOkay, Throwable>
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
    *  - Returns `Result<TOkay, Throwable>`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @param callable(TOkay):bool $filterFunc
    * @param Throwable $errorValue
    * @return Result<TOkay, Throwable>
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
    * Change the `Result::error($errorValue)` into `Result::okay($data)` iff `$filterFunc` returns false,
    * otherwise propigate the `Result::okay()`
    *
    * _Notes:_
    *
    *  - `$filterFunc` must follow this interface `callable(Throwable):bool`
    *  - Returns `Result<TOkay, Throwable>`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @param callable(Throwable):bool $filterFunc
    * @param TOkay $data
    * @return Result<TOkay, Throwable>
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
    * Turn an `Result::okay(null)` into an `Result::error($errorValue)` iff `is_null($value)`
    *
    * _Notes:_
    *
    *  - Returns `Result<TOkay, Throwable>`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @param string $errorValue
    * @return Result<TOkay, Throwable>
    **/
   public function notNull(string $errorValue): self {
      $ex = new Exception($errorValue);
      $either = $this->either->leftNotNull($ex);
      return new self($either);
   }

   /**
    * Turn an `Result::okay($value)` into an `Result::error($errorValue)` iff `!$value == true`
    *
    * _Notes:_
    *
    *  - Returns `Result<TOkay, Throwable>`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @param string $errorValue
    * @return Result<TOkay, Throwable>
    **/
   public function notFalsy(string $errorValue): self {
      $ex = new Exception($errorValue);
      $either = $this->either->leftNotFalsy($ex);
      return new self($either);
   }

   /**
    * Returns true if the Result's data == `$value`, otherwise false.
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @param mixed $value
    **/
   public function contains($value): bool {
      return $this->either->leftContains($value);
   }

   /**
    * Returns true if the Result's error == `$value`, otherwise false.
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
    * Take a value, turn it a `Result::okay($data)` iff the `$filterFunc` returns true
    * otherwise an `Result::error($errorValue)`
    *
    * _Notes:_
    *
    *  - `$filterFunc` must follow this interface `callable(TOkay): bool`
    *  - Returns `Result<TOkay, Throwable>`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @param TOkay $data
    * @param string $reason
    * @param callable(TOkay): bool $filterFunc
    * @return Result<TOkay, Throwable>
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
    * Take a value, turn it a `Result::error($errorValue)` iff the `$filterFunc` returns true
    * otherwise an `Result::okay($data)`
    *
    * _Notes:_
    *
    *  - `$filterFunc` must follow this interface `callable(TOkay): bool`
    *  - Returns `Result<TOkay, Throwable>`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @param TOkay $data
    * @param string $reason
    * @param callable(TOkay): bool $filterFunc
    * @return Result<TOkay, Throwable>
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
    * Take a value, turn it a `Result::okay($data)` iff `!is_null($data)`, otherwise returns `Result::error($errorValue)`
    *
    * _Notes:_
    *
    * - Returns `Result<TOkay, Throwable>`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @param TOkay $data
    * @param string $reason
    * @return Result<TOkay, Throwable>
    **/
   public static function okayNotNull($data, string $reason): self {
      $ex = new Exception($reason);
      $either = Either::notNullLeft($data, $ex);
      return new self($either);
   }

   /**
    * Creates a Result if the `$key` exists in `$array`
    *
    * _Notes:_
    *
    * - Returns `Result<TOkay, Throwable>`
    *
    * @psalm-mutation-free
    * @psalm-pure
    * @param array<array-key, mixed> $array
    * @param array-key $key The key of the array
    * @param string $reason
    *  @return Result<TOkay, Throwable>
    **/
   public static function fromArray(array $array, $key, string $reason = null): self {
      $ex = $reason
         ? new Exception($reason)
         : new \Exception("Result could not grab $key from array. No reason given.");

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

      /** @var TOkay|null **/
      $lv = $either->leftOr(null);

      /** @var TError **/
      $rv = $either->rightOr(new Exception("Result::error was not a Result::error?"));

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
