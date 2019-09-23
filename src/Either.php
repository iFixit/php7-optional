<?php

declare(strict_types = 1);

namespace Optional;

class Either {
   private $hasValue;
   private $someValue;
   private $noneValue;

   private function __construct($someValue, $noneValue, bool $hasValue) {
      $this->hasValue = $hasValue;
      $this->someValue = $someValue;
      $this->noneValue = $noneValue;
   }

   public function hasValue() {
      return $this->hasValue;
   }

   public function valueOr($alternative) {
      return $this->hasValue ? $this->someValue : $alternative;
   }

   public function valueOrCreate(callable $func) {
      $this->throwIfNull($func);

      return $this->hasValue ? $this->someValue : $func($this->noneValue);
   }

   public function or($alternative): self {
      return $this->hasValue ? $this : self::some($alternative);
   }

   public function orCreate(callable $func): self {
      $this->throwIfNull($func);

      return $this->hasValue ? $this : self::some($func($this->noneValue));
   }

   public function else(self $alternativeOption): self {
      return $this->hasValue ? $this : $alternativeOption;
   }

   public function elseCreate(callable $func): self {
      $this->throwIfNull($func);

      return $this->hasValue ? $this : $func($this->noneValue);
   }

   public function match(callable $some, callable $none) {
      $this->throwIfNull($some);
      $this->throwIfNull($none);

      return $this->hasValue ? $some($this->someValue) : $none($this->noneValue);
   }

   public function matchSome(callable $some): void {
      $this->throwIfNull($some);

      if (!$this->hasValue) {
         return;
      }

      $some($this->someValue);
   }

   public function matchNone(callable $none): void {
      $this->throwIfNull($none);

      if ($this->hasValue) {
         return;
      }

      $none($this->noneValue);
   }

   public function map(callable $mapFunc): self {
      $this->throwIfNull($mapFunc);

      $someFunc = function($value) use ($mapFunc) {
         return self::some($mapFunc($value));
      };

      $noneFunc = function($noneValue) {
         return self::none($noneValue);
      };

      return $this->match($someFunc, $noneFunc);
   }

   public function flatMap(callable $mapFunc): self {
      $this->throwIfNull($mapFunc);

      $noneFunc = function($noneValue) {
         return self::none($noneValue);
      };

      return $this->match($mapFunc, $noneFunc);
   }

   public function filter(bool $condition, $noneValue): self {
      return $this->hasValue && !$condition ? self::none($noneValue) : $this;
   }

   public function filterIf(callable $filterFunc, $noneValue): self {
      $this->throwIfNull($filterFunc);

      return $this->hasValue && !$filterFunc($this->someValue) ? self::none($noneValue) : $this;
   }

   public function notNull($noneValue): self {
      return $this->hasValue && $this->someValue == null ? self::none($noneValue) : $this;
   }

   public function contains($value): bool {
      if (!$this->hasValue()) {
         return false;
      }

      return $this->someValue == $value;
   }

   public function exists(callable $existsFunc): bool {
      $this->throwIfNull($existsFunc);

      if (!$this->hasValue()) {
         return false;
      }

      return $existsFunc($this->someValue);
   }

   public function __toString() {
      if ($this->hasValue()) {
         if ($this->someValue == null) {
            return "Some(null)";
         }
          return "Some({$this->someValue})";
      }

      if ($this->noneValue == null) {
         return "None(null)";
      }

      return "None({$this->noneValue})";
   }

   //////////////////////////////
   // STATIC FACTORY FUNCTIONS //
   //////////////////////////////

   public static function some($someValue): self {
      return new self($someValue, null, true);
   }

   public static function none($noneValue): self {
      return new self(null, $noneValue, false);
   }

   public static function someWhen($someValue, $noneValue, callable $filterFunc): self {
      if ($filterFunc($someValue)) {
         return self::some($someValue);
      }
      return self::none($noneValue);
   }

   public static function noneWhen($someValue, $noneValue, callable $filterFunc): self {
      if ($filterFunc($someValue)) {
         return self::none($noneValue);
      }
      return self::some($someValue);
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
