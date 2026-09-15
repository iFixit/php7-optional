<?php
declare(strict_types = 1);
namespace Optional\Probe;
use Optional\Option;
function boom(): string {
   // This is provably a None. Does psalm object to reading its value?
   return Option::none()->map(function($x): string { return "s"; })->value();
}
