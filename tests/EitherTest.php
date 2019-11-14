<?php
declare(strict_types = 1);

require_once dirname(__FILE__) . '/../src/Either.php';

use Optional\Either;
use Optional\Option;

class EitherTest extends PHPUnit\Framework\TestCase {

   public function testCreateAndCheckExistence() {
      $noneValue = "goodbye";
      $noneEither = Either::none($noneValue);

      $this->assertFalse($noneEither->hasValue());

      $leftThing = Either::left(1, $noneValue);
      $leftNullable = Either::left(null, $noneValue);
      $leftClass = Either::left(new SomeObject(), $noneValue);

      $this->assertTrue($leftThing->hasValue());
      $this->assertTrue($leftNullable->hasValue());
      $this->assertTrue($leftClass->hasValue());

      $noname = Either::fromArray(['name' => 'value'], 'noname', 'oh no');
      $this->assertFalse($noname->hasValue());

      $name = Either::fromArray(['name' => 'value'], 'name', 'oh no');
      $this->assertTrue($name->hasValue());

      $nonameEither = Either::fromArray(['name' => 'value'], 'noname');
      $nonameNull = Either::fromArray(['name' => 'value'], 'noname');
      $this->assertFalse($nonameEither->hasValue());
      $this->assertFalse($nonameNull->hasValue());

      $none = Either::leftNotNull(null, $noneValue);
      $left = Either::leftNotNull('', $noneValue);

      $this->assertFalse($none->hasValue());
      $this->assertTrue($left->hasValue());
   }

   public function testCreateAndCheckExistenceWhen() {
      $noneValue = "goodbye";

      $leftThing = Either::leftWhen(1, $noneValue, function($x) { return $x > 0; });
      $leftThing2 = Either::leftWhen(-1, $noneValue, function($x) { return $x > 0; });

      $this->assertSame($leftThing->valueOr(-5), 1);
      $this->assertSame($leftThing2->valueOr(-5), -5);

      $leftThing3 = Either::noneWhen(1, $noneValue, function($x) { return $x > 0; });
      $leftThing4 = Either::noneWhen(-1, $noneValue, function($x) { return $x > 0; });

      $this->assertSame($leftThing3->valueOr(-5), -5);
      $this->assertSame($leftThing4->valueOr(-5), -1);
   }

   public function testGettingValue() {
      $noneValue = "goodbye";
      $noneEither = Either::none($noneValue);

      $this->assertSame($noneEither->valueOr(-1), -1);

      $leftObject = new SomeObject();

      $leftThing = Either::left(1, $noneValue);
      $leftClass = Either::left($leftObject, $noneValue);

      $this->assertSame($leftThing->valueOr(-1), 1);
      $this->assertSame($leftClass->valueOr(-1), $leftObject);
   }

   public function testGettingValueLazily() {
      $noneValue = "goodbye";
      $noneEither = Either::none($noneValue);

      $this->assertSame($noneEither->valueOrCreate(function($x) { return $x; }), $noneValue);

      $leftObject = new SomeObject();

      $leftThing = Either::left(1, $noneValue);
      $leftClass = Either::left($leftObject, $noneValue);

      $this->assertSame($leftThing->valueOrCreate(function($x) { return $x; }), 1);
      $this->assertSame($leftClass->valueOrCreate(function($x) { return $x; }), $leftObject);

      $this->assertSame($leftThing->valueOrCreate(function($x) {
         $this->fail('Callback should not have been run!');
         return $x;
      }), 1);

      $this->assertSame($leftClass->valueOrCreate(function($x) {
         $this->fail('Callback should not have been run!');
         return $x;
      }), $leftObject);
   }

   public function testGettingAlternitiveValue() {
      $noneValue = "goodbye";
      $leftObject = new SomeObject();
      $noneEither = Either::none($noneValue);

      $this->assertFalse($noneEither->hasValue());

      $leftThing= $noneEither->or(1);
      $leftClass = $noneEither->or($leftObject);

      $this->assertTrue($leftThing->hasValue());
      $this->assertTrue($leftClass->hasValue());

      $this->assertSame($leftThing->valueOr(-1), 1);
      $this->assertSame($leftClass->valueOr("-1"), $leftObject);

      $lazySome = $noneEither->orCreate(function() { return 10; });
      $this->assertTrue($lazySome->hasValue());
      $this->assertSame($lazySome->valueOr(-1), 10);

      $lazyPassThrough = $leftThing->orCreate(function() { return 10; });
      $this->assertTrue($lazyPassThrough->hasValue());
      $this->assertSame($lazyPassThrough->valueOr(-1), 1);
   }

