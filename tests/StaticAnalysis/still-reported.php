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
 */

declare(strict_types = 1);

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
