<?php

/**
 * Boxes built in two branches, with the plugin and extension turned on.
 * StaticAnalysisTest compares each trace with the type it records for psalm
 * and for PHPStan, and fails on any other issue. A variable keeps its union,
 * so a type declared as that union still accepts it, and a method called on
 * it sees one box.
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

/** @return Result<int, never>|Result<never, string> */
function branchBuiltDeclaredUnion(bool $flag, int $id): Result {
   return $flag ? Result::ok($id) : Result::err('no');
}

/**
 * A template bounded by one box calls that box's methods, so it merges too
 *
 * @template R of Result<int, string>
 * @param R $found
 */
function branchBuiltTemplate(bool $flag, Result $found): void {
   $templated = ($flag ? $found : Result::err('no'))->map(fn(int $x): int => $x * 2);
   /** @psalm-trace $templated */
}

function branchBuilt(bool $flag, int $id, ?BranchBuiltUser $maybe, string $input, int $kind): void {
   $ternary = $flag ? Result::ok($id) : Result::err('no');
   /** @psalm-trace $ternary */

   // the boxes merge where a method is called on them, and everything else in the union stays
   $maybeFound = $flag ? Result::ok($id) : ($kind > 0 ? Result::err('no') : null);
   /** @psalm-trace $maybeFound */
   $maybeMapped = $maybeFound?->map(fn(int $x): int => $x + 1);
   /** @psalm-trace $maybeMapped */

   // a union declared as such still takes its own values, since only a method call merges
   $declared = branchBuiltDeclaredUnion($flag, $id)->map(fn(int $x): int => $x);
   /** @psalm-trace $declared */
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

   /** @var array<string, Result<int, never>> $cache */
   $cache = [];
   $cached = ($cache[$input] ?? Result::err('missing'))->map(fn(int $x): int => $x + 1);
   /** @psalm-trace $cached */

   /** @psalm-suppress MissingClosureReturnType the type comes from its two returns */
   $parse = function(string $s) {
      if (is_numeric($s)) {
         return Result::ok((int) $s);
      }

      return Result::err("not a number: {$s}");
   };
   $parsed = $parse($input)->map(fn(int $x): int => $x * 2);
   /** @psalm-trace $parsed */
}