   public function testGettingAlternitiveEither() {
      $noneValue = "goodbye";
      $leftObject = new SomeObject();
      $noneEither = Either::none($noneValue);

      $this->assertFalse($noneEither->hasValue());

      $noneEither2 = $noneEither->else(Either::none($noneValue));
      $this->assertFalse($noneEither2->hasValue());

      $leftThing = $noneEither->else(Either::left(1, $noneValue));
      $leftClass = $noneEither->else(Either::left($leftObject, $noneValue));

      $this->assertTrue($leftThing->hasValue());
      $this->assertTrue($leftClass->hasValue());

      $this->assertSame($leftThing->valueOr(-1), 1);
      $this->assertSame($leftClass->valueOr("-1"), $leftObject);
   }

   public function testGettingAlternitiveEitherLazy() {
      $noneValue = "goodbye";
      $leftObject = new SomeObject();
      $noneEither = Either::none($noneValue);

      $this->assertFalse($noneEither->hasValue());

      $noneEither2 = $noneEither->elseCreate(function($x) {
         return Either::none($x);
      });
      $this->assertFalse($noneEither2->hasValue());

      $leftThing = $noneEither->elseCreate(function($x) {
         return Either::left(1);
      });

      $leftClass = $noneEither->elseCreate(function($x) use ($leftObject) {
         return Either::left($leftObject);
      });

      $this->assertTrue($leftThing->hasValue());
      $this->assertTrue($leftClass->hasValue());

      $this->assertSame($leftThing->valueOr(-1), 1);
      $this->assertSame($leftClass->valueOr("-1"), $leftObject);

      $leftThing->elseCreate(function($x) {
         $this->fail('Callback should not have been run!');
         return Either::none($x);
      });
      $leftClass->elseCreate(function($x) {
         $this->fail('Callback should not have been run!');
         return Either::none($x);
      });
   }

   public function testMatching() {
      $noneValue = "goodbye";
      $none = Either::none($noneValue);
      $left = Either::left(1, $noneValue);

      $failure = $none->match(
          function($x) { return 2; },
          function($x) { return $x; }
      );

      $success = $left->match(
          function($x) { return 2; },
          function($x) { return $x; }
      );

      $this->assertSame($failure, $noneValue);
      $this->assertSame($success, 2);

      $hasMatched = false;
      $none->match(
          function($x) { $this->fail('Callback should not have been run!'); },
          function($x) use (&$hasMatched) { $hasMatched = true; }
      );
      $this->assertTrue($hasMatched);

      $hasMatched = false;
      $left->match(
          function($x) use (&$hasMatched) { return $hasMatched = $x == 1; },
          function($x) use (&$hasMatched) { $this->fail('Callback should not have been run!'); }
      );
      $this->assertTrue($hasMatched);

      $none->matchSome(function($x) { $this->fail('Callback should not have been run!'); });

      $hasMatched = false;
      $left->matchSome(function($x) use (&$hasMatched) { return $hasMatched = $x == 1; });
      $this->assertTrue($hasMatched);

      $left->matchNone(function() { $this->fail('Callback should not have been run!'); });
      $hasMatched = false;

      $none->matchNone(function() use (&$hasMatched) { $hasMatched = true; });
      $this->assertTrue($hasMatched);
   }

   public function testMapping() {
      $noneValue = "goodbye";
      $none = Either::none($noneValue);
      $left = Either::left("a", $noneValue);
      $leftNull = Either::left(null);

      $noneUpper = $none->map(function($x) { return strtoupper($x); });
      $leftUpper = $left->map(function($x) { return strtoupper($x); });

      $this->assertFalse($noneUpper->hasValue());
      $this->assertTrue($leftUpper->hasValue());
      $this->assertSame($noneUpper->valueOr("b"), "b");
      $this->assertSame($leftUpper->valueOr("b"), "A");

      $noneNotNull = $none->flatMap(function($x) use ($noneValue) { return Either::left($x)->notNull($noneValue); });
      $leftNotNull = $left->flatMap(function($x) use ($noneValue) { return Either::left($x)->notNull($noneValue); });
      $leftNullNotNull = $leftNull->flatMap(function($x) use ($noneValue) { return Either::left($x)->notNull($noneValue); });

      $this->assertFalse($noneNotNull->hasValue());
      $this->assertTrue($leftNotNull->hasValue());
      $this->assertFalse($leftNullNotNull->hasValue());
   }

