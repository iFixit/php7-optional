<?php

declare(strict_types = 1);

namespace Optional\Tests;

use Optional\Exceptions\MissingValueException;
use Optional\Option;
use PHPUnit\Framework\TestCase;

final class OptionTest extends TestCase {
   public function testCreateAndCheckExistence(): void {
      $noneOption = Option::none();

      $this->assertFalse($noneOption->hasValue());

      $someThing = Option::some(1);
      $someNullable = Option::some(null);
      $someClass = Option::Some(new SomeObject());

      $this->assertTrue($someThing->hasValue());
      $this->assertTrue($someNullable->hasValue());
      $this->assertTrue($someClass->hasValue());

      $noname = Option::fromArray(['name' => 'value'], 'noname');
      $this->assertFalse($noname->hasValue());

      $name = Option::fromArray(['name' => 'value'], 'name');
      $this->assertTrue($name->hasValue());

      $none = Option::someNotNull(null);
      $some = Option::someNotNull('');

      $this->assertFalse($none->hasValue());
      $this->assertTrue($some->hasValue());
   }

   public function testTheBoxIsAReadonlyClass(): void {
      $this->assertTrue((new \ReflectionClass(Option::class))->isReadOnly(), 'PHP enforces the immutability only while the class is readonly');
   }

   public function testValueOrThrowWithNoFactoryReturnsTheValue(): void {
      $this->assertSame(1, Option::some(1)->valueOrThrow());
   }

   public function testValueOrThrowWithNoFactoryThrowsMissingValueException(): void {
      $this->expectException(MissingValueException::class);
      $this->expectExceptionMessage("Value is missing.");

      Option::none()->valueOrThrow();
   }

   public function testGettingValue(): void {
      $noneOption = Option::none();

      $this->assertSame($noneOption->valueOr(-1), -1);

      $someObject = new SomeObject();

      $someThing = Option::some(1);
      $someClass = Option::some($someObject);

      $this->assertSame($someThing->valueOr(-1), 1);
      $this->assertSame($someClass->valueOr(-1), $someObject);
   }

   public function testGettingValueLazily(): void {
      $noneOption = Option::none();

      $this->assertSame($noneOption->valueOrCreate(function() { return -1; }), -1);

      $someObject = new SomeObject();

      $someThing = Option::some(1);
      $someClass = Option::some($someObject);

      $this->assertSame($someThing->valueOrCreate(function() { return -1; }), 1);
      $this->assertSame($someClass->valueOrCreate(function() { return -1; }), $someObject);

      $this->assertSame($someThing->valueOrCreate(function() {
         $this->fail('Callback should not have been run!');
      }), 1);

      $this->assertSame($someClass->valueOrCreate(function() {
         $this->fail('Callback should not have been run!');
      }), $someObject);
   }

   public function testGettingAlternitiveValue(): void {
      $someObject = new SomeObject();
      $noneOption = Option::none();

      $this->assertFalse($noneOption->hasValue());

      $someThing= $noneOption->or(1);
      $someClass = $noneOption->or($someObject);

      $this->assertSame($someClass->or("Hello"), $someClass);

      $this->assertTrue($someThing->hasValue());
      $this->assertTrue($someClass->hasValue());

      $this->assertSame($someThing->valueOr(-1), 1);
      $this->assertSame($someClass->valueOr("-1"), $someObject);

      $lazySome = $noneOption->orCreate(function() { return 10; });
      $this->assertTrue($lazySome->hasValue());
      $this->assertSame($lazySome->valueOr(-1), 10);

      $lazyPassThrough = $someThing->orCreate(function() { return 10; });
      $this->assertTrue($lazyPassThrough->hasValue());
      $this->assertSame($lazyPassThrough->valueOr(-1), 1);
   }

