<?php
declare(strict_types = 1);
namespace Optional\Probe;
use Optional\Either;
use Optional\Option;
function probe(): void {
   $v = Option::fromArray(['hello' => 'world'], 'hello')->valueOr('x');
   /** @psalm-trace $v */
   $e = Either::fromArray(['hello' => 'world'], 'hello', 'oh no');
   /** @psalm-trace $e */
   echo $v, (string) $e;
}
