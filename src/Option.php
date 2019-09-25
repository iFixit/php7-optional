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

   public function hasValue(): bool {
      return $this->hasValue;
   }

   /**
    * @param T $alternative
    * @return T
    **/
   public function valueOr($alternative) {
      return $this->hasValue ? $this->value : $alternative;
   }

   /**
    * @param callable():T $alternativeFactory
    * @return T
    **/
   public function valueOrCreate(callable $alternativeFactory) {
      return $this->hasValue ? $this->value : $alternativeFactory();
   }

   /**
    * @param T $alternative
    * @return Option<T>
    **/
   public function or($alternative): self {
      return $this->hasValue ? $this : self::some($alternative);
   }

   /**
    * @param callable():T $alternativeFactory
    * @return Option<T>
    **/
   public function orCreate(callable $alternativeFactory): self {
      return $this->hasValue ? $this : self::some($alternativeFactory());
   }

   /**
    * @param Option<T> $alternativeOption
    * @return Option<T>
    **/
   public function else(self $alternativeOption): self {
      return $this->hasValue ? $this : $alternativeOption;
   }

   /**
    * @param callable():Option<T> $alternativeOptionFactory
    * @return Option<T>
    **/
   public function elseCreate(callable $alternativeOptionFactory): self {
      return $this->hasValue ? $this : $alternativeOptionFactory();
   }

   /**
    * @template U
    * @param callable(T):U $some
    * @param callable():U $none
    * @return U
    **/
   public function match(callable $some, callable $none) {
      return $this->hasValue ? $some($this->value) : $none();
   }

   /**
    * @param $some callable(T)
    **/
   public function matchSome(callable $some): void {
      if (!$this->hasValue) {
         return;
      }

      $some($this->value);
   }

   /**
    * @param $none callable(T)
    **/
   public function matchNone(callable $none): void {
      if ($this->hasValue) {
         return;
      }

      $none();
   }

   /**
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
    * @param callable(T):bool $filterFunc
    * @return Option<T>
    **/
   public function filterIf(callable $filterFunc): self {
      return $this->hasValue && !$filterFunc($this->value) ? self::none() : $this;
   }

   /**
    * @return Option<T>
    **/
   public function notNull(): self {
      return $this->hasValue && $this->value == null ? self::none() : $this;
   }

   /** @param mixed $value */
   public function contains($value): bool {
      if (!$this->hasValue()) {
         return false;
      }

      return $this->value == $value;
   }

   /**
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
    * Wraps an existing value in an Option instance.
    * Returns An optional containing the specified value.
    *
    * @param T $someValue
    * @return Option<T>
    */
   public static function some($someValue): self {
      return new self($someValue, true);
   }

   /**
    * Creates an empty Option instance.
    * Returns An empty optional.
    */
   public static function none(): self {
      return new self(null, false);
   }

   /**
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