   public function testGettingAlternitiveOption(): void {
      $someObject = new SomeObject();
      $noneOption = Option::none();

      $this->assertFalse($noneOption->hasValue());

      $noneOption2 = $noneOption->else(Option::none());
      $this->assertFalse($noneOption2->hasValue());

      $someThing = $noneOption->else(Option::some(1));
      $someClass = $noneOption->else(Option::some($someObject));

      $this->assertSame($someThing->else(Option::some(1)), $someThing);

      $this->assertTrue($someThing->hasValue());
      $this->assertTrue($someClass->hasValue());

      $this->assertSame($someThing->valueOr(-1), 1);
      $this->assertSame($someClass->valueOr("-1"), $someObject);
   }

   public function testGettingAlternitiveOptionLazy(): void {
      $someObject = new SomeObject();
      $noneOption = Option::none();

      $this->assertFalse($noneOption->hasValue());

      $noneOption2 = $noneOption->elseCreate(function() {
         return Option::none();
      });
      $this->assertFalse($noneOption2->hasValue());

      $someThing = $noneOption->elseCreate(function() {
         return Option::some(1);
      });

      $someClass = $noneOption->elseCreate(function() use ($someObject) {
         return Option::some($someObject);
      });

      $this->assertTrue($someThing->hasValue());
      $this->assertTrue($someClass->hasValue());

      $this->assertSame($someThing->valueOr(-1), 1);
      $this->assertSame($someClass->valueOr("-1"), $someObject);

      $kept = $someThing->elseCreate(
         /** @return never */
         function() {
            $this->fail('Callback should not have been run!');
         }
      );
      $this->assertSame($someThing, $kept, 'elseCreate hands back the Some it was called on');

      $kept = $someClass->elseCreate(
         /** @return never */
         function() {
            $this->fail('Callback should not have been run!');
         }
      );
      $this->assertSame($someClass, $kept, 'elseCreate hands back the Some it was called on');
   }

   public function testMatching(): void {
      $none = Option::none();
      $some = Option::some(1);

      $failure = $none->match(
          function($_x) { return 2; },
          function() { return -2; }
      );

      $success = $some->match(
          function($_x) { return 2; },
          function() { return -2; }
      );

      $this->assertSame($failure, -2);
      $this->assertSame($success, 2);

      $whichRan = $none->match(
          function() { return 'some'; },
          function() { return 'none'; }
      );
      $this->assertSame('none', $whichRan, 'match on a None runs the none branch');

      $whichRan = $some->match(
          function() { return 'some'; },
          function() { return 'none'; }
      );
      $this->assertSame('some', $whichRan, 'match on a Some runs the some branch');
   }

   public function testMapping(): void {
      $none = Option::none();
      $some = Option::some("a");
      $someNull = Option::some(null);

      $noneUpper = $none->map(function() { return 'UNREACHABLE'; });
      $someUpper = $some->map(function(string $x) { return strtoupper($x); });

      $this->assertFalse($noneUpper->hasValue());
      $this->assertTrue($someUpper->hasValue());
      $this->assertSame($noneUpper->valueOr("b"), "b");
      $this->assertSame($someUpper->valueOr("b"), "A");

      $noneNotNull = $none->flatMap(function($x) { return Option::some($x)->notNull(); });
      $someNotNull = $some->flatMap(function($x) { return Option::some($x)->notNull(); });
      $someNullNotNull = $someNull->flatMap(function($x) { return Option::some($x)->notNull(); });

      $this->assertFalse($noneNotNull->hasValue());
      $this->assertTrue($someNotNull->hasValue());
      $this->assertFalse($someNullNotNull->hasValue());
   }

   public function testFiltering(): void {
      $none = Option::none();
      $some = Option::some("a");

      $someTrue = $some->filter(true);
      $someFalse = $some->filter(false);
      $noneTrue = $none->filter(true);
      $noneFalse = $none->filter(false);

      $this->assertTrue($someTrue->hasValue());
      $this->assertFalse($someFalse->hasValue());

      $this->assertFalse($noneTrue->hasValue());
      $this->assertFalse($noneFalse->hasValue());

      $someNull = Option::some(null);
      $this->assertTrue($someNull->hasValue());
      $noneNull = $someNull->notNull();
      $this->assertFalse($noneNull->hasValue());

      $someEmpty = Option::some("");
      $this->assertTrue($someEmpty->hasValue());
      $noneEmpty = $someEmpty->notFalsy();
      $this->assertFalse($noneEmpty->hasValue());
   }

