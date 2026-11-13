<?php

declare(strict_types = 1);

namespace Optional\Tests;

use Optional\Either;
use Optional\Option;

/**
 * Checked by Psalm, never run. An Option<T> or Either<TLeft, TRight> whose
 * type parameters aren't never could be either case, so these return types
 * must include both outcomes.
 */

/**
 * @param Option<int> $option
 * @psalm-suppress UnusedVariable
 */
function optionReturnTypes(Option $option, string $text): void {
   $valueOr = $option->valueOr(null);
   /** @psalm-check-type-exact $valueOr = int|null */

   $valueOrCreate = $option->valueOrCreate(function () use ($text): string {
      return $text;
   });
   /** @psalm-check-type-exact $valueOrCreate = int|string */

   $or = $option->or($text);
   /** @psalm-check-type-exact $or = Option<int|string> */

   $else = $option->else(Option::some($text));
   /** @psalm-check-type-exact $else = Option<int|string> */

   $map = $option->map(function (int $_value) use ($text): string {
      return $text;
   });
   /** @psalm-check-type-exact $map = Option<string> */

   $mapSafely = $option->mapSafely(function (int $_value) use ($text): string {
      return $text;
   });
   /** @psalm-check-type-exact $mapSafely = Option<string> */
}

/**
 * @psalm-suppress UnusedVariable
 */
function noneReturnTypes(): void {
   $valueOr = Option::none()->valueOr(null);
   /** @psalm-check-type-exact $valueOr = null */
}

/**
 * @param Either<int, string> $either
 * @psalm-suppress UnusedVariable
 */
function eitherReturnTypes(Either $either, float $number): void {
   $leftOr = $either->leftOr(null);
   /** @psalm-check-type-exact $leftOr = int|null */

   $rightOrCreate = $either->rightOrCreate(function (int $left): bool {
      return $left > 0;
   });
   /** @psalm-check-type-exact $rightOrCreate = bool|string */

   $match = $either->match(
      function (int $left): bool {
         return $left > 0;
      },
      function (string $_right): ?bool {
         return null;
      }
   );
   /** @psalm-check-type-exact $match = bool|null */

   $orRight = $either->orRight($number);
   /** @psalm-check-type-exact $orRight = Either<never, float|string> */

   $elseLeft = $either->elseLeft(Either::right($number));
   /** @psalm-check-type-exact $elseLeft = Either<int, float> */

   $mapRight = $either->mapRight(function (string $right): int {
      return intval($right);
   });
   /** @psalm-check-type-exact $mapRight = Either<int, int> */

   $filterLeft = $either->filterLeft(false, $number);
   /** @psalm-check-type-exact $filterLeft = Either<int, float|string> */
}

/**
 * @psalm-suppress UnusedVariable
 */
function leftReturnTypes(int $number): void {
   $leftOr = Either::left($number)->leftOr(null);
   /** @psalm-check-type-exact $leftOr = int */
}
