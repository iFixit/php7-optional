<?php

/**
 * A caller's docblock with the wrong number of types: a Result with no error
 * type. psalm has to report it the usual way. The plugin's merge once crashed
 * psalm on it, and every other issue in the run was lost with it.
 */

declare(strict_types = 1);

use Optional\Result;

final class WrongTypeCountRepository {
   /** @return Result<int> */
   public function find(int $id): Result {
      return Result::ok($id);
   }
}

function wrongTypeCount(WrongTypeCountRepository $repository, ?int $id): string {
   $found = $id === null ? Result::err('no id') : $repository->find($id);

   return (string) $found;
}
