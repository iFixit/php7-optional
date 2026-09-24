<?php

declare(strict_types = 1);

namespace Optional\Tests;

use Optional\Option;
use Optional\Result;
use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * A call that returns a new box and has its result dropped did nothing.
 *
 * `$option->map($f);` on its own line leaves `$option` exactly as it was, and
 * psalm cannot say so: it reports a dropped result only for a method it knows
 * changes nothing, and a method that runs your callback cannot promise that.
 * PHP 8.5's #[\NoDiscard] makes the engine warn instead. PHP 8.4 ignores the
 * attribute.
 */
final class NoDiscardTest extends TestCase {
   /**
    * Every method that hands back a new box carries the attribute, factories
    * included, and nothing else does. The tap family returns a box too, but
    * running it for the side effect and dropping the box is the whole point of
    * calling it.
    */
   public function testEveryMethodThatReturnsABoxIsMarked(): void {
      $checked = 0;

      foreach ([Option::class, Result::class] as $class) {
         foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            // PHP 8.4 renders a `self` return type as "self", 8.5 as the class name
            $returnsABox = in_array((string) $method->getReturnType(), ['self', $class, Option::class, Result::class], true);
            $expected = $returnsABox && !str_starts_with($method->getName(), 'tap');
            $marked = $method->getAttributes(\NoDiscard::class) !== [];

            $this->assertSame(
               $expected,
               $marked,
               sprintf(
                  '%s::%s() %s #[\NoDiscard]',
                  $class,
                  $method->getName(),
                  $expected ? 'returns a new box, so it needs' : 'does not return a new box, so it must not carry'
               )
            );

            $checked++;
         }
      }

      $this->assertGreaterThan(35, $checked, 'Reflection found suspiciously few methods');
   }

   #[RequiresPhp('>= 8.5')]
   public function testPhp85WarnsWhenTheResultIsDropped(): void {
      $warnings = [];

      set_error_handler(function(int $_level, string $message) use (&$warnings): bool {
         $warnings[] = $message;
         return true;
      });

      try {
         Option::some(1)->map(fn(int $n) => $n + 1);
      } finally {
         restore_error_handler();
      }

      $this->assertCount(1, $warnings);
      $this->assertStringContainsString('Optional\Option::map() should either be used', $warnings[0]);
      $this->assertStringContainsString('a box never changes after it is built', $warnings[0]);
   }

   /**
    * A factory dropped on its own line is almost always a missing return, as
    * in `if ($input === '') { Result::err('empty'); }`, which then falls
    * through to the success path.
    */
   #[RequiresPhp('>= 8.5')]
   public function testPhp85WarnsWhenAFactoryIsDropped(): void {
      $warnings = [];

      set_error_handler(function(int $_level, string $message) use (&$warnings): bool {
         $warnings[] = $message;
         return true;
      });

      try {
         Result::err('empty');
         Option::fromCallable(fn() => throw new \RuntimeException('swallowed'));
      } finally {
         restore_error_handler();
      }

      $this->assertCount(2, $warnings);
      $this->assertStringContainsString('Optional\Result::err() should either be used', $warnings[0]);
      $this->assertStringContainsString('a missing return is the usual cause', $warnings[0]);
      $this->assertStringContainsString('Optional\Option::fromCallable() should either be used', $warnings[1]);
   }
}
