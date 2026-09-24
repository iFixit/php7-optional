<?php

declare(strict_types = 1);

namespace Optional\Exceptions;

use Exception;
use Throwable;

/**
 * Thrown when a value is read off the empty side of a box.
 *
 * `valueOrThrow()` raises this when it is given a message or nothing, rather
 * than a closure that builds its own exception. With no argument,
 * `Result::err($error)->valueOrThrow()` rethrows an error that is itself a
 * `Throwable` instead.
 *
 * `$error` is the `Result`'s error, so a catch can still read an error that is
 * an enum or an array, which the message can only describe. It is `null` for
 * an `Option`, which has no error.
 *
 * @api
 */
final class MissingValueException extends Exception {
   /**
    * @internal only valueOrThrow() builds one
    */
   public function __construct(string $message, public readonly mixed $error = null, ?Throwable $previous = null) {
      parent::__construct($message, 0, $previous);
   }
}