   public function testFiltering() {
      $noneValue = "goodbye";
      $none = Either::none($noneValue);
      $left = Either::left("a", $noneValue);

      $leftTrue = $left->filter(true, $noneValue);
      $leftFalse = $left->filter(false, $noneValue);
      $noneTrue = $none->filter(true, $noneValue);
      $noneFalse = $none->filter(false, $noneValue);

      $this->assertTrue($leftTrue->hasValue());
      $this->assertFalse($leftFalse->hasValue());

      $this->assertFalse($noneTrue->hasValue());
      $this->assertFalse($noneFalse->hasValue());

      $noneNotA = $none->filterIf(function($x) { return $x != "a"; }, $noneValue);
      $leftNotA = $left->filterIf(function($x) { return $x != "a"; }, $noneValue);
      $noneA = $none->filterIf(function($x) { return $x == "a"; }, $noneValue);
      $leftA = $left->filterIf(function($x) { return $x == "a"; }, $noneValue);

      $this->assertFalse($noneNotA->hasValue());
      $this->assertFalse($leftNotA->hasValue());
      $this->assertFalse($noneA->hasValue());
      $this->assertTrue($leftA->hasValue());

      $leftNull = Either::left(null);
      $this->assertTrue($leftNull->hasValue());
      $noneNull = $leftNull->notNull($noneValue);
      $this->assertFalse($noneNull->hasValue());

      $leftEmpty = Either::left("");
      $this->assertTrue($leftEmpty->hasValue());
      $noneEmpty = $leftEmpty->notFalsy($noneValue);
      $this->assertFalse($noneEmpty->hasValue());
   }

   public function testContains() {
      $noneValue = "goodbye";
      $none = Either::none($noneValue);
      $leftString = Either::left("a", $noneValue);
      $leftInt = Either::left(1, $noneValue);

      $this->assertTrue($leftString->contains("a"));
      $this->assertFalse($leftString->contains("A"));
      $this->assertFalse($leftString->contains(1));
      $this->assertFalse($leftString->contains(null));

      $this->assertTrue($leftInt->contains(1));
      $this->assertFalse($leftInt->contains(2));
      $this->assertFalse($leftInt->contains("A"));
      $this->assertFalse($leftInt->contains(null));

      $this->assertFalse($none->contains(1));
      $this->assertFalse($none->contains(2));
      $this->assertFalse($none->contains("A"));
      $this->assertFalse($none->contains(null));
   }

   public function testExists() {
      $noneValue = "goodbye";
      $none = Either::none($noneValue);
      $left = Either::left(10, $noneValue);

      $noneFalse = $none->exists(function($x) { return $x == 10; });
      $leftTrue = $left->exists(function($x) { return $x >= 10; });
      $leftFalse = $left->exists(function($x) { return $x == "Thing"; });

      $this->assertTrue($leftTrue);
      $this->assertFalse($noneFalse);
      $this->assertFalse($leftFalse);
   }

   public function testFlatMap() {
      $leftPerson = [
         'name' => [
            'first' => 'First',
            'last' => 'Last'
         ]
      ];

      $person = Either::fromArray($leftPerson, 'name', 'name was missing');

      $name = $person->andThen(function($person) {
         $fullName = $person['first'] . $person['last'];

         try {
            $thing = SomeComplexThing::doWork($fullName);
         } catch (ErrorException $e) {
            return Either::none('SomeComplexThing had an error!');
         }

         return Either::left($thing);
      });

      $this->assertSame($name->valueOr(''), 'FirstLast');
   }

   public function testFlatMapWithException() {
      $leftPerson = [
         'name' => [
            'first' => 'First',
            'last' => 'Last'
         ]
      ];

      $person = Either::fromArray($leftPerson, 'name', 'name was missing');

      $name = $person->andThen(function($person) {
         $fullName = $person['first'] . $person['last'];

         try {
            $thing = SomeComplexThing::doWork($fullName, "Forcing left exception");
         } catch (\Exception $e) {
            return Either::none('SomeComplexThing had an error!');
         }

         return Either::left($thing);
      });

      $this->assertFalse($name->hasValue());
      $this->assertSame($name->valueOr('oh no'), 'oh no');
   }

   public function testSafelyMapWithException() {
      $leftPerson = [
         'name' => [
            'first' => 'First',
            'last' => 'Last'
         ]
      ];

      $person = Either::fromArray($leftPerson, 'name', 'name was missing');

      $name = $person->mapSafely(function($person): string {
         $fullName = $person['first'] . $person['last'];
         return SomeComplexThing::doWork($fullName, "Forcing left exception");
      });

      $this->assertFalse($name->hasValue());
      $this->assertSame($name->valueOr('oh no'), 'oh no');

      $out = 'This should change';

      $name->match(
         function ($leftValue) { $this->fail('Callback should not have been run!'); },
         function ($exception) use(&$out) { $out = $exception->getMessage(); }
      );

      $this->assertSame($out, "Forcing left exception");
   }

   public function testToOption() {
      $left = Either::left(10, "Some Error Message");
      $none = Either::none("Some Error Message");

      $leftOption = $left->toOption();
      $noneOption = $none->toOption();

      $this->assertEquals($leftOption, Option::left(10));
      $this->assertEquals($noneOption, Option::none());
   }
}