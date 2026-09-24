<?php

/**
 * Boxes handed to declared types, the way callers write them.
 *
 * Expressions.php records what psalm infers. This file checks the other half:
 * that a box fits the type a function, property or parameter declares. It has
 * no traces, so TypeInferenceTest fails on any issue psalm reports here.
 *
 * Every function below once failed psalm at errorLevel 1, when the templates
 * were invariant. psalm let a literal type through (`Option<'x'>` into
 * `Option<string>`), which is what the other fixture uses, so nothing noticed.
 */

declare(strict_types = 1);

use Optional\Option;
use Optional\Result;

class AssignabilityAnimal {}

final class AssignabilityDog extends AssignabilityAnimal {}

final class AssignabilityShelter {
   /** @var Option<AssignabilityAnimal> */
   public Option $resident;

   public function __construct() {
      $this->resident = Option::none();
   }

   public function admit(AssignabilityDog $dog): void {
      $this->resident = Option::some($dog);
   }
}

/**
 * A narrower type goes in where a wider one is declared
 *
 * @param list<mixed> $items
 * @return Option<int>
 */
function assignabilityCount(array $items): Option {
   return Option::some(count($items));
}

/** @return Option<AssignabilityAnimal> */
function assignabilitySubclass(): Option {
   return Option::some(new AssignabilityDog());
}

/** @return Option<string> */
function assignabilityNarrowedString(string $name): Option {
   return $name === '' ? Option::none() : Option::some($name);
}

/** @return Option<int> */
function assignabilityPosition(string $haystack): Option {
   return Option::someNotFalse(strpos($haystack, 'x'));
}

/** @return Result<int, string> */
function assignabilityTernary(int $id): Result {
   return $id > 0 ? Result::ok($id) : Result::err('no id');
}

/** @return Result<int, string> */
function assignabilityEarlyReturn(int $id): Result {
   if ($id < 0) {
      return Result::err('negative');
   }

   return Result::ok($id);
}

/**
 * @param Result<string, string> $name
 * @return Result<int, string>
 */
function assignabilityMapped(Result $name): Result {
   return $name->map(strlen(...));
}

/** @return Result<mixed, Throwable> */
function assignabilityCaughtClass(string $json): Result {
   return Result::fromCallable(fn(): mixed => json_decode($json, flags: JSON_THROW_ON_ERROR), JsonException::class);
}

/** @param Option<AssignabilityAnimal> $animal */
function assignabilityTakesAnimal(Option $animal): bool {
   return $animal->hasValue();
}

function assignabilityPassesADog(): bool {
   return assignabilityTakesAnimal(Option::some(new AssignabilityDog()))
      && !assignabilityTakesAnimal(Option::none());
}
