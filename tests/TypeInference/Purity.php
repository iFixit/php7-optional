<?php

/**
 * Boxes used from pure functions and immutable classes.
 *
 * psalm only lets pure code call methods marked pure or mutation-free. A
 * method that runs a callback you pass, such as `map()`, cannot be either,
 * since the callback may have side effects. Everything below runs no
 * callback, so it must stay callable here. TypeInferenceTest fails on any
 * issue psalm reports in this file.
 *
 * PHPStan is not run on it. PHPStan does not read `@psalm-mutation-free`, so
 * it calls every instance method from pure code possibly impure, as it did
 * for 3.0.
 */

declare(strict_types = 1);

use Optional\Option;
use Optional\Result;

/** @psalm-pure */
function purityName(?string $maybe): string {
   return Option::someNotNull($maybe)->valueOr('nobody');
}

/** @psalm-pure */
function purityFirstLine(string|false $line): string {
   return Option::someNotFalse($line)->or('')->valueOr('');
}

/**
 * @psalm-pure
 * @param array<string, string> $row
 * @return Option<string>
 */
function purityNickname(array $row): Option {
   return Option::someNotFalsy($row['nickname'] ?? null);
}

/**
 * A step for a `do()` block. The block itself runs a callback, so it belongs
 * to the caller, not to the immutable class.
 *
 * @psalm-immutable
 */
final class PurityConfig {
   /** @param Option<int> $port */
   public function __construct(private Option $port) {}

   public function port(): int {
      return $this->port->valueOrReturn();
   }
}

/** @param Result<int, string> $parsed */
function purityPortOrDefault(Result $parsed): int {
   return (new PurityConfig($parsed->toOption()))->port();
}
