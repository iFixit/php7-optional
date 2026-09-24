<?php

declare(strict_types = 1);

namespace Optional\Tests;

use Optional\Exceptions\MissingValueException;
use Optional\Option;
use Optional\Result;
use PHPUnit\Framework\TestCase;

final class ResultTest extends TestCase {
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
      $this->expectExceptionMessage('Result is an Err: timeout');

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

      Result::fromCallable(function(): int { throw new \LogicException('a bug'); }, \OutOfBoundsException::class);
   }

   public function testToStringShowsAnyValueWithoutWarning(): void {
      $this->assertSame('Ok(5)', (string) Result::ok(5));
      $this->assertSame('Err(no)', (string) Result::err('no'));
      $this->assertSame('Ok(null)', (string) Result::ok(null));
      $this->assertSame('Ok(false)', (string) Result::ok(false));
      $this->assertSame('Ok(array)', (string) Result::ok([1, 2]), 'an array is shown by its type');
      $this->assertSame('Ok(stdClass)', (string) Result::ok(new \stdClass()), 'an object with no string form is shown by its class');
      $this->assertSame('Err(RuntimeException: boom)', (string) Result::err(new \RuntimeException('boom')), 'an exception by its class and message, not its trace');
   }
}
