<?php

declare(strict_types = 1);

namespace Optional\Internal;

use TypeError;

/**
 * The check `all()` and `partition()` run on each item before they put it in
 * an array.
 *
 * psalm and PHPStan make the same check, but only for callers who run them.
 * Without it, a wrong item gave a warning and a wrong answer, and a `null` key
 * from a generator is deprecated on PHP 8.5. It is a method rather than an
 * inline `instanceof` because both tools know the item is a box already, and
 * would report the check as always true.
 *
 * @internal
 */
trait ChecksItems {
   /**
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
}
