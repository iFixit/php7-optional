<?php

declare(strict_types = 1);

namespace Optional\Internal;

use Optional\Exceptions\EarlyReturn;
use Stringable;
use Throwable;
use UnitEnum;

/**
 * How a box shows what it holds, in `__toString()` and in error messages.
 *
 * It never warns and never throws, and the result is one line of valid UTF-8.
 * Two different scalars never look the same: a string is quoted, so `'null'`
 * is not `null` and `'1'` is not `1`, and a float keeps its point, so `1.0` is
 * not `1`. A string's control characters are escaped, and so are its high
 * bytes when it is not valid UTF-8. An enum case shows as `Class::Case`, an
 * exception as its class and its quoted message rather than its trace, and any
 * other object with a string form as its class and that string, quoted, such
 * as `SplFileInfo('5')`. Anything else shows as its type, so two arrays, or two
 * objects with no string form, can look alike.
 *
 * @internal
 */
trait DescribesValues {
   private static function describe(mixed $value): string {
      return match (true) {
         is_bool($value) => $value ? 'true' : 'false',
         is_int($value) => (string) $value,
         is_float($value) => var_export($value, true),
         is_string($value) => self::quote($value),
         $value instanceof UnitEnum => $value::class . '::' . $value->name,
         $value instanceof Throwable => get_debug_type($value) . '(' . self::quote($value->getMessage()) . ')',
         $value instanceof Stringable => self::describeStringable($value),
         default => get_debug_type($value), // null is 'null' here too
      };
   }

   /**
    * `$text` in single quotes, escaped so the result is one line of valid
    * UTF-8, which a log line or `json_encode()` can take
    */
   private static function quote(string $text): string {
      $escaped = preg_match('//u', $text) === 1 ? "\0..\37'\\\177" : "\0..\37'\\\177..\377";

      return "'" . addcslashes($text, $escaped) . "'";
   }

   /**
    * A `__toString()` that throws must not make the box's own string form
    * throw, unless it is ending a `do()` block
    */
   private static function describeStringable(Stringable $value): string {
      try {
         return get_debug_type($value) . '(' . self::quote((string) $value) . ')';
      } catch (EarlyReturn $exit) {
         throw $exit;
      } catch (Throwable) {
         return get_debug_type($value);
      }
   }
}
