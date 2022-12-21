<?php

declare(strict_types = 1);

namespace Optional\Tests;

use Optional\Exceptions\MissingValueException;
use Optional\Option;
use PHPUnit\Framework\TestCase;

class OptionTest extends TestCase {
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

   /**
    * @psalm-suppress UnevaluatedCode since php unit is catching the throw
    * @psalm-suppress UnevaluatedCode since php unit is catching the throw
    * @psalm-suppress UnusedMethodCall since php unit is catching the throw
    */
   public function testValueOrThrow(): void {
      $some = Option::some(1);
      $this->assertSame($some->value(), 1);

      $none = Option::none();

      $this->expectException(MissingValueException::class);
      $none->value();

      $this->expectExceptionMessage("Value is missing.");
      $none->value();
   }

   public function testCreateAndCheckExistenceWhen(): void {
      $someThing = Option::someWhen(1, function(int $x) { return $x > 0; });
      $someThing2 = Option::someWhen(-1, function(int $x) { return $x > 0; });

      $this->assertSame($someThing->valueOr(-5), 1);
      $this->assertSame($someThing2->valueOr(-5), -5);

      $someThing3 = Option::noneWhen(1, function(int $x) { return $x > 0; });
      $someThing4 = Option::noneWhen(-1, function(int $x) { return $x > 0; });

      $this->assertSame($someThing3->valueOr(-5), -5);
      $this->assertSame($someThing4->valueOr(-5), -1);
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

      /** @psalm-suppress UnusedMethodCall so we can show that psalm even knows this is impossible */
      $someThing->elseCreate(
         /** @return never */
         function() {
            $this->fail('Callback should not have been run!');
         }
      );

      /** @psalm-suppress UnusedMethodCall so we can show that psalm even knows this is impossible */
      $someClass->elseCreate(
         /** @return never */
         function() {
            $this->fail('Callback should not have been run!');
         }
      );
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

      $hasMatched = $none->match(
          function($_x) { $this->fail('Callback should not have been run!'); },
          function() { return true; }
      );
      $this->assertTrue($hasMatched);

      $hasMatched = $some->match(
          function($x) { return $x == 1; },
          function() { $this->fail('Callback should not have been run!'); }
      );
      $this->assertTrue($hasMatched);

      /** @psalm-suppress UnusedMethodCall so we can show that psalm even knows this is impossible */
      $none->matchSome(function(int $_x) { $this->fail('Callback should not have been run!'); });

      $hasMatched = false;
      $some->matchSome(function(int $x) use (&$hasMatched) { return $hasMatched = $x == 1; });
      $this->assertTrue($hasMatched);

      /** @psalm-suppress UnusedMethodCall so we can show that psalm even knows this is impossible */
      $some->matchNone(function() { $this->fail('Callback should not have been run!'); });

      $none->matchNone(function() use (&$hasMatched) { $hasMatched = true; });
      $this->assertTrue($hasMatched);
   }

   public function testMapping(): void {
      $none = Option::none();
      $some = Option::some("a");
      $someNull = Option::some(null);

      $noneUpper = $none->map(function(string $x) { return strtoupper($x); });
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

      $noneNotNull = $none->andThen(function($x) { return Option::some($x)->notNull(); });
      $someNotNull = $some->andThen(function($x) { return Option::some($x)->notNull(); });
      $someNullNotNull = $someNull->andThen(function($x) { return Option::some($x)->notNull(); });

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

      $noneNotA = $none->filterIf(function($x) { return $x != "a"; });
      $someNotA = $some->filterIf(function($x) { return $x != "a"; });
      $noneA = $none->filterIf(function($x) { return $x == "a"; });
      $someA = $some->filterIf(function($x) { return $x == "a"; });

      $this->assertFalse($noneNotA->hasValue());
      $this->assertFalse($someNotA->hasValue());
      $this->assertFalse($noneA->hasValue());
      $this->assertTrue($someA->hasValue());

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

      $name = $person->andThen(
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

   public function testToString(): void {
      $this->assertEquals("Some(null)", (string)Option::some(null));
      $this->assertEquals("Some(10)", (string)Option::some(10));

      $this->assertEquals("None", (string)Option::none());
   }
}

class SomeObject {};
class SomeComplexThing {
   /**
    * @throws \Exception
    */
   public static function doWork(string $thing, string $ex = null): string {
      if($ex) {
         throw new \Exception($ex);
      }

      return $thing;
   }
};
