<?php

declare(strict_types = 1);

namespace Optional\Tests\Fixtures;

use Exception;

/**
 * A placeholder unit of work the `flatMap` and `andThen` examples call.
 *
 * Throws when handed a second argument, which is how the examples demonstrate
 * a failing step inside a chain.
 */
final class SomeComplexThing {
   /**
    * @throws Exception iff $ex is given
    */
   public static function doWork(string $thing, ?string $ex = null): string {
      if ($ex !== null) {
         throw new Exception($ex);
      }

      return $thing;
   }
}
