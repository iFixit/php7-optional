<?php
declare(strict_types = 1);
namespace Optional\Probe;
use Optional\Either;
use Optional\Option;

function probe(): void {
   $a = Option::none()->map(function($x) { return "s"; });
   /** @psalm-trace $a */
   $b = Option::some(5)->filter(false);
   /** @psalm-trace $b */
   $c = Either::right('err')->mapLeft(function($x) { return "s"; });
   /** @psalm-trace $c */
   $d = Option::fromArray(['hello' => 'world'], 'nope');
   /** @psalm-trace $d */
   echo (string)$a,(string)$b,(string)$c,(string)$d;
}
