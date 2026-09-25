<?php

declare(strict_types = 1);

namespace Optional\Internal;

use Closure;
use Throwable;
use TypeError;

/**
 * The checks the boxes run on arguments that PHP's own types let through
 *
 * psalm and PHPStan make the same checks, but only for callers who run them.
 * They are methods rather than inline checks because both tools already know
 * the argument has the right type from its docblock, and would report an
 * inline check as always true.
 *
 * @internal
 */
trait ChecksArguments {
   /**
    * The check `all()` and `partition()` run on each item before they put it
    * in an array. Without it, a wrong item gave a warning and a wrong answer,
    * and a `null` key from a generator is deprecated on PHP 8.5.
    *
    * @psalm-assert int|string $key
    */
   private static function checkItem(string $method, mixed $key, mixed $item): void {
      if (!$item instanceof self) {
         throw new TypeError("{$method} takes only " . self::class . ' items, got ' . get_debug_type($item));
      }

      if (!is_int($key) && !is_string($key)) {
         throw new TypeError("{$method} needs int or string keys, got " . get_debug_type($key));
      }
   }

   /**
    * `valueOrThrow()` takes any object in its PHP type, since PHPStan adds a
    * PHP type's classes back to the docblock's: with `Throwable` there, it
    * let an exception object through. So this rejects every object but a
    * closure. In a file without strict types, it also keeps an exception from
    * being turned into its own text, stack trace and all.
    */
   private static function checkOrThrow(mixed $orThrow): void {
      if (!is_object($orThrow) || $orThrow instanceof Closure) {
         return;
      }

      throw new TypeError($orThrow instanceof Throwable
         ? 'valueOrThrow() takes a closure that builds the exception, such as fn() => new '
            . get_debug_type($orThrow) . '(...), not the exception itself'
         : 'valueOrThrow() takes a message or a closure that builds the exception, got ' . get_debug_type($orThrow));
   }
}
