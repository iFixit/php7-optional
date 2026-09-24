<?php

/**
 * Expressions whose inferred type is asserted by TypeInferenceTest.
 *
 * Each variable here is traced and compared against a type recorded in that
 * test. This file is outside psalm's projectFiles, because @psalm-trace is
 * reported as an issue and would fail the main run.
 *
 * No namespace, matching the generated example file and eval()'s own
 * resolution of bare class names.
 */

declare(strict_types = 1);

use Optional\Either;
use Optional\Option;
use Optional\Result;

function optionTypes(): void {
   // map used to erase the mapped type entirely and report Option<never>
   $mapKeepsTheMappedType = Option::some(5)->map(function(int $n): string { return "n{$n}"; });
   /** @psalm-trace $mapKeepsTheMappedType */

   $filterTrueKeepsTheValue = Option::some(5)->filter(true);
   /** @psalm-trace $filterTrueKeepsTheValue */

   // mapSafely declared the type going in rather than the type coming out
   $mapSafelyKeepsTheMappedType = Option::some(5)->mapSafely(function(int $n): string { return "n{$n}"; });
   /** @psalm-trace $mapSafelyKeepsTheMappedType */

   // fromArray collapsed to Option<mixed> at every call site
   $fromArrayKeepsTheValueType = Option::fromArray(['hello' => 'world'], 'hello');
   /** @psalm-trace $fromArrayKeepsTheValueType */

   $valueOrThrowReturnsTheValue = Option::some(41)->valueOrThrow(function(): Exception { return new RuntimeException('x'); });
   /** @psalm-trace $valueOrThrowReturnsTheValue */

   $valueOrThrowWithNoFactory = Option::some(41)->valueOrThrow();
   /** @psalm-trace $valueOrThrowWithNoFactory */

   $tapReturnsTheSameBox = Option::some(5)->tap(function(int $_n): void {});
   /** @psalm-trace $tapReturnsTheSameBox */

   $someNotFalsyKeepsTheType = Option::someNotFalsy('Ada');
   /** @psalm-trace $someNotFalsyKeepsTheType */

   $fromCallableKeepsTheReturnType = Option::fromCallable(function(): int { return 7; });
   /** @psalm-trace $fromCallableKeepsTheReturnType */

   $firstWithAPredicateKeepsTheType = Option::first(['a', 'bb'], function(string $s): bool { return strlen($s) > 1; });
   /** @psalm-trace $firstWithAPredicateKeepsTheType */
}

function eitherTypes(): void {
   // orRight had its two branches swapped, so it claimed only the alternative
   // for a box that keeps its original value. The original has to be in it.
   $orRightOnARightKeepsTheOriginal = Either::right('original')->orRight(3.14);
   /** @psalm-trace $orRightOnARightKeepsTheOriginal */

   $orRightOnALeftTakesTheAlternative = Either::left(42)->orRight(3.14);
   /** @psalm-trace $orRightOnALeftTakesTheAlternative */

   // @template U bound to nothing, so every caller got Option<never>
   $toOptionFromLeftKeepsTheLeft = Either::left(10)->toOptionFromLeft();
   /** @psalm-trace $toOptionFromLeftKeepsTheLeft */

   $toOptionFromRightOnALeftIsNone = Either::left(10)->toOptionFromRight();
   /** @psalm-trace $toOptionFromRightOnALeftIsNone */

   // the key-of<TArray> conditional never resolved, so a hit and a miss
   // inferred identically
   $eitherFromArrayKeepsTheValueType = Either::fromArray(['hello' => 'world'], 'hello', 'oh no');
   /** @psalm-trace $eitherFromArrayKeepsTheValueType */

   $mapLeftKeepsTheMappedType = Either::left(5)->mapLeft(function(int $n): string { return "n{$n}"; });
   /** @psalm-trace $mapLeftKeepsTheMappedType */



   $eitherFromCallableCarriesTheThrowable = Either::fromCallable(function(): int { return 7; });
   /** @psalm-trace $eitherFromCallableCarriesTheThrowable */

   $getLeftOrThrowReturnsTheLeft = Either::left(42)->getLeftOrThrow(function(): Exception { return new RuntimeException('x'); });
   /** @psalm-trace $getLeftOrThrowReturnsTheLeft */

   // naming the class to catch types the right side as that class
   $fromCallableTypesTheNamedClass = Either::fromCallable(function(): int { return 7; }, InvalidArgumentException::class);
   /** @psalm-trace $fromCallableTypesTheNamedClass */

   $mapLeftSafelyTypesTheNamedClass = Either::left(5)->mapLeftSafely(function(int $n): string { return "n{$n}"; }, JsonException::class);
   /** @psalm-trace $mapLeftSafelyTypesTheNamedClass */
}

