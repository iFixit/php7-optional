<?php

declare(strict_types = 1);

namespace Optional\Internal;

use Optional\Exceptions\EarlyReturn;
use Optional\Option;
use Optional\Result;
use ReflectionClass;
use Stringable;
use Throwable;
use UnitEnum;

/**
 * How a box shows what it holds, in `__toString()` and in error messages.
 *
 * It never warns and never throws, and the result is one line of valid UTF-8.
 * Two different scalars never look the same: a string is quoted, so `'null'`
 * is not `null` and `'1'` is not `1`, and a float keeps its point, so `1.0` is
 * not `1`. A box inside a box shows the same way, as `Some(Some('a'))`. An
 * enum case shows as `Class::Case`, an exception as its class and message, as
 * `RuntimeException(boom)`, and any other object with a string form as its
 * class and that string, as `SplFileInfo(5)`. Anything else shows as its type,
 * so two arrays, or two objects with no string form, look alike.
 *
 * @internal
 */
trait DescribesValues {
   private static function describe(mixed $value): string {
      return match (true) {
         is_bool($value) => $value ? 'true' : 'false',
         is_int($value) => (string) $value,
         is_float($value) => var_export($value, true),
         is_string($value) => "'" . self::oneLine($value, "'\\") . "'",
         $value instanceof Option, $value instanceof Result => (string) $value,
         $value instanceof UnitEnum => self::oneLine($value::class . '::' . $value->name),
         $value instanceof Throwable => self::oneLine(get_debug_type($value) . '(' . $value->getMessage() . ')'),
         $value instanceof Stringable => self::describeStringable($value),
         default => self::oneLine(get_debug_type($value)), // null is 'null' here too
      };
   }

   /**
    * `$text` as one line of valid UTF-8, which a log line or `json_encode()`
    * can take
    *
    * Control characters and Unicode line breaks are escaped, and so is every
    * high byte when `$text` is not valid UTF-8. `$also` names more characters
    * to escape, the quote and the backslash in a quoted string. Text an object
    * gives itself keeps both, so a box whose value's `__toString()` prints
    * another box is not escaped again at every level. Escaping them there made
    * the string form double in size with each level.
    */
   private static function oneLine(string $text, string $also = ''): string {
      if (preg_match('//u', $text) !== 1) {
         return addcslashes($text, "\0..\37\177..\377{$also}");
      }

      return (string) preg_replace_callback(
         '/[\x{80}-\x{9F}\x{2028}\x{2029}]/u',
         static fn(array $char): string => addcslashes($char[0], "\200..\377"),
         addcslashes($text, "\0..\37\177{$also}")
      );
   }

   /**
    * The message `valueOrThrow()` puts on its `MissingValueException`
    *
    * A message that is exactly the name of an exception class was almost
    * always meant as the exception to throw, so the message says how to throw
    * one. Only a class PHP has already loaded counts, so no autoloader runs,
    * and the case has to match, so a message such as `'error'` is left alone.
    */
   private static function missingMessage(?string $message, string $otherwise): string {
      if ($message === null) {
         return $otherwise;
      }

      $class = ltrim($message, '\\');
      $namesAnException = class_exists($class, false)
         && is_a($class, Throwable::class, true)
         && (new ReflectionClass($class))->getName() === $class;

      return $namesAnException ? "{$message} (a class name: pass valueOrThrow() a closure to throw one)" : $message;
   }

   /**
    * A `__toString()` that throws must not make the box's own string form
    * throw, unless it is ending a `do()` block
    */
   private static function describeStringable(Stringable $value): string {
      try {
         return self::oneLine(get_debug_type($value) . '(' . (string) $value . ')');
      } catch (EarlyReturn $exit) {
         throw $exit;
      } catch (Throwable) {
         return self::oneLine(get_debug_type($value));
      }
   }
}
