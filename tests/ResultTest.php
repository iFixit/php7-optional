<?php

declare(strict_types = 1);

namespace Optional\Tests;

use Optional\Exceptions\MissingValueException;
use Optional\Option;
use Optional\Result;
use PHPUnit\Framework\TestCase;

final class ResultTest extends TestCase {
   /**
    * Result takes Option's names wherever the idea is the same, down to the
    * parameter names, which PHP 8 callers can pass by name. A reader who has
    * learned one box can guess the other. else, elseCreate and match are left
    * out on purpose: their parameters name the box or the side they take.
    */
   public function testSharesOptionsMethodAndParameterNames(): void {
      $shared = [
         'map', 'mapSafely', 'flatMap', 'filter', 'valueOr', 'valueOrCreate', 'valueOrThrow', 'toNullable',
         'contains', 'exists', 'or', 'orCreate', 'tap', 'fromCallable',
      ];

      foreach ($shared as $name) {
         $names = fn(\ReflectionMethod $method): array => array_map(
            fn(\ReflectionParameter $parameter): string => $parameter->getName(),
            $method->getParameters()
         );
         $option = $names(new \ReflectionMethod(Option::class, $name));
         $result = $names(new \ReflectionMethod(Result::class, $name));

         $this->assertSame(
            $option,
            array_slice($result, 0, count($option)),
            "Result::{$name}() has to take Option::{$name}()'s parameters, in order, before any of its own"
         );
      }
   }

   public function testOkAndErr(): void {
      $this->assertTrue(Result::ok(5)->isOk());
      $this->assertFalse(Result::ok(5)->isErr());
      $this->assertTrue(Result::err('no')->isErr());
      $this->assertFalse(Result::err('no')->isOk());
   }

   public function testANullValueIsStillASuccess(): void {
      $this->assertTrue(Result::ok(null)->isOk(), 'ok(null) is a success that holds null');
      $this->assertNull(Result::ok(null)->valueOr('x'));
   }

   public function testTheBoxIsAReadonlyClass(): void {
      $this->assertTrue((new \ReflectionClass(Result::class))->isReadOnly(), 'PHP enforces the immutability only while the class is readonly');
   }

   public function testValueOrAndErrOr(): void {
      $this->assertSame(5, Result::ok(5)->valueOr(0));
      $this->assertSame(0, Result::err('no')->valueOr(0));

      $this->assertSame('no', Result::err('no')->errOr('fine'));
      $this->assertSame('fine', Result::ok(5)->errOr('fine'));
   }

   public function testValueOrCreateHandsTheFactoryTheError(): void {
      $this->assertSame('failed: timeout', Result::err('timeout')->valueOrCreate(fn(string $e) => "failed: {$e}"));

      $ran = false;
      $value = Result::ok(1)->valueOrCreate(function() use (&$ran): int {
         $ran = true;
         return 0;
      });

      $this->assertFalse($ran, 'the factory must not run on a success');
      $this->assertSame(1, $value);
   }

   public function testToNullable(): void {
      $this->assertSame('Ada', Result::ok('Ada')->toNullable());
      $this->assertNull(Result::err('timeout')->toNullable());
   }

   public function testToOption(): void {
      $this->assertSame(10, Result::ok(10)->toOption()->valueOr(-1));
      $this->assertFalse(Result::err('bad id')->toOption()->hasValue());
      $this->assertTrue(Result::ok(null)->toOption()->hasValue(), 'a success holding null is a Some(null)');
   }

   public function testOptionToResult(): void {
      $this->assertSame(7, Option::some(7)->toResult('missing')->valueOr(-1));
      $this->assertSame('missing', Option::none()->toResult('missing')->errOr('x'));
   }

   public function testValueOrThrowReturnsTheValue(): void {
      $this->assertSame(1, Result::ok(1)->valueOrThrow());
      $this->assertSame(1, Result::ok(1)->valueOrThrow(fn() => new \LogicException('unreachable')));
   }

   /**
    * The point of rethrowing rather than wrapping: the trace still says where
    * the failure happened, not where somebody finally looked at it.
    */
   public function testValueOrThrowRethrowsAThrowableErrorItself(): void {
      $original = new \RuntimeException('the api said no');

      try {
         Result::err($original)->valueOrThrow();
      } catch (\RuntimeException $e) {
         $caught = $e;
      }

      $this->assertSame($original, $caught, 'the stored exception is rethrown, not a copy of it');
   }