function aliasTypes(): void {
   // an alias has to infer exactly what its target infers
   $viaMapSuccess = Either::success(21)->mapSuccess(function(int $n): string { return "n{$n}"; });
   /** @psalm-trace $viaMapSuccess */

   $viaMapLeft = Either::left(21)->mapLeft(function(int $n): string { return "n{$n}"; });
   /** @psalm-trace $viaMapLeft */

   $viaSuccessOr = Either::failure('e')->successOr('fallback');
   /** @psalm-trace $viaSuccessOr */

   $viaLeftOr = Either::right('e')->leftOr('fallback');
   /** @psalm-trace $viaLeftOr */
}

function chainingAcceptsEitherBranch(): void {
   // flatMapLeft demanded an impossible Either<never, never> and rejected a
   // callback that could fail. Tracing the result proves it binds now.
   $flatMapLeftAcceptsAFailingStep = Either::left(1)->flatMapLeft(
      function(int $n): Either { return Either::right("rejected {$n}"); }
   );
   /** @psalm-trace $flatMapLeftAcceptsAFailingStep */

   $flatMapRightAcceptsARecovery = Either::right('gone')->flatMapRight(
      function(string $_e): Either { return Either::left('default'); }
   );
   /** @psalm-trace $flatMapRightAcceptsARecovery */
}

/**
 * The entrances exist to drop the missing value, so the type has to drop it
 * too. someNotNull() on a ?string used to infer Option<null|string>, which made
 * the very next ->map(fn(string $s) => ...) an InvalidArgument.
 *
 * @param array{id: int}|false $row
 */
function entrancesDropTheMissingValue(?string $maybe, array|false $row, int|false $position): void {
   $someNotNullDropsNull = Option::someNotNull($maybe);
   /** @psalm-trace $someNotNullDropsNull */

   $someNotFalsyDropsFalse = Option::someNotFalsy($row);
   /** @psalm-trace $someNotFalsyDropsFalse */

   $notNullDropsNull = Option::some($maybe)->notNull();
   /** @psalm-trace $notNullDropsNull */

   $notFalsyDropsFalse = Option::some($position)->notFalsy();
   /** @psalm-trace $notFalsyDropsFalse */

   $notNullLeftDropsNull = Either::notNullLeft($maybe, 'missing');
   /** @psalm-trace $notNullLeftDropsNull */

   $notFalsyLeftDropsFalse = Either::notFalsyLeft($row, 'not found');
   /** @psalm-trace $notFalsyLeftDropsFalse */

   $notFalsyRightDropsFalse = Either::notFalsyRight($row, 'no error');
   /** @psalm-trace $notFalsyRightDropsFalse */

   $someNotFalseDropsFalse = Option::someNotFalse($position);
   /** @psalm-trace $someNotFalseDropsFalse */

   $notFalseDropsFalse = Option::some($position)->notFalse();
   /** @psalm-trace $notFalseDropsFalse */

   $notFalseLeftDropsFalse = Either::notFalseLeft($position, 'not found');
   /** @psalm-trace $notFalseLeftDropsFalse */

   $leftNotFalseDropsFalse = Either::left($position)->leftNotFalse('not found');
   /** @psalm-trace $leftNotFalseDropsFalse */
}

