<?php
declare(strict_types = 1);

require_once dirname(__FILE__) . '/../src/Either.php';

use Optional\Either;
use Optional\Option;

class EitherTest extends PHPUnit\Framework\TestCase {

   public function testCreateAndCheckExistence() {
      $rightValue = "goodbye";
      $rightEither = Either::right($rightValue);

      $this->assertFalse($rightEither->hasValue());

      $leftThing = Either::left(1, $rightValue);
      $leftNullable = Either::left(null, $rightValue);
      $leftClass = Either::left(new SomeObject(), $rightValue);

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

      $right = Either::leftNotNull(null, $rightValue);
      $left = Either::leftNotNull('', $rightValue);

      $this->assertFalse($right->hasValue());
      $this->assertTrue($left->hasValue());
   }

   public function testCreateAndCheckExistenceWhen() {
      $rightValue = "goodbye";

      $leftThing = Either::leftWhen(1, $rightValue, function($x) { return $x > 0; });
      $leftThing2 = Either::leftWhen(-1, $rightValue, function($x) { return $x > 0; });

      $this->assertSame($leftThing->valueOr(-5), 1);
      $this->assertSame($leftThing2->valueOr(-5), -5);

      $leftThing3 = Either::rightWhen(1, $rightValue, function($x) { return $x > 0; });
      $leftThing4 = Either::rightWhen(-1, $rightValue, function($x) { return $x > 0; });

      $this->assertSame($leftThing3->valueOr(-5), -5);
      $this->assertSame($leftThing4->valueOr(-5), -1);
   }

   public function testGettingValue() {
      $rightValue = "goodbye";
      $rightEither = Either::right($rightValue);

      $this->assertSame($rightEither->valueOr(-1), -1);

      $someObject = new SomeObject();

      $leftThing = Either::left(1, $rightValue);
      $leftClass = Either::left($someObject, $rightValue);

      $this->assertSame($leftThing->valueOr(-1), 1);
      $this->assertSame($leftClass->valueOr(-1), $someObject);
   }

   public function testGettingValueLazily() {
      $rightValue = "goodbye";
      $rightEither = Either::right($rightValue);

      $this->assertSame($rightEither->valueOrCreate(function($x) { return $x; }), $rightValue);

      $someObject = new SomeObject();

      $leftThing = Either::left(1, $rightValue);
      $leftClass = Either::left($someObject, $rightValue);

      $this->assertSame($leftThing->valueOrCreate(function($x) { return $x; }), 1);
      $this->assertSame($leftClass->valueOrCreate(function($x) { return $x; }), $someObject);

      $this->assertSame($leftThing->valueOrCreate(function($x) {
         $this->fail('Callback should not have been run!');
         return $x;
      }), 1);

      $this->assertSame($leftClass->valueOrCreate(function($x) {
         $this->fail('Callback should not have been run!');
         return $x;
      }), $someObject);
   }

   public function testGettingAlternitiveValue() {
      $rightValue = "goodbye";
      $someObject = new SomeObject();
      $rightEither = Either::right($rightValue);

      $this->assertFalse($rightEither->hasValue());

      $leftThing= $rightEither->or(1);
      $leftClass = $rightEither->or($someObject);

      $this->assertTrue($leftThing->hasValue());
      $this->assertTrue($leftClass->hasValue());

      $this->assertSame($leftThing->valueOr(-1), 1);
      $this->assertSame($leftClass->valueOr("-1"), $someObject);

      $lazyleft = $rightEither->orCreate(function() { return 10; });
      $this->assertTrue($lazyleft->hasValue());
      $this->assertSame($lazyleft->valueOr(-1), 10);

      $lazyPassThrough = $leftThing->orCreate(function() { return 10; });
      $this->assertTrue($lazyPassThrough->hasValue());
      $this->assertSame($lazyPassThrough->valueOr(-1), 1);
   }

   public function testGettingAlternitiveEither() {
      $rightValue = "goodbye";
      $someObject = new SomeObject();
      $rightEither = Either::right($rightValue);

      $this->assertFalse($rightEither->hasValue());

      $rightEither2 = $rightEither->else(Either::right($rightValue));
      $this->assertFalse($rightEither2->hasValue());

      $leftThing = $rightEither->else(Either::left(1, $rightValue));
      $leftClass = $rightEither->else(Either::left($someObject, $rightValue));

      $this->assertTrue($leftThing->hasValue());
      $this->assertTrue($leftClass->hasValue());

      $this->assertSame($leftThing->valueOr(-1), 1);
      $this->assertSame($leftClass->valueOr("-1"), $someObject);
   }

   public function testGettingAlternitiveEitherLazy() {
      $rightValue = "goodbye";
      $someObject = new SomeObject();
      $rightEither = Either::right($rightValue);

      $this->assertFalse($rightEither->hasValue());

      $rightEither2 = $rightEither->elseCreate(function($x) {
         return Either::right($x);
      });
      $this->assertFalse($rightEither2->hasValue());

      $leftThing = $rightEither->elseCreate(function($x) {
         return Either::left(1);
      });

      $leftClass = $rightEither->elseCreate(function($x) use ($someObject) {
         return Either::left($someObject);
      });

      $this->assertTrue($leftThing->hasValue());
      $this->assertTrue($leftClass->hasValue());

      $this->assertSame($leftThing->valueOr(-1), 1);
      $this->assertSame($leftClass->valueOr("-1"), $someObject);

      $leftThing->elseCreate(function($x) {
         $this->fail('Callback should not have been run!');
         return Either::right($x);
      });
      $leftClass->elseCreate(function($x) {
         $this->fail('Callback should not have been run!');
         return Either::right($x);
      });
   }