   public function testContains(): void {
      $none = Option::none();
      $someString = Option::some("a");
      $someInt = Option::some(1);

      $this->assertTrue($someString->contains("a"));
      $this->assertFalse($someString->contains("A"));
      $this->assertFalse($someString->contains(1));
      $this->assertFalse($someString->contains(null));

      $this->assertTrue($someInt->contains(1));
      $this->assertFalse($someInt->contains(2));
      $this->assertFalse($someInt->contains("A"));
      $this->assertFalse($someInt->contains(null));

      $this->assertFalse($none->contains(1));
      $this->assertFalse($none->contains(2));
      $this->assertFalse($none->contains("A"));
      $this->assertFalse($none->contains(null));
   }

   /**
    * contains() compares with ===, as Result::contains() does. Under the ==
    * of 3.x, each of these was true.
    */
   public function testContainsComparesStrictly(): void {
      $this->assertFalse(Option::some(1)->contains('1'));
      $this->assertFalse(Option::some('1e1')->contains('10'));
      $this->assertFalse(Option::some(null)->contains(false));
      $this->assertFalse(Option::some(new \stdClass())->contains(new \stdClass()));

      $object = new \stdClass();
      $this->assertTrue(Option::some($object)->contains($object), 'the same instance still matches');
   }

   public function testExists(): void {
      $none = Option::none();
      $some = Option::some(10);

      $noneFalse = $none->exists(function($x) { return $x == 10; });
      $someTrue = $some->exists(function($x) { return $x >= 10; });
      $someFalse = $some->exists(function($x) { return $x == "Thing"; });

      $this->assertTrue($someTrue);
      $this->assertFalse($noneFalse);
      $this->assertFalse($someFalse);
   }

   public function testFlatMap(): void {
      $somePerson = [
         'name' => [
            'first' => 'First',
            'last' => 'Last'
         ]
      ];

      /** @var Option<array{first: string, last: string}> */
      $person = Option::fromArray($somePerson, 'name');

      $name = $person->flatMap(
         /**
          * @param array{first: string, last: string} $person
          * @return Option<string|never>
          */
         function(array $person) {
            $fullName = $person['first'] . $person['last'];

            try {
               $thing = SomeComplexThing::doWork($fullName);
            } catch (\ErrorException $_e) {
               return Option::none();
            }

            return Option::some($thing);
         });

      $this->assertSame($name->valueOr(''), 'FirstLast');
   }

   public function testFlatMapWithException(): void {
      $somePerson = [
         'name' => [
            'first' => 'First',
            'last' => 'Last'
         ]
      ];

      $person = Option::fromArray($somePerson, 'BAD VALUE');

      $this->assertFalse($person->hasValue());
      $this->assertSame($person->valueOr('oh no'), 'oh no');
   }

   public function testSafelyMapWithException(): void {
      $somePerson = [
         'name' => [
            'first' => 'First',
            'last' => 'Last'
         ]
      ];

      $person = Option::fromArray($somePerson, 'name');

      $name = $person->mapSafely(
         /** @param array{first: string, last: string} $person */
         function(array $person): string {
            $fullName = $person['first'] . $person['last'];
            return SomeComplexThing::doWork($fullName, "Forcing some exception");
         });

      $this->assertFalse($name->hasValue());
      $this->assertSame($name->valueOr('oh no'), 'oh no');
   }