   public function testValueOrThrowHandsTheFactoryTheError(): void {
      $this->expectException(\RuntimeException::class);
      $this->expectExceptionMessage('could not load: timeout');

      Result::err('timeout')->valueOrThrow(fn(string $error) => new \RuntimeException("could not load: {$error}"));
   }

   public function testValueOrThrowWithNoFactoryNamesAPlainError(): void {
      $this->expectException(MissingValueException::class);
      $this->expectExceptionMessage("Result is an Err: 'timeout'");

      Result::err('timeout')->valueOrThrow();
   }

   public function testValueOrThrowWithNoFactoryDescribesAnErrorWithNoStringForm(): void {
      $this->expectException(MissingValueException::class);
      $this->expectExceptionMessage('Result is an Err: array');

      Result::err(['code' => 404])->valueOrThrow();
   }

   public function testMatchRunsOnlyTheSideItHas(): void {
      /** @var Result<int, string> $ok typed as a caller would hold it, so psalm allows both callbacks */
      $ok = Result::ok(4);
      /** @var Result<int, string> $err */
      $err = Result::err('no');

      $this->assertSame('ok:4', $ok->match(ok: fn(int $x) => "ok:{$x}", err: fn(string $e) => "err:{$e}"));
      $this->assertSame('err:no', $err->match(ok: fn(int $x) => "ok:{$x}", err: fn(string $e) => "err:{$e}"));
   }

   public function testContainsComparesTheSuccessStrictly(): void {
      $this->assertTrue(Result::ok(10)->contains(10));
      $this->assertFalse(Result::ok(10)->contains('10'), 'contains compares with ===');
      $this->assertFalse(Result::err(10)->contains(10), 'the error is not the value');
   }

   public function testExists(): void {
      $isAdult = fn(int $age): bool => $age >= 18;

      $this->assertTrue(Result::ok(30)->exists($isAdult));
      $this->assertFalse(Result::ok(12)->exists($isAdult));

      $ran = false;
      $this->assertFalse(Result::err('no age')->exists(function() use (&$ran): bool {
         $ran = true;
         return true;
      }));
      $this->assertFalse($ran, 'exists must not run its callable on an Err');
   }

   public function testMapTouchesOnlyTheSuccess(): void {
      $this->assertSame(25, Result::ok(5)->map(fn(int $x) => $x * $x)->valueOr(-1));

      $ran = false;
      $err = Result::err('no')->map(function() use (&$ran): int {
         $ran = true;
         return 1;
      });

      $this->assertFalse($ran, 'map must not run on an Err');
      $this->assertSame('no', $err->errOr('x'));
   }

   public function testMapErrTouchesOnlyTheError(): void {
      $this->assertSame('request failed: timeout', Result::err('timeout')->mapErr(fn(string $e) => "request failed: {$e}")->errOr('x'));

      $ran = false;
      $ok = Result::ok(1)->mapErr(function() use (&$ran): string {
         $ran = true;
         return 'x';
      });

      $this->assertFalse($ran, 'mapErr must not run on an Ok');
      $this->assertSame(1, $ok->valueOr(-1));
   }

   public function testMapSafelyCatchesThrowableByDefault(): void {
      $fromException = Result::ok(1)->mapSafely(function(int $_x): int { throw new \Exception('boom'); });
      $this->assertSame('boom', $fromException->mapErr(fn(\Throwable $e) => $e->getMessage())->errOr('x'));

      $fromError = Result::ok(1)->mapSafely(function(int $_x): int { throw new \TypeError('not an exception'); });
      $this->assertInstanceOf(\TypeError::class, $fromError->errOr(null), 'an Error lands in the error rather than escaping');
   }

   public function testMapSafelyCatchesTheNamedClassAndItsSubclasses(): void {
      $named = Result::ok(1)->mapSafely(function(int $_x): int { throw new \OutOfBoundsException('x'); }, \OutOfBoundsException::class);
      $this->assertTrue($named->isErr());

      $subclass = Result::ok(1)->mapSafely(function(int $_x): int { throw new \OutOfBoundsException('x'); }, \RuntimeException::class);
      $this->assertTrue($subclass->isErr(), 'a subclass of the named class is caught, as a catch clause would');
   }

