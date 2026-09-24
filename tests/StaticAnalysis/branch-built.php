<?php

/**
 * Boxes built in two branches, with the plugin and extension turned on.
 * StaticAnalysisTest compares each trace with the type it records for psalm
 * and for PHPStan, and fails on any other issue.
 *
 * Without them, psalm reports ReservedWord on every typed callback below,
 * and PHPStan types $mapped as Result<never, string>.
 */

declare(strict_types = 1);

use Optional\Option;
use Optional\Result;

final class BranchBuiltUser {
   public function __construct(public readonly string $name) {
   }
}

function branchBuilt(bool $flag, int $id, ?BranchBuiltUser $maybe, string $input, int $kind): void {
   $ternary = $flag ? Result::ok($id) : Result::err('no');
   /** @psalm-trace $ternary */
   $mapped = $ternary->map(fn(int $x): int => $x * 2);
   /** @psalm-trace $mapped */

   if ($flag) {
      $branches = Result::ok($input);
   } else {
      $branches = Result::err(new RuntimeException('no input'));
   }

   $length = $branches->map(fn(string $s): int => strlen($s));
   /** @psalm-trace $length */

   $matched = match ($kind) {
      0 => Result::ok(1.5),
      1 => Result::err('small'),
      default => Result::err(404),
   };
   $described = $matched->mapErr(fn(string|int $e): string => "failed: {$e}");
   /** @psalm-trace $described */

   $user = $maybe !== null ? Option::some($maybe) : Option::none();
   $name = $user->map(fn(BranchBuiltUser $u): string => $u->name);
   /** @psalm-trace $name */

   $direct = ($flag ? Result::ok($id) : Result::err('no'))->valueOrThrow();
   /** @psalm-trace $direct */
}
