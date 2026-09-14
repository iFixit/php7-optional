<?php

declare(strict_types = 1);

namespace Optional\Exceptions;

use Exception;

/**
 * Thrown when a value is read off the empty side of a box.
 *
 * `Option::none()->value()`, `Either::right(...)->getLeft()` and
 * `Either::left(...)->getRight()` all raise this. Every other way of reading a
 * box either takes an alternative or hands you both cases, so this is the only
 * exception the library throws on its own.
 *
 * @api
 */
final class MissingValueException extends Exception {
}
