<?php

declare(strict_types = 1);

namespace Optional\Internal;

use Stringable;
use Throwable;
use UnitEnum;

/**
 * How a box shows what it holds, in `__toString()` and in error messages.
 *
 * It never warns and never throws, and two different values never look the
 * same: a string is quoted, so `'null'` is not `null` and `'1'` is not `1`, and
 * a float keeps its point, so `1.0` is not `1`. A string's control characters
 * are escaped, so the result stays on one line. An enum case shows as
 * `Class::Case`, an exception as its class and message rather than its trace,
 * and anything else with no string form as its type.
 *
 * @internal
 */
trait DescribesValues {
   private static function describe(mixed $value): string {
      return match (true) {
         $value === null => 'null',
         is_bool($value) => $value ? 'true' : 'false',
         is_int($value) => (string) $value,
         is_float($value) => var_export($value, true),
         is_string($value) => "'" . addcslashes($value, "\0..\37'\\") . "'",
         $value instanceof UnitEnum => $value::class . '::' . $value->name,
         $value instanceof Throwable => get_debug_type($value) . ': ' . $value->getMessage(),
         $value instanceof Stringable => self::describeStringable($value),
         default => get_debug_type($value),
      };
   }

   /**
    * A `__toString()` that throws must not make the box's own string form throw
    */
   private static function describeStringable(Stringable $value): string {
      try {
         return (string) $value;
      } catch (Throwable) {
         return get_debug_type($value);
      }
   }
}
