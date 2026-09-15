<?php
declare(strict_types = 1);
namespace Optional\Probe;
use Optional\Option;

function probe(): void {
   $arr = ['hello' => 'world'];
   $hit = Option::fromArray($arr, 'hello');
   /** @psalm-trace $hit */
   $miss = Option::fromArray($arr, 'nope');
   /** @psalm-trace $miss */
   echo (string) $hit, (string) $miss;
}
