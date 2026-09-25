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

   // fromArray added Option<mixed> to every call site's type, as Option<'world'>|Option<mixed>
   $fromArrayKeepsTheValueType = Option::fromArray(['hello' => 'world'], 'hello');
   /** @psalm-trace $fromArrayKeepsTheValueType */

   $valueOrThrowReturnsTheValue = Option::some(41)->valueOrThrow(function(): Exception { return new RuntimeException('x'); });
   /** @psalm-trace $valueOrThrowReturnsTheValue */

   $valueOrThrowWithNoFactory = Option::some(41)->valueOrThrow();
   /** @psalm-trace $valueOrThrowWithNoFactory */

   $valueOrThrowWithAMessage = Option::some(41)->valueOrThrow('no id for this device');
   /** @psalm-trace $valueOrThrowWithAMessage */

   $tapReturnsTheSameBox = Option::some(5)->tap(function(int $_n): void {});
   /** @psalm-trace $tapReturnsTheSameBox */

   // PHPStan once rejected a callback that returns something, such as a log call
   $tapTakesACallbackThatReturns = Option::some(5)->tap(fn(int $n): bool => error_log((string) $n));
   /** @psalm-trace $tapTakesACallbackThatReturns */

   // PHPStan once dropped the fallback, so a later null check looked redundant
   $valueOrKeepsTheFallback = Option::some(5)->valueOr(null);
   /** @psalm-trace $valueOrKeepsTheFallback */

   $someNotFalsyKeepsTheType = Option::someNotFalsy('Ada');
   /** @psalm-trace $someNotFalsyKeepsTheType */

   $fromCallableKeepsTheReturnType = Option::fromCallable(function(): int { return 7; });
   /** @psalm-trace $fromCallableKeepsTheReturnType */

   $firstWithAPredicateKeepsTheType = Option::first(['a', 'bb'], function(string $s): bool { return strlen($s) > 1; });
   /** @psalm-trace $firstWithAPredicateKeepsTheType */

   $orCreateJoinsTheFallback = Option::some(5)->orCreate(fn(): string => 'six');
   /** @psalm-trace $orCreateJoinsTheFallback */

   $tapNoneReturnsTheSameBox = Option::some(5)->tapNone(function(): void {});
   /** @psalm-trace $tapNoneReturnsTheSameBox */
}

/**
 * The entrances exist to drop the missing value, so the type has to drop it
 * too. On 3.0, someNotNull() on a ?string inferred Option<null|string>, so
 * psalm reported strlen(Option::someNotNull($maybe)->valueOr('x')) as a
 * PossiblyNullArgument.
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

   $someNotFalseDropsFalse = Option::someNotFalse($position);
   /** @psalm-trace $someNotFalseDropsFalse */

   $notFalseDropsFalse = Option::some($position)->notFalse();
   /** @psalm-trace $notFalseDropsFalse */

}

/**
 * Two calls in a row, the way the library is meant to be used.
 *
 * Most of these once failed psalm at errorLevel 1, when box-returning methods
 * had conditional return types. psalm cannot decide `T is never` for an
 * ordinary T, so map() returned Option<U>|Option<never>, and the next typed
 * callback met the Option<never> half and reported ReservedWord and NoValue.
 * testTheFixtureHasNoOtherIssues fails if any of them does again.
 *
 * @param Option<string> $opt
 */
function chainsTypeCheck(?string $maybe, bool $flag, string $text, Option $opt, int|false $position): void {
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

   // a callback that returns a Some on one branch and a None on the other
   $flatMapWithBothBranches = $opt->flatMap(fn(string $s) => $s === '' ? Option::none() : Option::some($s));
   /** @psalm-trace $flatMapWithBothBranches */

   $elseCreateWithBothBranches = $opt->elseCreate(fn() => $flag ? Option::some('fallback') : Option::none());
   /** @psalm-trace $elseCreateWithBothBranches */

   // an early-return block keeps the type of each value it unwraps
   $valueOrReturnKeepsTheType = $opt->valueOrReturn();
   /** @psalm-trace $valueOrReturnKeepsTheType */

   $optionDoBlock = Option::do(fn() => strlen($opt->valueOrReturn()));
   /** @psalm-trace $optionDoBlock */

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

   $resultOrCreateIsAlwaysOk = Result::err('no')->orCreate(fn(string $e): int => strlen($e));
   /** @psalm-trace $resultOrCreateIsAlwaysOk */

   $resultTapReturnsTheSameBox = Result::ok(5)->tap(function(int $_n): void {});
   /** @psalm-trace $resultTapReturnsTheSameBox */

   $resultTapErrReturnsTheSameBox = Result::err('no')->tapErr(function(string $_e): void {});
   /** @psalm-trace $resultTapErrReturnsTheSameBox */

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

   // a callback that returns an Ok on one branch and an Err on the other
   $resultFlatMapWithBothBranches = $result->flatMap(fn(int $n) => $n > 0 ? Result::ok($n) : Result::err('not positive'));
   /** @psalm-trace $resultFlatMapWithBothBranches */

   $resultElseCreateWithBothBranches = $result->elseCreate(fn(string $e) => $e === '' ? Result::ok(0) : Result::err($e));
   /** @psalm-trace $resultElseCreateWithBothBranches */

   // a batch keeps its keys and the value and error types of its boxes
   $resultAllKeepsTheKeys = Result::all(['a' => $result, 'b' => $result]);
   /** @psalm-trace $resultAllKeepsTheKeys */

   $resultPartition = Result::partition([$result, $result]);
   /** @psalm-trace $resultPartition */

   $optionAllOfAList = Option::all([Option::someNotNull($maybe), Option::someNotNull($maybe)]);
   /** @psalm-trace $optionAllOfAList */

   // the block's errors are not part of its type, so the error side is mixed
   $resultDoBlock = Result::do(fn() => $result->valueOrReturn() * 2);
   /** @psalm-trace $resultDoBlock */

   $resultElseThenMap = $result->else(Result::ok(0))->map(fn(int $n): int => $n + 1);
   /** @psalm-trace $resultElseThenMap */

   $resultFlatMapThenMap = $result->flatMap(fn(int $n) => Result::ok("n{$n}"))->map(fn(string $s): int => strlen($s));
   /** @psalm-trace $resultFlatMapThenMap */

   $toResultThenMap = Option::someNotNull($maybe)->toResult('missing')->map(fn(string $s): int => strlen($s));
   /** @psalm-trace $toResultThenMap */
}