   /**
    * 3.x interpolated the value, so an array printed a warning and
    * "Some(Array)", an object with no __toString threw an Error, and false
    * printed "Some()".
    */
   public function testToStringShowsAnyValueWithoutWarning(): void {
      $this->assertSame('Some(10)', (string) Option::some(10));
      $this->assertSame('None', (string) Option::none());
      $this->assertSame('Some(null)', (string) Option::some(null));
      $this->assertSame('Some(false)', (string) Option::some(false));
      $this->assertSame('Some(array)', (string) Option::some([1, 2]), 'an array is shown by its type');
      $this->assertSame('Some(stdClass)', (string) Option::some(new \stdClass()), 'an object with no string form is shown by its class');
      $this->assertSame('Some(RuntimeException: boom)', (string) Option::some(new \RuntimeException('boom')), 'an exception by its class and message, not its trace');
   }

   public function testValueOrThrowUsesTheSuppliedException(): void {
      $some = Option::some(1);
      $this->assertSame(1, $some->valueOrThrow(function(): \Exception {
         throw new \LogicException('factory must not be called on a Some');
      }));

      $this->expectException(\RuntimeException::class);
      $this->expectExceptionMessage("no device id");

      Option::none()->valueOrThrow(function(): \Exception { return new \RuntimeException("no device id"); });
   }

   public function testTap(): void {
      $seen = null;
      $some = Option::some(5);
      $returned = $some->tap(function(int $x) use (&$seen): void { $seen = $x; });

      $this->assertSame(5, $seen, 'tap runs its callback on a Some');
      $this->assertSame($some, $returned, 'tap returns the original option so a chain continues');

      $ran = false;
      $none = Option::none();
      $returnedNone = $none->tap(function() use (&$ran): void { $ran = true; });

      $this->assertFalse($ran, 'tap must not run its callback on a None');
      $this->assertSame($none, $returnedNone);

      $doubled = Option::some(5)
         ->tap(function() use (&$ran): void { $ran = true; })
         ->map(function(int $x): int { return $x * 2; });

      $this->assertSame(10, $doubled->valueOr(-1), 'tap does not break the chain');
   }

   public function testTapNone(): void {
      $ran = false;
      $none = Option::none();
      $returned = $none->tapNone(function() use (&$ran): void { $ran = true; });

      $this->assertTrue($ran, 'tapNone runs its callback on a None');
      $this->assertSame($none, $returned, 'tapNone returns the original option so a chain continues');

      $ranOnSome = false;
      $some = Option::some(1);
      $this->assertSame($some, $some->tapNone(function() use (&$ranOnSome): void { $ranOnSome = true; }));
      $this->assertFalse($ranOnSome, 'tapNone must not run its callback on a Some');

      $recovered = Option::none()
         ->tapNone(function(): void {})
         ->orCreate(function(): string { return 'from source'; });

      $this->assertSame('from source', $recovered->valueOr('x'), 'tapNone does not break the chain');
   }

   /**
    * filter takes a bool or a callable now. The short, guessable name used to
    * be the eager one, so the cheapest thing to write was the one that costs.
    */
   public function testFilterTakesEitherSpelling(): void {
      $this->assertSame(5, Option::some(5)->filter(true)->valueOr(-1));
      $this->assertFalse(Option::some(5)->filter(false)->hasValue());

      $this->assertSame(5, Option::some(5)->filter(function(int $n): bool { return $n > 3; })->valueOr(-1));
      $this->assertFalse(Option::some(5)->filter(function(int $n): bool { return $n > 9; })->hasValue());

      $ran = false;
      $filtered = Option::none()->filter(function() use (&$ran): bool { $ran = true; return true; });
      $this->assertFalse($ran, 'the callable must not run on a None');
      $this->assertFalse($filtered->hasValue());

   }

