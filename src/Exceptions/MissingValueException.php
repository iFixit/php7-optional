<?php

declare(strict_types = 1);

namespace Optional\Exceptions;

use Exception;

/**
 * Thrown when a value is read off the empty side of a box.
 *
 * `valueOrThrow()` raises this when it is given a message or nothing, rather
 * than a closure that builds its own exception. With no argument,
 * `Result::err($error)->valueOrThrow()` rethrows an error that is itself a
 * `Throwable` instead. Every other way of reading a box either takes an
 * alternative or hands you both cases, so this is the only exception the
 * library throws on its own, apart from the `Error` that `valueOrReturn()`
 * throws outside a `do()` block.
 *
 * @api
 */
final class MissingValueException extends Exception {
}