   public function testMapSafelyLetsAnyOtherClassThrough(): void {
      $this->expectException(\LogicException::class);

      $_ = Result::ok(1)->mapSafely(function(int $_x): int { throw new \LogicException('a bug'); }, \OutOfBoundsException::class);
   }

   public function testFlatMapStopsAtTheFirstFailure(): void {
      // a callback that can return either side needs its type spelled out, as the flatMap docblock says
      /** @var \Closure(int):Result<int, string> $half */
      $half = fn(int $n): Result => $n % 2 === 0 ? Result::ok(intdiv($n, 2)) : Result::err("{$n} is odd");

      $this->assertSame(5, Result::ok(20)->flatMap($half)->flatMap($half)->valueOr(-1));
      $this->assertSame('5 is odd', Result::ok(10)->flatMap($half)->flatMap($half)->errOr('x'));

      $ran = false;
      $stopped = Result::err('no input')->flatMap(function() use (&$ran): Result {
         $ran = true;
         return Result::ok(1);
      });
      $this->assertFalse($ran, 'flatMap must not run on an Err');
      $this->assertSame('no input', $stopped->errOr('x'));
   }

   public function testFilter(): void {
      $isAdult = fn(int $age): bool => $age >= 18;

      $this->assertSame(30, Result::ok(30)->filter($isAdult, 'too young')->valueOr(-1));
      $this->assertSame('too young', Result::ok(12)->filter($isAdult, 'too young')->errOr('x'));
      $this->assertSame(12, Result::ok(12)->filter(true, 'too young')->valueOr(-1));
      $this->assertSame('too young', Result::ok(12)->filter(false, 'too young')->errOr('x'));

      $ran = false;
      $untouched = Result::err('no age')->filter(function() use (&$ran): bool {
         $ran = true;
         return true;
      }, 'too young');
      $this->assertFalse($ran, 'filter must not run its callable on an Err');
      $this->assertSame('no age', $untouched->errOr('x'), 'an Err keeps its own error');
   }

   /**
    * @psalm-suppress InvalidArgument these callbacks break callable(T):bool on purpose
    */
   public function testExistsReadsTheCallbackResultTheWayIfDoes(): void {
      $this->assertTrue(Result::ok(5)->exists(fn() => 1));
      $this->assertFalse(Result::ok(5)->exists(fn() => '0'));
      $this->assertFalse(Result::err('e')->exists(fn() => 1));
   }

   /**
    * The same rule as Option::filter: a callback's result counts the way `if`
    * counts it, so a flag of 1 or '1' keeps the value.
    *
    * @psalm-suppress InvalidArgument these callbacks break callable(T):bool on purpose
    */
   public function testFilterReadsTheCallbackResultTheWayIfDoes(): void {
      $this->assertSame(5, Result::ok(5)->filter(fn() => 1, 'e')->valueOr(-1));
      $this->assertSame(5, Result::ok(5)->filter(fn() => '1', 'e')->valueOr(-1));
      $this->assertSame('e', Result::ok(5)->filter(fn() => '0', 'e')->errOr('x'));
      $this->assertSame('e', Result::ok(5)->filter(fn() => null, 'e')->errOr('x'));
   }

