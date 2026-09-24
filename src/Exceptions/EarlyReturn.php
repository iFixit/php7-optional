<?php

declare(strict_types = 1);

namespace Optional\Exceptions;

use Error;

/**
 * Carries an empty box out of an `Option::do()` or `Result::do()` block.
 *
 * `valueOrReturn()` throws it, and the nearest `do()` for the same kind of box
 * catches it, however many calls down it was thrown. It is an `Error`, so a
 * `catch (Exception)` in between lets it through. A catch-all has to let it
 * through by name:
 *
 * ```php
 * $port = Option::do(function() {
 *    try {
 *       return Option::fromArray(['host' => 'db1'], 'port')->valueOrReturn();
 *    } catch (\Optional\Exceptions\EarlyReturn $exit) {
 *       throw $exit; // the block ends here with a None
 *    } catch (\Throwable $e) {
 *       error_log("lookup failed: {$e->getMessage()}");
 *       return 5432;
 *    }
 * });
 *
 * echo (string) $port, "\n"; // None
 * ```
 *
 * `fromCallable()` and `mapSafely()` always let it through, whatever class
 * they were told to catch. If one reaches the top, `valueOrReturn()` ran with
 * no `do()` of its kind around it, or a catch in between kept it.
 *
 * @api
 */
final class EarlyReturn extends Error {
   /**
    * @internal only valueOrReturn() builds one
    * @param 'Option'|'Result' $box
    */
   public function __construct(public readonly string $box, public readonly mixed $error) {
      parent::__construct(
         "{$box}::valueOrReturn() found no value, and nothing caught the early exit: "
         . "there is no {$box}::do() around it, or a catch in between kept it"
      );
   }
}
