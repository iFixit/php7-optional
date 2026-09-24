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

function optionTypes(): void {
   // map used to erase the mapped type entirely and report Option<never>
   $mapKeepsTheMappedType = Option::some(5)->map(function(int $n): string { return "n{$n}"; });
   /** @psalm-trace $mapKeepsTheMappedType */

   // mapping over an empty box gives an empty box, and psalm should know
   $mapOnNoneStaysNone = Option::none()->map(function(): string { return 'unreachable'; });
   /** @psalm-trace $mapOnNoneStaysNone */

   // a false condition yields a None whatever the box held
   $filterFalseIsNone = Option::some(5)->filter(false);
   /** @psalm-trace $filterFalseIsNone */

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

   $tapReturnsTheSameBox = Option::some(5)->tap(function(int $_n): void {});
   /** @psalm-trace $tapReturnsTheSameBox */

   $someNotFalsyKeepsTheType = Option::someNotFalsy('Ada');
   /** @psalm-trace $someNotFalsyKeepsTheType */

   $fromCallableKeepsTheReturnType = Option::fromCallable(function(): int { return 7; });
   /** @psalm-trace $fromCallableKeepsTheReturnType */
}

function eitherTypes(): void {
   // orRight had its two branches swapped, so it claimed the alternative
   // for a box that keeps its original value
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

   $mapLeftOnARightStaysRight = Either::right('err')->mapLeft(function(): string { return 'unreachable'; });
   /** @psalm-trace $mapLeftOnARightStaysRight */

   $mapLeftKeepsTheMappedType = Either::left(5)->mapLeft(function(int $n): string { return "n{$n}"; });
   /** @psalm-trace $mapLeftKeepsTheMappedType */

   // notFalsyRight declared a flat union where its twin narrows
   $notFalsyRightNarrowsOnFalsy = Either::notFalsyRight('', 'no error');
   /** @psalm-trace $notFalsyRightNarrowsOnFalsy */

   $notFalsyLeftNarrowsOnFalsy = Either::notFalsyLeft('', 'was falsy');
   /** @psalm-trace $notFalsyLeftNarrowsOnFalsy */

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
