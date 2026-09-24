<?php

declare(strict_types = 1);

namespace Optional\Tests;

use Error;
use Exception;
use Optional\Option;
use Optional\Result;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

/**
 * Option::do() and Result::do() with valueOrReturn(): a block of steps that
 * ends at the first empty box, the way Rust's `?` returns from a function.
 */
final class EarlyReturnTest extends TestCase {
   public function testTheBlockBoxesWhatItReturns(): void {
      $this->assertSame('Some(3)', (string) Option::do(fn() => Option::some(1)->valueOrReturn() + 2));
      $this->assertSame('Ok(3)', (string) Result::do(fn() => Result::ok(1)->valueOrReturn() + 2));
      $this->assertSame(5, Option::some(5)->valueOrReturn(), 'a value needs no block');
      $this->assertSame(5, Result::ok(5)->valueOrReturn(), 'a value needs no block');
   }

   public function testTheFirstEmptyOptionEndsTheBlock(): void {
      $steps = [];

      $box = Option::do(function() use (&$steps): int {
         $steps[] = 'first';
         $value = self::nothing()->valueOrReturn();
         $steps[] = 'never';

         return $value;
      });

      $this->assertSame('None', (string) $box);
      $this->assertSame(['first'], $steps, 'the step after the empty box never ran');
   }

   public function testTheFirstErrEndsTheBlockWithItsError(): void {
      $steps = [];
      $timeout = new RuntimeException('timeout');

      $box = Result::do(function() use (&$steps, $timeout): int {
         $steps[] = 'first';
         $value = self::failed($timeout)->valueOrReturn();
         $steps[] = 'never';

         return $value + self::failed('second')->valueOrReturn();
      });

      $this->assertSame($timeout, $box->errOr(null), 'the block keeps the very error it met');
      $this->assertSame(['first'], $steps);
   }

   public function testAnInnerBlockEndsOnItsOwn(): void {
      $outer = Result::do(function(): string {
         $inner = Result::do(fn() => self::failed('inner failed')->valueOrReturn());

         return 'outer saw ' . (string) $inner;
      });

      $this->assertSame("Ok('outer saw Err(\\'inner failed\\')')", (string) $outer);
   }

   /**
    * Each kind of box ends only its own kind of block, so an Option coming up
    * empty inside a Result::do() ends the Option::do() around both.
    */
   public function testAnEarlyReturnPassesThroughTheOtherKindOfBlock(): void {
      $option = Option::do(fn() => Result::do(fn() => self::nothing()->valueOrReturn()));
      $result = Result::do(fn() => Option::do(fn() => self::failed('outer')->valueOrReturn()));

      $this->assertSame('None', (string) $option);
      $this->assertSame("Err('outer')", (string) $result);
   }

   public function testACatchForExceptionsInsideTheBlockCannotStopIt(): void {
      $box = Result::do(function(): string {
         try {
            return (string) self::failed('kept')->valueOrReturn();
         } catch (Exception $_e) {
            return 'swallowed';
         }
      });

      $this->assertSame("Err('kept')", (string) $box);
   }

   /**
    * fromCallable() and mapSafely() catch what a callable throws. An early
    * return is not a failure of that callable, so it passes through them to
    * the block, whatever class they were told to catch.
    */
   public function testFromCallableAndMapSafelyLetItThrough(): void {
      $viaFromCallable = Result::do(fn() => Result::fromCallable(fn() => self::failed('first')->valueOrReturn(), Throwable::class));
      $viaMapSafely = Result::do(fn() => Result::ok(1)->mapSafely(fn() => self::failed('second')->valueOrReturn(), Error::class));
      $viaOptionFromCallable = Option::do(fn() => Option::fromCallable(fn() => self::nothing()->valueOrReturn(), Throwable::class));
      $viaOptionMapSafely = Option::do(fn() => Option::some(1)->mapSafely(fn() => self::nothing()->valueOrReturn(), Error::class));

      $this->assertSame("Err('first')", (string) $viaFromCallable);
      $this->assertSame("Err('second')", (string) $viaMapSafely);
      $this->assertSame('None', (string) $viaOptionFromCallable);
      $this->assertSame('None', (string) $viaOptionMapSafely);
   }

   public function testAnythingElseTheBlockThrowsPropagates(): void {
      $this->expectException(RuntimeException::class);
      $this->expectExceptionMessage('a real failure');

      $_ = Result::do(function(): int { throw new RuntimeException('a real failure'); });
   }

   public function testValueOrReturnOutsideABlockSaysSo(): void {
      $this->expectException(Error::class);
      $this->expectExceptionMessage('Result::valueOrReturn() found no value, and there is no Result::do() around it to return from');

      $_ = self::failed('nowhere to go')->valueOrReturn();
   }

   /**
    * @return Option<int>
    */
   private static function nothing(): Option {
      return Option::none();
   }

   /**
    * @template E
    * @param E $error
    * @return Result<int, E>
    */
   private static function failed(mixed $error): Result {
      return Result::err($error);
   }
}
