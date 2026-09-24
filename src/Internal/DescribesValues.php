<?php

declare(strict_types = 1);

namespace Optional\Internal;

use Stringable;
use Throwable;

/**
 * How a box shows what it holds, in `__toString()` and in error messages.
 *
 * It never warns and never throws. A value with no string form is shown by its
 * type, and an exception by its class and message rather than by its trace.
 *
 * @internal
 */
trait DescribesValues {
   private static function describe(mixed $value): string {
      return match (true) {
         $value === null => 'null',
         is_bool($value) => $value ? 'true' : 'false',
         $value instanceof Throwable => get_class($value) . ': ' . $value->getMessage(),
         is_scalar($value), $value instanceof Stringable => (string) $value,
         default => get_debug_type($value),
      };
   }
}
