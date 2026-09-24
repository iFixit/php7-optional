<?php

declare(strict_types = 1);

namespace Optional\Internal;

use InvalidArgumentException;
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
    * The message `valueOrThrow()` puts on its `MissingValueException`
    *
    * A class name there is almost always meant as the exception to throw, and
    * would silently become the message, so it throws instead. Only a string
    * with no space can be a class name, so a message never reaches an
    * autoloader.
    */
   private static function missingMessage(?string $message, string $otherwise): string {
      if ($message !== null && !str_contains($message, ' ') && is_a($message, Throwable::class, true)) {
         throw new InvalidArgumentException(
            "valueOrThrow() takes a message, and {$message} is an exception class. "
            . 'To throw one, pass a closure that builds it'
         );
      }

      return $message ?? $otherwise;
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
