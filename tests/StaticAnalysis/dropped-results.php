<?php

/**
 * What the dropped-Result check reports, line by line. StaticAnalysisTest
 * expects a report on exactly the lines marked "dropped", from psalm and from
 * PHPStan alike.
 */

declare(strict_types = 1);

use Optional\Option;
use Optional\Result;

/** @return Result<int, string> */
function droppedResultsWrite(int $id): Result {
   error_log("writing {$id}");

   return Result::ok($id);
}

function droppedResultsNothing(): void {
}

function droppedResultsStop(): never {
   throw new LogicException('stopped');
}

class DroppedResultsBase {
   public function __construct(public readonly int $id) {
   }
}

final class DroppedResultsChild extends DroppedResultsBase {
   public function __construct() {
      parent::__construct(1);
   }
}

final class DroppedResultsRepository {
   /** @return Result<int, string> */
   public function save(int $id): Result {
      return $id > 0 ? Result::ok($id) : Result::err('no id');
   }

   /** @return Result<int, string> */
   public static function create(int $id): Result {
      return Result::ok($id);
   }
}

/** @return Result<int, string> */
function droppedResultsSave(int $id): Result {
   return $id > 0 ? Result::ok($id) : Result::err('no id');
}

function droppedResultsCallers(DroppedResultsRepository $repository, ?DroppedResultsRepository $maybe): int {
   droppedResultsSave(1); // dropped
   $repository->save(2); // dropped
   $maybe?->save(3); // dropped
   DroppedResultsRepository::create(4); // dropped
   $repository->save(5)->map(fn(int $x): int => $x + 1); // dropped
   $repository->save(6)->tap(fn(int $x) => error_log("saved {$x}")); // dropped, tap() does not look at the error
   $repository->save(7)->tapErr(fn(string $error) => error_log($error));
   droppedResultsWrite(12); // dropped, a function with side effects PHPStan's own checks let through
   droppedResultsNothing();
   $kept = droppedResultsSave(8);
   $_ = droppedResultsSave(9);
   $_ = Option::some(10)->map(fn(int $x): int => $x);

   return $kept->valueOr(0) + droppedResultsSave(11)->valueOr(0);
}

function droppedResultsStopping(): void {
   droppedResultsStop();
}