   /**
    * A callback's result counts the way `if` counts it, as filterIf's did in
    * 3.x. A flag read back from a database as 1 or '1' has to keep the value,
    * not drop it because it is not the bool true.
    *
    * @psalm-suppress InvalidArgument these callbacks break callable(T):bool on purpose
    */
   public function testFilterReadsTheCallbackResultTheWayIfDoes(): void {
      $this->assertSame(5, Option::some(5)->filter(fn() => 1)->valueOr(-1));
      $this->assertSame(5, Option::some(5)->filter(fn() => '1')->valueOr(-1));
      $this->assertFalse(Option::some(5)->filter(fn() => '0')->hasValue());
      $this->assertFalse(Option::some(5)->filter(fn() => null)->hasValue());
   }

   public function testFirst(): void {
      $this->assertSame('a', Option::first(['a', 'b'])->valueOr('x'));
      $this->assertFalse(Option::first([])->hasValue());
      $this->assertSame('a', Option::first(['k' => 'a'])->valueOr('x'), 'keys are ignored');
      $this->assertTrue(Option::first([null])->hasValue(), 'a first value of null is still a Some');
   }

   /**
    * The point of taking an iterable rather than an array: a generator should
    * stop after the first value instead of being drained.
    */
   public function testFirstDoesNotDrainAGenerator(): void {
      $drained = false;

      $values = (function() use (&$drained) {
         yield 'first';
         $drained = true;
         yield 'second';
      })();

      $this->assertSame('first', Option::first($values)->valueOr('x'));
      $this->assertFalse($drained, 'first() pulled more than one value from the generator');
   }

   public function testFirstWithAPredicateTakesTheFirstMatch(): void {
      $this->assertSame('bb', Option::first(['a', 'bb', 'ccc'], fn(string $s) => strlen($s) > 1)->valueOr('x'));
      $this->assertFalse(Option::first(['a', 'b'], fn(string $s) => strlen($s) > 1)->hasValue(), 'no match is a None');
      $this->assertTrue(
         Option::first(['a', null], fn(?string $s) => $s === null)->hasValue(),
         'a match on null is Some(null), where array_find would return null for a miss too'
      );
   }

   public function testFirstWithAPredicateStopsAtTheMatch(): void {
      $checked = [];

      $values = (function() {
         yield 1;
         yield 2;
         yield 3;
      })();

      $even = function(int $n) use (&$checked): bool {
         $checked[] = $n;
         return $n % 2 === 0;
      };

      $this->assertSame(2, Option::first($values, $even)->valueOr(-1));
      $this->assertSame([1, 2], $checked, 'first() kept checking values after the first match');
   }

   public function testToNullable(): void {
      $this->assertSame('Ada', Option::some('Ada')->toNullable());
      $this->assertNull(Option::none()->toNullable());
      $this->assertNull(Option::some(null)->toNullable(), 'a stored null and an empty box look the same at a ?T boundary');
      $this->assertSame(
         Option::some('Ada')->valueOr(null),
         Option::some('Ada')->toNullable(),
         'toNullable is valueOr(null) with a name that says why'
      );
   }

   /**
    * someNotFalse exists for PHP's false-returning functions, where a match
    * at index 0 is falsy but found. someNotFalsy gets that case wrong.
    */
   public function testSomeNotFalse(): void {
      $this->assertSame(0, Option::someNotFalse(array_search('a', ['a', 'b']))->valueOr(-1), 'index 0 is found');
      $this->assertFalse(Option::someNotFalsy(array_search('a', ['a', 'b']))->hasValue(), 'the falsy check drops it');
      $this->assertFalse(Option::someNotFalse(false)->hasValue());
      $this->assertSame('', Option::someNotFalse('')->valueOr('x'), 'an empty string is not false');
      $this->assertNull(Option::someNotFalse(null)->valueOr('x'), 'null is not false either');
   }

   public function testNotFalse(): void {
      $this->assertFalse(Option::some(false)->notFalse()->hasValue());
      $this->assertSame(0, Option::some(0)->notFalse()->valueOr(-1));
      $this->assertFalse(Option::none()->notFalse()->hasValue());
   }