/**
 * Two calls in a row, the way the library is meant to be used.
 *
 * Eleven of these twenty failed psalm at errorLevel 1 when box-returning
 * methods had conditional return types. psalm cannot decide `T is never` for
 * an ordinary T, so map() returned Option<U>|Option<never>, and the next typed
 * callback met the Option<never> half and reported ReservedWord and NoValue.
 * testTheFixtureHasNoOtherIssues fails if any of them does again.
 *
 * @param Option<string> $opt
 * @param Either<int, string> $either
 */
function chainsTypeCheck(?string $maybe, bool $flag, string $text, Option $opt, Either $either, int|false $position): void {
   $twoMaps = Option::some(5)->map(fn(int $n): string => "n{$n}")->map(fn(string $s): string => strtoupper($s));
   /** @psalm-trace $twoMaps */

   $twoMapsOnAnOption = $opt->map(fn(string $s): int => strlen($s))->map(fn(int $n): int => $n * 2);
   /** @psalm-trace $twoMapsOnAnOption */

   $notNullThenMap = Option::some($maybe)->notNull()->map(fn(string $s): string => strtoupper($s));
   /** @psalm-trace $notNullThenMap */

   $someNotNullThenMap = Option::someNotNull($maybe)->map(fn(string $s): string => strtoupper($s));
   /** @psalm-trace $someNotNullThenMap */

   $someNotFalsyThenMap = Option::someNotFalsy($text)->map(fn(string $s): int => strlen($s));
   /** @psalm-trace $someNotFalsyThenMap */

   $filterByABoolThenMap = $opt->filter($flag)->map(fn(string $s): int => strlen($s));
   /** @psalm-trace $filterByABoolThenMap */

   $filterByACallableThenMap = $opt->filter(fn(string $s) => $s !== '')->map(fn(string $s): int => strlen($s));
   /** @psalm-trace $filterByACallableThenMap */

   $orThenMap = $opt->or('fallback')->map(fn(string $s): int => strlen($s));
   /** @psalm-trace $orThenMap */

   $elseThenMap = $opt->else(Option::some('x'))->map(fn(string $s): int => strlen($s));
   /** @psalm-trace $elseThenMap */

   $mapThenValueOr = $opt->map(fn(string $s): int => strlen($s))->valueOr(0);
   /** @psalm-trace $mapThenValueOr */

   $notFalsyThenMap = Option::some($position)->notFalsy()->map(fn(int $n): int => $n + 1);
   /** @psalm-trace $notFalsyThenMap */

   $twoMapLefts = $either->mapLeft(fn(int $n): string => "n{$n}")->mapLeft(fn(string $s): int => strlen($s));
   /** @psalm-trace $twoMapLefts */

   // mapRight declared the unmapped right for any box that might be a Left
   $twoMapRights = $either->mapRight(fn(string $e): int => strlen($e))->mapRight(fn(int $n): int => $n * 2);
   /** @psalm-trace $twoMapRights */

   // filterLeft with a callable used to claim a Left could only become a Right
   $filterLeftByACallable = Either::left(10)->filterLeft(fn(int $x) => $x > 5, 'small');
   /** @psalm-trace $filterLeftByACallable */

   $filterLeftThenMapLeft = $either->filterLeft(fn(int $x) => $x > 5, 'small')->mapLeft(fn(int $n): int => $n + 1);
   /** @psalm-trace $filterLeftThenMapLeft */

   $orLeftThenMapLeft = $either->orLeft(0)->mapLeft(fn(int $n): int => $n + 1);
   /** @psalm-trace $orLeftThenMapLeft */

   $elseLeftThenMapLeft = $either->elseLeft(Either::left(0))->mapLeft(fn(int $n): int => $n + 1);
   /** @psalm-trace $elseLeftThenMapLeft */

   $notNullLeftThenMapLeft = Either::notNullLeft($maybe, 'missing')->mapLeft(fn(string $s): int => strlen($s));
   /** @psalm-trace $notNullLeftThenMapLeft */

   $twoMapSuccesses = $either->mapSuccess(fn(int $n): string => "n{$n}")->mapSuccess(fn(string $s): int => strlen($s));
   /** @psalm-trace $twoMapSuccesses */

   $flatMapLeftThenMapLeft = $either
      ->flatMapLeft(fn(int $n): Either => Either::left("n{$n}"))
      ->mapLeft(fn(string $s): int => strlen($s));
   /** @psalm-trace $flatMapLeftThenMapLeft */
}

