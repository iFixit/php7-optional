<?php

/**
 * Mistakes each tool has to go on reporting with the plugin and extension on.
 * StaticAnalysisTest checks that each tool reports exactly these.
 *
 * A caller's docblock with the wrong number of types: a Result with no error
 * type. psalm's merge once crashed on it where a method was called on the
 * box, and every other issue in the run was lost with it.
 *
 * A template bounded by a box or null. It may be null, so it is not merged
 * as a box, and the method called on it is reported.
 *
 * An exception object passed to valueOrThrow(), which takes a closure that
 * builds one. PHPStan let it through while the method's PHP type named
 * Throwable.
 *
 * Callbacks typed for the wrong side of a box built in two branches. Neither
 * tool checks them against the right box without the merge.
 */

declare(strict_types = 1);

use Optional\Option;
use Optional\Result;

final class StillReportedRepository {
   /** @return Result<int> */
   public function find(int $id): Result {
      return Result::ok($id);
   }
}

function stillReportedTypeCount(StillReportedRepository $repository, ?int $id): string {
   $found = $id === null ? Result::err('no id') : $repository->find($id);

   return (string) $found->map(fn(int $x): int => $x);
}

/**
 * @template R of Result<int, string>|null
 * @param R $found
 * @param Result<int, string> $fallback
 */
function stillReportedNullable(bool $flag, ?Result $found, Result $fallback): string {
   return (string) ($flag ? $found : $fallback)->map(fn(int $x): int => $x);
}

/** @param Result<int, string> $found */
function stillReportedExceptionObject(Result $found): int {
   return $found->valueOrThrow(new RuntimeException('no row'));
}

function stillReportedWrongCallbacks(bool $flag, int $id): void {
   $found = $flag ? Result::ok($id) : Result::err('no');
   $_ = $found->map(fn(string $s): int => strlen($s));

   $mixed = $flag ? Option::some(1) : Option::some('a');
   $_ = $mixed->map(fn(int $x): int => $x);

   $maybe = $flag ? Result::ok($id) : ($id > 0 ? Result::err('no') : null);
   $_ = $maybe?->map(fn(string $s): int => strlen($s));
}
