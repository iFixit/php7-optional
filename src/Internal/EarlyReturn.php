<?php

declare(strict_types = 1);

namespace Optional\Internal;

use Error;

/**
 * Carries an empty box out of an `Option::do()` or `Result::do()` block.
 *
 * `valueOrReturn()` throws it, and only the nearest `do()` for the same kind
 * of box catches it, so a `Result` failing inside an `Option::do()` passes
 * through to the `Result::do()` around it. `fromCallable()` and `mapSafely()`
 * never catch it, whatever class they were told to catch.
 *
 * It is an `Error` so a `catch (Exception)` in the block cannot swallow it.
 * One that escapes means `valueOrReturn()` ran outside any matching block, and
 * its message says so.
 *
 * @internal
 */
final class EarlyReturn extends Error {
   /**
    * @param 'Option'|'Result' $box
    */
   public function __construct(public readonly string $box, public readonly mixed $error) {
      parent::__construct("{$box}::valueOrReturn() found no value, and there is no {$box}::do() around it to return from");
   }
}
