<?php

declare(strict_types = 1);

namespace Optional;

class Option {
   private $hasValue;
   private $value;

   private function __construct($value, bool $hasValue) {
      $this->hasValue = $hasValue;
      $this->value = $value;
   }

   public function hasValue() {
      return $this->hasValue;
   }

   public function valueOr($alternative) {
      return $this->hasValue ? $this->value : $alternative;
   }

   public function valueOrCreate(callable $func) {
      $this->throwIfNull($func);

      return $this->hasValue ? $this->value : $func();
   }

   public function or($alternative): self {
      return $this->hasValue ? $this : self::some($alternative);
   }

   public function orCreate(callable $func): self {
      $this->throwIfNull($func);

      return $this->hasValue ? $this : self::some($func());
   }

   public function else(self $alternativeOption): self {
      return $this->hasValue ? $this : $alternativeOption;
   }

   public function elseCreate(callable $func): self {
      $this->throwIfNull($func);

      return $this->hasValue ? $this : $func();
   }

   public function match(callable $some, callable $none) {
      $this->throwIfNull($some);
      $this->throwIfNull($none);

      return $this->hasValue ? $some($this->value) : $none();
   }

   public function matchSome(callable $some): void {
      $this->throwIfNull($some);

      if (!$this->hasValue) {
         return;
      }

      $some($this->value);
   }

   public function matchNone(callable $none): void {
      $this->throwIfNull($none);

      if ($this->hasValue) {
         return;
      }

      $none();
   }

   public function map(callable $mapFunc): self {
      $this->throwIfNull($mapFunc);

      $someFunc = function($value) use ($mapFunc) {
         return self::some($mapFunc($value));
      };

      $noneFunc = function() {
         return self::none();
      };

      return $this->match($someFunc, $noneFunc);
   }

   public function flatMap(callable $mapFunc): self {
      $this->throwIfNull($mapFunc);

      $noneFunc = function() {
         return self::none();
      };

      return $this->match($mapFunc, $noneFunc);
   }

   public function filter(bool $condition): self {
      return $this->hasValue && !$condition ? self::none() : $this;
   }

   public function filterIf(callable $filterFunc): self {
      $this->throwIfNull($filterFunc);

      return $this->hasValue && !$filterFunc($this->value) ? self::none() : $this;
   }

   public function notNull(): self {
      return $this->hasValue && $this->value == null ? self::none() : $this;
   }

   public function __toString() {
      if ($this->hasValue()) {
         if ($this->value == null) {
            return "Some(null)";
         }
          return "Some({$this->value})";
      }

      return "None";
   }

   //////////////////////////////
   // STATIC FACTORY FUNCTIONS //
   //////////////////////////////

   /**
    * Wraps an existing value in an Option instance.
    * Returns An optional containing the specified value.
    */
    public static function some($thing): self {
      return new self($thing, true);
   }

   /**
    * Creates an empty Option instance.
    * Returns An empty optional.
    */
   public static function none(): self {
      return new self(null, false);
   }

   public static function someWhen($thing, callable $filterFunc): self {
      if ($filterFunc($thing)) {
         return self::some($thing);
      }
      return self::none();
   }

   public static function noneWhen($thing, callable $filterFunc): self {
      if ($filterFunc($thing)) {
         return self::none();
      }
      return self::some($thing);
   }


   //////////////////////////////
   //     Private Functions    //
   //////////////////////////////

   private function throwIfNull(callable $func) {
      if ($func == null) {
         throw new Exception("Function provided can not be null.");
      }
   }
}