function resultTypes(): void {
   $resultMapKeepsTheMappedType = Result::ok(5)->map(function(int $n): string { return "n{$n}"; });
   /** @psalm-trace $resultMapKeepsTheMappedType */

   $resultFromCallableCarriesTheThrowable = Result::fromCallable(function(): int { return 7; });
   /** @psalm-trace $resultFromCallableCarriesTheThrowable */

   // naming the class to catch types the error as that class
   $resultFromCallableTypesTheNamedClass = Result::fromCallable(function(): int { return 7; }, InvalidArgumentException::class);
   /** @psalm-trace $resultFromCallableTypesTheNamedClass */

   $resultMapSafelyTypesTheNamedClass = Result::ok(5)->mapSafely(function(int $n): string { return "n{$n}"; }, JsonException::class);
   /** @psalm-trace $resultMapSafelyTypesTheNamedClass */

   $resultValueOrThrowReturnsTheValue = Result::ok(41)->valueOrThrow();
   /** @psalm-trace $resultValueOrThrowReturnsTheValue */

   $resultToOptionKeepsTheValue = Result::ok(10)->toOption();
   /** @psalm-trace $resultToOptionKeepsTheValue */

   $optionToResultCarriesTheError = Option::fromArray(['hello' => 'world'], 'hello')->toResult('missing');
   /** @psalm-trace $optionToResultCarriesTheError */

   $resultOrIsAlwaysOk = Result::err('no')->or(0);
   /** @psalm-trace $resultOrIsAlwaysOk */

   // the step's error joins the chain's error type
   $resultFlatMapJoinsTheErrors = Result::ok(1)->flatMap(function(int $n): Result { return Result::err("rejected {$n}"); });
   /** @psalm-trace $resultFlatMapJoinsTheErrors */
}

/**
 * The same two-call chains as chainsTypeCheck(), for Result.
 *
 * @param Result<int, string> $result
 */
function resultChainsTypeCheck(?string $maybe, Result $result): void {
   $twoResultMaps = $result->map(fn(int $n): string => "n{$n}")->map(fn(string $s): int => strlen($s));
   /** @psalm-trace $twoResultMaps */

   $twoMapErrs = $result->mapErr(fn(string $e): int => strlen($e))->mapErr(fn(int $n): int => $n * 2);
   /** @psalm-trace $twoMapErrs */

   $resultFilterThenMap = $result->filter(fn(int $x) => $x > 5, 'small')->map(fn(int $n): int => $n + 1);
   /** @psalm-trace $resultFilterThenMap */

   $resultOrThenMap = $result->or(0)->map(fn(int $n): int => $n + 1);
   /** @psalm-trace $resultOrThenMap */

   $resultElseThenMap = $result->else(Result::ok(0))->map(fn(int $n): int => $n + 1);
   /** @psalm-trace $resultElseThenMap */

   $resultFlatMapThenMap = $result->flatMap(fn(int $n) => Result::ok("n{$n}"))->map(fn(string $s): int => strlen($s));
   /** @psalm-trace $resultFlatMapThenMap */

   $toResultThenMap = Option::someNotNull($maybe)->toResult('missing')->map(fn(string $s): int => strlen($s));
   /** @psalm-trace $toResultThenMap */
}