   /**
    * The same as Option: a by-reference callback gets a copy, on both sides.
    */
   public function testCallbacksMayTakeTheirArgumentByReference(): void {
      $ok = Result::ok([1, 2, 3]);
      $err = Result::err([4, 5, 6]);
      $last = function(array &$values): int {
         return (int) end($values);
      };

      $this->assertSame(3, $ok->map(end(...))->valueOr(0));
      $this->assertSame(6, $err->mapErr(end(...))->errOr(0));
      $this->assertSame(3, $ok->mapSafely(end(...))->valueOr(0));
      $this->assertSame(3, $ok->flatMap(fn(array $v) => Result::ok($last($v)))->valueOr(0));
      $this->assertSame(3, $ok->match(ok: $last, err: $last));
      $this->assertSame(6, $err->match(ok: $last, err: $last));
      $this->assertSame(6, $err->valueOrCreate($last));
      $this->assertSame(6, $err->orCreate($last)->valueOr(0));
      $this->assertSame(6, $err->elseCreate(fn(array $e) => Result::ok($last($e)))->valueOr(0));
      $this->assertTrue($ok->filter(function(array &$values): bool { return end($values) === 3; }, 'no')->isOk());
      $this->assertTrue($ok->exists(function(array &$values): bool { return end($values) === 3; }));
      $_ = $ok->tap(function(array &$values): void { end($values); });
      $_ = $err->tapErr(function(array &$values): void { end($values); });

      try {
         $err->valueOrThrow(function(array &$values): \RuntimeException { return new \RuntimeException((string) end($values)); });
      } catch (\RuntimeException $e) {
         $this->assertSame('6', $e->getMessage());
      }

      $this->assertSame([1, 2, 3], $ok->valueOr([]), 'the callbacks changed a copy, never the box');
      $this->assertSame([4, 5, 6], $err->errOr([]), 'the callbacks changed a copy, never the box');
   }

   /**
    * The same rule as Option::filter(): a string is never called.
    *
    * @psalm-suppress ForbiddenCode the point is code without strict types
    */
   public function testFilterNeverCallsAString(): void {
      ob_start();
      /** @var mixed $kept */
      $kept = eval("return \\Optional\\Result::ok('held')->filter('var_dump', 'rejected');");
      $printed = (string) ob_get_clean();

      $this->assertSame('', $printed, 'var_dump() must not run');
      $this->assertInstanceOf(Result::class, $kept);
      $this->assertSame("Ok('held')", (string) $kept);
   }

   public function testAllKeepsTheKeysOrStopsAtTheFirstErr(): void {
      $this->assertSame(['a' => 1, 'b' => 2], Result::all(['a' => Result::ok(1), 'b' => Result::ok(2)])->valueOr([]));
      $this->assertSame([], Result::all([])->valueOr(['not', 'empty']));

      $pulled = 0;
      $results = (function() use (&$pulled): \Generator {
         foreach ([Result::ok(1), Result::err('second'), Result::err('third')] as $result) {
            $pulled++;
            yield $result;
         }
      })();

      $this->assertSame('second', Result::all($results)->errOr(''));
      $this->assertSame(2, $pulled, 'all() stops reading at the first Err');
   }

   public function testPartitionSplitsValuesFromErrorsAndKeepsTheKeys(): void {
      [$values, $errors] = Result::partition([Result::ok(1), Result::err('x'), Result::ok(3), Result::err('y')]);

      $this->assertSame([0 => 1, 2 => 3], $values);
      $this->assertSame([1 => 'x', 3 => 'y'], $errors, 'each error still says which input it came from');
      $this->assertSame([[], []], Result::partition([]));
   }

   public function testValueOrThrowWithAMessageNamesTheErrorAndKeepsItAsTheCause(): void {
      $this->assertSame(1, Result::ok(1)->valueOrThrow('never used'));

      try {
         $_ = self::failure('timeout')->valueOrThrow('could not load');
         $this->fail('valueOrThrow() on an Err must throw');
      } catch (MissingValueException $e) {
         $this->assertSame("could not load: 'timeout'", $e->getMessage());
         $this->assertNull($e->getPrevious(), 'a string error is named, not chained');
      }

      $cause = new \RuntimeException('the api said no');

      try {
         $_ = self::failure($cause)->valueOrThrow('could not load');
         $this->fail('valueOrThrow() on an Err must throw');
      } catch (MissingValueException $e) {
         $this->assertSame('could not load: RuntimeException: the api said no', $e->getMessage());
         $this->assertSame($cause, $e->getPrevious(), 'a Throwable error becomes the cause');
      }
   }

   public function testOrAndOrCreateRecoverOnlyAnErr(): void {
      $this->assertSame(0, Result::err('timeout')->or(0)->valueOr(-1));

      $ok = Result::ok(5);
      $this->assertSame($ok, $ok->or(0), 'an Ok comes back as it was');

      $this->assertSame('default, after timeout', Result::err('timeout')->orCreate(fn(string $e) => "default, after {$e}")->valueOr('x'));

      $ran = false;
      $kept = $ok->orCreate(function() use (&$ran): int {
         $ran = true;
         return 0;
      });
      $this->assertFalse($ran, 'orCreate must not run on an Ok');
      $this->assertSame($ok, $kept);
   }