   public function testMatching() {
      $rightValue = "goodbye";
      $right = Either::right($rightValue);
      $left = Either::left(1, $rightValue);

      $failure = $right->match(
          function($x) { return 2; },
          function($x) { return $x; }
      );

      $success = $left->match(
          function($x) { return 2; },
          function($x) { return $x; }
      );

      $this->assertSame($failure, $rightValue);
      $this->assertSame($success, 2);

      $hasMatched = false;
      $right->match(
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

      $right->matchleft(function($x) { $this->fail('Callback should not have been run!'); });

      $hasMatched = false;
      $left->matchleft(function($x) use (&$hasMatched) { return $hasMatched = $x == 1; });
      $this->assertTrue($hasMatched);

      $left->matchRight(function() { $this->fail('Callback should not have been run!'); });
      $hasMatched = false;

      $right->matchRight(function() use (&$hasMatched) { $hasMatched = true; });
      $this->assertTrue($hasMatched);
   }

   public function testMapping() {
      $rightValue = "goodbye";
      $right = Either::right($rightValue);
      $left = Either::left("a", $rightValue);
      $leftNull = Either::left(null);

      $rightUpper = $right->mapLeft(function($x) { return strtoupper($x); });
      $leftUpper = $left->mapLeft(function($x) { return strtoupper($x); });

      $this->assertFalse($rightUpper->hasValue());
      $this->assertTrue($leftUpper->hasValue());
      $this->assertSame($rightUpper->valueOr("b"), "b");
      $this->assertSame($leftUpper->valueOr("b"), "A");

      $rightNotNull = $right->flatMap(function($x) use ($rightValue) { return Either::left($x)->notNull($rightValue); });
      $leftNotNull = $left->flatMap(function($x) use ($rightValue) { return Either::left($x)->notNull($rightValue); });
      $leftNullNotNull = $leftNull->flatMap(function($x) use ($rightValue) { return Either::left($x)->notNull($rightValue); });

      $this->assertFalse($rightNotNull->hasValue());
      $this->assertTrue($leftNotNull->hasValue());
      $this->assertFalse($leftNullNotNull->hasValue());
   }

   public function testFiltering() {
      $rightValue = "goodbye";
      $right = Either::right($rightValue);
      $left = Either::left("a", $rightValue);

      $leftTrue = $left->filter(true, $rightValue);
      $leftFalse = $left->filter(false, $rightValue);
      $rightTrue = $right->filter(true, $rightValue);
      $rightFalse = $right->filter(false, $rightValue);

      $this->assertTrue($leftTrue->hasValue());
      $this->assertFalse($leftFalse->hasValue());

      $this->assertFalse($rightTrue->hasValue());
      $this->assertFalse($rightFalse->hasValue());

      $rightNotA = $right->filterIf(function($x) { return $x != "a"; }, $rightValue);
      $leftNotA = $left->filterIf(function($x) { return $x != "a"; }, $rightValue);
      $rightA = $right->filterIf(function($x) { return $x == "a"; }, $rightValue);
      $leftA = $left->filterIf(function($x) { return $x == "a"; }, $rightValue);

      $this->assertFalse($rightNotA->hasValue());
      $this->assertFalse($leftNotA->hasValue());
      $this->assertFalse($rightA->hasValue());
      $this->assertTrue($leftA->hasValue());

      $leftNull = Either::left(null);
      $this->assertTrue($leftNull->hasValue());
      $rightNull = $leftNull->notNull($rightValue);
      $this->assertFalse($rightNull->hasValue());

      $leftEmpty = Either::left("");
      $this->assertTrue($leftEmpty->hasValue());
      $rightEmpty = $leftEmpty->notFalsy($rightValue);
      $this->assertFalse($rightEmpty->hasValue());
   }

   public function testContains() {
      $rightValue = "goodbye";
      $right = Either::right($rightValue);
      $leftString = Either::left("a", $rightValue);
      $leftInt = Either::left(1, $rightValue);

      $this->assertTrue($leftString->contains("a"));
      $this->assertFalse($leftString->contains("A"));
      $this->assertFalse($leftString->contains(1));
      $this->assertFalse($leftString->contains(null));

      $this->assertTrue($leftInt->contains(1));
      $this->assertFalse($leftInt->contains(2));
      $this->assertFalse($leftInt->contains("A"));
      $this->assertFalse($leftInt->contains(null));

      $this->assertFalse($right->contains(1));
      $this->assertFalse($right->contains(2));
      $this->assertFalse($right->contains("A"));
      $this->assertFalse($right->contains(null));
   }

   public function testExists() {
      $rightValue = "goodbye";
      $right = Either::right($rightValue);
      $left = Either::left(10, $rightValue);

      $rightFalse = $right->exists(function($x) { return $x == 10; });
      $leftTrue = $left->exists(function($x) { return $x >= 10; });
      $leftFalse = $left->exists(function($x) { return $x == "Thing"; });

      $this->assertTrue($leftTrue);
      $this->assertFalse($rightFalse);
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
            return Either::right('SomeComplexThing had an error!');
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
            return Either::right('SomeComplexThing had an error!');
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

      $name = $person->mapLeftSafely(function($person): string {
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
      $right = Either::right("Some Error Message");

      $leftOption = $left->toOption();
      $rightOption = $right->toOption();

      $this->assertEquals($leftOption, Option::left(10));
      $this->assertEquals($rightOption, Option::right());
   }
}