<?php

declare(strict_types = 1);

namespace Optional\Internal;

use Optional\Exceptions\EarlyReturn;
use Optional\Option;
use Optional\Result;
use Error;
use Exception;
use ReflectionClass;
use ReflectionProperty;
use Stringable;
use Throwable;
use UnitEnum;

/**
 * How a box shows what it holds, in `__toString()` and in error messages.
 *
 * It never warns and never throws, and the result is one line of valid UTF-8.
 * Two different scalars never look the same: a string is quoted, so `'null'`
 * is not `null` and `'1'` is not `1`, and a float keeps its point, so `1.0` is
 * not `1`. Floats print with PHP's `serialize_precision`, whose default of -1
 * tells every two apart; a setting below 17 can make `0.1 + 0.2` print as
 * `0.3`. A box inside a box shows the same way, as `Some(Some('a'))`. An
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
         $value instanceof Option, $value instanceof Result => self::describeBox($value),
         $value instanceof UnitEnum => self::oneLine($value::class . '::' . $value->name),
         $value instanceof Throwable => self::oneLine(get_debug_type($value) . '(' . self::messageOf($value) . ')'),
         $value instanceof Stringable => self::describeStringable($value),
         default => self::oneLine(get_debug_type($value)), // null is 'null' here too
      };
   }

   /**
    * `$text` as one line of valid UTF-8, which a log line or `json_encode()`
    * can take
    *
    * Control characters, Unicode line breaks and the twelve characters Unicode
    * lists as bidirectional controls, which can make a log line display
    * differently from what it holds, are escaped, and so is every high byte
    * when `$text` is not valid UTF-8. `$also` names more characters
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
         '/[\x{80}-\x{9F}\x{061C}\x{200E}\x{200F}\x{2028}\x{2029}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u',
         static fn(array $char): string => addcslashes($char[0], "\200..\377"),
         addcslashes($text, "\0..\37\177{$also}")
      );
   }

   /**
    * The message `valueOrThrow()` puts on its `MissingValueException`
    *
    * A message that is exactly the name of an exception class was almost
    * always meant as the exception to throw, so the message says how to throw
    * one. A class PHP has already loaded counts if its case matches, so a
    * message such as `'error'` is left alone. One that is not loaded counts if
    * it is spelled like a namespaced class whose name ends in `Exception` or
    * `Error`, such as `'App\NotFoundError'`; a bare `'NotFoundError'` may be an
    * error code. No autoloader runs either way.
    */
   private static function missingMessage(?string $message, string $otherwise): string {
      if ($message === null) {
         return $otherwise;
      }

      $class = ltrim($message, '\\');
      $namesAnException = class_exists($class, false)
         ? is_a($class, Throwable::class, true) && (new ReflectionClass($class))->getName() === $class
         // a project's own exception is namespaced and usually not loaded yet, so
         // judge a namespaced name by its spelling; a bare word such as
         // 'NotFoundError' may be an error code the caller compares against
         // (\x5c is the namespace separator, spelled so Infection can print a diff of the line)
         : preg_match('/^(?:[A-Za-z_\x80-\xff][\w\x80-\xff]*\x5c)+(?=[A-Z])[\w\x80-\xff]*(?:Exception|Error)$/D', $class) === 1;

      return $namesAnException ? "{$message} (a class name: pass valueOrThrow() a closure to throw one)" : $message;
   }

   /**
    * An exception's message, read without `getMessage()`, which converts
    * whatever a subclass stored in the untyped property: an array there warns
    * and an object throws. The raw read also skips a property hook a subclass
    * may have put on it.
    */
   private static function messageOf(Throwable $exception): string {
      $property = new ReflectionProperty($exception instanceof Exception ? Exception::class : Error::class, 'message');
      /** @var mixed $message a subclass can store anything there */
      $message = $property->isInitialized($exception) ? $property->getRawValue($exception) : null;

      return match (true) {
         // var_export(), as describe() prints a float: 1.0 keeps its point, and
         // NAN does not warn, as a string cast of it does on PHP 8.5
         is_float($message) => var_export($message, true),
         is_scalar($message) || $message === null => (string) $message,
         default => get_debug_type($message),
      };
   }

   /**
    * A box inside a box. Its own string form never throws, but a chain deep
    * enough to reach the stack limit does, and so does a lazy box whose
    * initializer fails
    */
   private static function describeBox(Stringable $box): string {
      try {
         return (string) $box;
      } catch (EarlyReturn $exit) {
         throw $exit;
      } catch (Throwable) {
         return get_debug_type($box);
      }
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