   public function testElseAndElseCreateRecoverOnlyAnErr(): void {
      $fromDisk = Result::ok('from disk');

      $this->assertSame('from disk', Result::err('cache miss')->else($fromDisk)->valueOr('x'));

      $cached = Result::ok('from cache');
      $this->assertSame($cached, $cached->else($fromDisk), 'an Ok comes back as it was');

      $retried = Result::err('timeout')->elseCreate(fn(string $e) => Result::ok("second try, after {$e}"));
      $this->assertSame('second try, after timeout', $retried->valueOr('x'));

      $ran = false;
      $kept = $cached->elseCreate(function() use (&$ran): Result {
         $ran = true;
         return Result::ok('unreachable');
      });
      $this->assertFalse($ran, 'elseCreate must not run on an Ok');
      $this->assertSame($cached, $kept);
   }

   public function testTapAndTapErrRunOnTheirSideAndReturnTheSameBox(): void {
      $seen = [];
      /** @var Result<int, string> $ok */
      $ok = Result::ok(5);
      /** @var Result<int, string> $err */
      $err = Result::err('no');

      $this->assertSame($ok, $ok->tap(function(int $x) use (&$seen): void { $seen[] = "tap {$x}"; }));
      $this->assertSame($ok, $ok->tapErr(function(string $e) use (&$seen): void { $seen[] = "tapErr {$e}"; }));
      $this->assertSame($err, $err->tap(function(int $x) use (&$seen): void { $seen[] = "tap {$x}"; }));
      $this->assertSame($err, $err->tapErr(function(string $e) use (&$seen): void { $seen[] = "tapErr {$e}"; }));

      $this->assertSame(['tap 5', 'tapErr no'], $seen);
   }

   public function testFromCallable(): void {
      $this->assertSame(7, Result::fromCallable(fn() => 7)->valueOr(-1));

      $failed = Result::fromCallable(function(): int { throw new \Exception('boom'); });
      $this->assertSame('boom', $failed->mapErr(fn(\Throwable $e) => $e->getMessage())->errOr('x'));

      $fromError = Result::fromCallable(function(): int { throw new \TypeError('not an exception'); });
      $this->assertInstanceOf(\TypeError::class, $fromError->errOr(null), 'Throwable is caught by default, since the box keeps it');
   }

   public function testFromCallableCatchesTheNamedClassAndItsSubclasses(): void {
      $named = Result::fromCallable(function(): int { throw new \OutOfBoundsException('no user 5'); }, \OutOfBoundsException::class);
      $this->assertSame('no user 5', $named->mapErr(fn(\OutOfBoundsException $e) => $e->getMessage())->errOr('x'));

      $subclass = Result::fromCallable(function(): int { throw new \OutOfBoundsException('no user 5'); }, \RuntimeException::class);
      $this->assertTrue($subclass->isErr(), 'a subclass of the named class is caught, as a catch clause would');
   }

   public function testFromCallableLetsAnyOtherClassThrough(): void {
      $this->expectException(\LogicException::class);

      $_ = Result::fromCallable(function(): int { throw new \LogicException('a bug'); }, \OutOfBoundsException::class);
   }

   public function testToStringShowsAnyValueWithoutWarning(): void {
      $this->assertSame('Ok(5)', (string) Result::ok(5));
      $this->assertSame("Err('no')", (string) Result::err('no'));
      $this->assertSame('Ok(null)', (string) Result::ok(null));
      $this->assertSame('Ok(false)', (string) Result::ok(false));
      $this->assertSame('Ok(array)', (string) Result::ok([1, 2]), 'an array is shown by its type');
      $this->assertSame('Ok(stdClass)', (string) Result::ok(new \stdClass()), 'an object with no string form is shown by its class');
      $this->assertSame('Err(RuntimeException: boom)', (string) Result::err(new \RuntimeException('boom')), 'an exception by its class and message, not its trace');
   }

   /**
    * An Err psalm cannot see is one, so a test can check what happens after
    *
    * @template E
    * @param E $error
    * @return Result<int, E>
    */
   private static function failure(mixed $error): Result {
      return Result::err($error);
   }
}
