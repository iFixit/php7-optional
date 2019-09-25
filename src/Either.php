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

   public function hasValue(): bool {
      return $this->hasValue;
   }

   /**
    * @param TSome $alternative
    * @return TSome
    **/
   public function valueOr($alternative) {
      return $this->hasValue ? $this->someValue : $alternative;
   }

   /**
    * @param callable(TNone):TSome $alternativeFactory
    * @return TSome
    **/
   public function valueOrCreate(callable $alternativeFactory) {
      return $this->hasValue ? $this->someValue : $alternativeFactory($this->noneValue);
   }

   /**
    * @param TSome $alternative
    * @return Either<TSome, TNone>
    **/
   public function or($alternative): self {
      return $this->hasValue ? $this : self::some($alternative);
   }

   /**
    * @param callable(TNone):TSome $alternativeFactory
    * @return Either<TSome, TNone>
    **/
   public function orCreate(callable $alternativeFactory): self {
      return $this->hasValue ? $this : self::some($alternativeFactory($this->noneValue));
   }

   /**
    * @param Either<TSome, TNone> $alternativeEither
    * @return Either<TSome, TNone>
    **/
   public function else(self $alternativeEither): self {
      return $this->hasValue ? $this : $alternativeEither;
   }

   /**
    * @param callable(TNone):Either<TSome, TNone> $alternativeEitherFactory
    * @return Either<TSome, TNone>
    **/
   public function elseCreate(callable $alternativeEitherFactory): self {
      return $this->hasValue ? $this : $alternativeEitherFactory($this->noneValue);
   }

   /**
    * @template U
    * @param callable(TSome):U $some
    * @param callable(TNone):U $none
    * @return U
    **/
   public function match(callable $some, callable $none) {
      return $this->hasValue ? $some($this->someValue) : $none($this->noneValue);
   }

   /**
    * @param callable(TSome) $some
    **/
   public function matchSome(callable $some): void {
      if (!$this->hasValue) {
         return;
      }

      $some($this->someValue);
   }

   /**
    * @param callable(TNone) $none
    **/
   public function matchNone(callable $none): void {
      if ($this->hasValue) {
         return;
      }

      $none($this->noneValue);
   }

   /**
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
    * @param TNone $noneValue
    * @return Either<TSome, TNone>
    **/
   public function filter(bool $condition, $noneValue): self {
      return $this->hasValue && !$condition ? self::none($noneValue) : $this;
   }

   /**
    * @param callable(TSome):bool $filterFunc
    * @param TNone $noneValue
    * @return Either<TSome, TNone>
    **/
   public function filterIf(callable $filterFunc, $noneValue): self {
      return $this->hasValue && !$filterFunc($this->someValue) ? self::none($noneValue) : $this;
   }

   /**
    * @param TNone $noneValue
    * @return Either<TSome, TNone>
    **/
   public function notNull($noneValue): self {
      return $this->hasValue && $this->someValue == null ? self::none($noneValue) : $this;
   }

   /** @param mixed $value */
   public function contains($value): bool {
      if (!$this->hasValue()) {
         return false;
      }

      return $this->someValue == $value;
   }

   /**
    * @param callable(TSome):bool $existsFunc
    **/
   public function exists(callable $existsFunc): bool {
      if (!$this->hasValue()) {
         return false;
      }

      return $existsFunc($this->someValue);
   }

   /**
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
    * @param TSome $someValue
    * @return Either<TSome, mixed>
    **/
   public static function some($someValue): self {
      return new self($someValue, null, true);
   }

   /**
    * @param TNone $noneValue
    * @return Either<mixed, TNone>
    **/
   public static function none($noneValue): self {
      return new self(null, $noneValue, false);
   }

   /**
    * @param TSome $someValue
    * @param TNone $noneValue
    * @return Either<TSome, TNone>
    **/
   public static function someWhen($someValue, $noneValue, callable $filterFunc): self {
      if ($filterFunc($someValue)) {
         return self::some($someValue);
      }
      return self::none($noneValue);
   }

   /**
    * @param TSome $someValue
    * @param TNone $noneValue
    * @return Either<TSome, TNone>
    **/
   public static function noneWhen($someValue, $noneValue, callable $filterFunc): self {
      if ($filterFunc($someValue)) {
         return self::none($noneValue);
      }
      return self::some($someValue);
   }
}