   public function testSomeNotFalsy(): void {
      $this->assertFalse(Option::someNotFalsy(0)->hasValue());
      $this->assertFalse(Option::someNotFalsy('')->hasValue());
      $this->assertFalse(Option::someNotFalsy([])->hasValue());
      $this->assertFalse(Option::someNotFalsy(null)->hasValue());
      $this->assertFalse(Option::someNotFalsy(false)->hasValue());
      $this->assertFalse(Option::someNotFalsy('0')->hasValue());

      $this->assertTrue(Option::someNotFalsy('0.0')->hasValue());
      $this->assertTrue(Option::someNotFalsy(-1)->hasValue());
      $this->assertSame([1], Option::someNotFalsy([1])->valueOr([]));
   }

   public function testFromCallable(): void {
      $some = Option::fromCallable(function(): int { return 7; });
      $this->assertSame(7, $some->valueOr(-1));

      $none = Option::fromCallable(function(): int { throw new \Exception("boom"); });
      $this->assertFalse($none->hasValue(), 'a thrown Exception becomes a None');

      $stillSome = Option::fromCallable(function() { return null; });
      $this->assertTrue($stillSome->hasValue(), 'a returned null is still a Some, use notNull to collapse it');
   }

   /**
    * mapSafely deliberately catches Exception and not Throwable. A mutation
    * sweep found nothing pinned that, so the two boxes could be made to agree
    * in exactly the way AGENTS.md says not to, with CI staying green.
    */
   public function testMapSafelyCatchesExceptionAndDropsIt(): void {
      $none = Option::some(1)->mapSafely(function(int $_x): int { throw new \Exception("boom"); });

      $this->assertFalse($none->hasValue(), 'a thrown Exception becomes a None');
   }

   public function testMapSafelyLetsErrorsThrough(): void {
      $this->expectException(\TypeError::class);

      $_ = Option::some(1)->mapSafely(function(int $_x): int { throw new \TypeError("not an exception"); });
   }

   public function testFromCallableLetsErrorsThrough(): void {
      $this->expectException(\TypeError::class);
      Option::fromCallable(function(): int { throw new \TypeError("not an exception"); });
   }

   public function testFromCallableCatchesTheNamedClassAndItsSubclasses(): void {
      $named = Option::fromCallable(function(): int { throw new \OutOfBoundsException('no user 5'); }, \OutOfBoundsException::class);
      $this->assertFalse($named->hasValue(), 'the named class becomes a None');

      $subclass = Option::fromCallable(function(): int { throw new \OutOfBoundsException('no user 5'); }, \RuntimeException::class);
      $this->assertFalse($subclass->hasValue(), 'a subclass of the named class is caught, as a catch clause would');
   }

   public function testFromCallableLetsAnyOtherClassThrough(): void {
      $this->expectException(\LogicException::class);
      Option::fromCallable(function(): int { throw new \LogicException('a bug'); }, \OutOfBoundsException::class);
   }

   public function testFromCallableCatchesAnErrorOnlyWhenNamed(): void {
      $none = Option::fromCallable(function(): int { throw new \TypeError('x'); }, \TypeError::class);
      $this->assertFalse($none->hasValue(), 'naming an Error class opts into catching it');
   }

   public function testMapSafelyCatchesTheNamedClass(): void {
      $none = Option::some(1)->mapSafely(function(int $_x): int { throw new \OutOfBoundsException('x'); }, \OutOfBoundsException::class);
      $this->assertFalse($none->hasValue(), 'the named class becomes a None');
   }

   public function testMapSafelyLetsAnyOtherClassThrough(): void {
      $this->expectException(\LogicException::class);
      $_ = Option::some(1)->mapSafely(function(int $_x): int { throw new \LogicException('a bug'); }, \OutOfBoundsException::class);
   }

}

final class SomeObject {}
final class SomeComplexThing {
   /**
    * @throws \Exception
    */
   public static function doWork(string $thing, ?string $ex = null): string {
      if ($ex !== null) {
         throw new \Exception($ex);
      }

      return $thing;
   }
}
