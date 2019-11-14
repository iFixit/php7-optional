<?php
declare(strict_types = 1);

require_once dirname(__FILE__) . '/../src/Either.php';

use Optional\Either;
use Optional\Option;

class EitherTest extends PHPUnit\Framework\TestCase {

   public function testCreateAndCheckExistence() {
      $rightValue = "goodbye";
      $rightEither = Either::right($rightValue);

      $this->assertFalse($rightEither->isLeft());

      $leftThing = Either::left(1, $rightValue);
      $leftNullable = Either::left(null, $rightValue);
      $leftClass = Either::left(new SomeObject(), $rightValue);

      $this->assertTrue($leftThing->isLeft());
      $this->assertTrue($leftNullable->isLeft());
      $this->assertTrue($leftClass->isLeft());

      $this->assertFalse($leftThing->isRight());
      $this->assertFalse($leftNullable->isRight());
      $this->assertFalse($leftClass->isRight());

      $noname = Either::fromArray(['name' => 'value'], 'noname', 'oh no');
      $this->assertTrue($noname->isRight());
      $this->assertFalse($noname->isLeft());

      $name = Either::fromArray(['name' => 'value'], 'name', 'oh no');
      $this->assertTrue($name->isLeft());
      $this->assertFalse($name->isRight());

      $nonameEither = Either::fromArray(['name' => 'value'], 'noname');
      $nonameNull = Either::fromArray(['name' => 'value'], 'noname');
      $this->assertFalse($nonameEither->isLeft());
      $this->assertFalse($nonameNull->isLeft());

      $right = Either::notNullLeft(null, $rightValue);
      $left = Either::notNullLeft('', $rightValue);

      $this->assertFalse($right->isLeft());
      $this->assertTrue($left->isLeft());
   }

   public function testCreateAndCheckExistenceWhen() {
      $rightValue = "goodbye";

      $leftThing = Either::leftWhen(1, $rightValue, function($x) { return $x > 0; });
      $leftThing2 = Either::leftWhen(-1, $rightValue, function($x) { return $x > 0; });

      $this->assertSame($leftThing->leftOr(-5), 1);
      $this->assertSame($leftThing2->leftOr(-5), -5);

      $leftThing3 = Either::rightWhen(1, $rightValue, function($x) { return $x > 0; });
      $leftThing4 = Either::rightWhen(-1, $rightValue, function($x) { return $x > 0; });

      $this->assertSame($leftThing3->leftOr(-5), -5);
      $this->assertSame($leftThing4->leftOr(-5), -1);

      $rightThing = Either::rightWhen(1, 100, function($x) { return $x > 0; });
      $rightThing2 = Either::rightWhen(-1, 100, function($x) { return $x > 0; });

      $this->assertSame($rightThing->rightOr(-5), 100);
      $this->assertSame($rightThing2->rightOr(-5), -5);

      $rightThing3 = Either::leftWhen(1, 100, function($x) { return $x > 0; });
      $rightThing4 = Either::leftWhen(-1, 100, function($x) { return $x > 0; });

      $this->assertSame($rightThing3->rightOr(-5), -5);
      $this->assertSame($rightThing4->rightOr(-5), 100);
   }

   public function testGettingValue() {
      $rightValue = "goodbye";
      $rightEither = Either::right($rightValue);

      $this->assertSame($rightEither->leftOr(-1), -1);

      $someObject = new SomeObject();

      $leftThing = Either::left(1, $rightValue);
      $leftClass = Either::left($someObject, $rightValue);

      $this->assertSame($leftThing->leftOr(-1), 1);
      $this->assertSame($leftClass->leftOr(-1), $someObject);
   }

   public function testGettingValueLazily() {
      $rightValue = "goodbye";
      $rightEither = Either::right($rightValue);

      $this->assertSame($rightEither->leftOrCreate(function($x) { return $x; }), $rightValue);

      $someObject = new SomeObject();

      $leftThing = Either::left(1, $rightValue);
      $leftClass = Either::left($someObject, $rightValue);

      $this->assertSame($leftThing->leftOrCreate(function($x) { return $x; }), 1);
      $this->assertSame($leftClass->leftOrCreate(function($x) { return $x; }), $someObject);

      $this->assertSame($leftThing->leftOrCreate(function($x) {
         $this->fail('Callback should not have been run!');
         return $x;
      }), 1);

      $this->assertSame($leftClass->leftOrCreate(function($x) {
         $this->fail('Callback should not have been run!');
         return $x;
      }), $someObject);
   }

   public function testGettingAlternitiveValue() {
      $rightValue = "goodbye";
      $someObject = new SomeObject();
      $rightEither = Either::right($rightValue);

      $this->assertFalse($rightEither->isLeft());

      $leftThing= $rightEither->orLeft(1);
      $leftClass = $rightEither->orLeft($someObject);

      $this->assertTrue($leftThing->isLeft());
      $this->assertTrue($leftClass->isLeft());

      $this->assertSame($leftThing->leftOr(-1), 1);
      $this->assertSame($leftClass->leftOr("-1"), $someObject);

      $lazyleft = $rightEither->orCreateLeft(function() { return 10; });
      $this->assertTrue($lazyleft->isLeft());
      $this->assertSame($lazyleft->leftOr(-1), 10);

      $lazyPassThrough = $leftThing->orCreateLeft(function() { return 10; });
      $this->assertTrue($lazyPassThrough->isLeft());
      $this->assertSame($lazyPassThrough->leftOr(-1), 1);
   }

   public function testGettingAlternitiveEither() {
      $rightValue = "goodbye";
      $someObject = new SomeObject();
      $rightEither = Either::right($rightValue);

      $this->assertFalse($rightEither->isLeft());

      $rightEither2 = $rightEither->elseLeft(Either::right($rightValue));
      $this->assertFalse($rightEither2->isLeft());

      $leftThing = $rightEither->elseLeft(Either::left(1, $rightValue));
      $leftClass = $rightEither->elseLeft(Either::left($someObject, $rightValue));

      $this->assertTrue($leftThing->isLeft());
      $this->assertTrue($leftClass->isLeft());

      $this->assertSame($leftThing->leftOr(-1), 1);
      $this->assertSame($leftClass->leftOr("-1"), $someObject);
   }

   public function testGettingAlternitiveEitherLazy() {
      $rightValue = "goodbye";
      $someObject = new SomeObject();
      $rightEither = Either::right($rightValue);

      $this->assertFalse($rightEither->isLeft());

      $rightEither2 = $rightEither->elseCreateLeft(function($x) {
         return Either::right($x);
      });
      $this->assertFalse($rightEither2->isLeft());

      $leftThing = $rightEither->elseCreateLeft(function($x) {
         return Either::left(1);
      });

      $leftClass = $rightEither->elseCreateLeft(function($x) use ($someObject) {
         return Either::left($someObject);
      });

      $this->assertTrue($leftThing->isLeft());
      $this->assertTrue($leftClass->isLeft());

      $this->assertSame($leftThing->leftOr(-1), 1);
      $this->assertSame($leftClass->leftOr("-1"), $someObject);

      $leftThing->elseCreateLeft(function($x) {
         $this->fail('Callback should not have been run!');
         return Either::right($x);
      });
      $leftClass->elseCreateLeft(function($x) {
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

      $this->assertFalse($rightUpper->isLeft());
      $this->assertTrue($leftUpper->isLeft());
      $this->assertSame($rightUpper->leftOr("b"), "b");
      $this->assertSame($leftUpper->leftOr("b"), "A");

      $right = Either::right("a");
      $left = Either::left("a", $rightValue);
      $leftNull = Either::left(null);

      $rightUpper = $right->mapRight(function($x) { return strtoupper($x); });
      $leftUpper = $left->mapRight(function($x) { return strtoupper($x); });

      $this->assertFalse($rightUpper->isLeft());
      $this->assertTrue($leftUpper->isLeft());
      $this->assertSame($rightUpper->rightOr("b"), "A");
      $this->assertSame($leftUpper->rightOr("b"), "b");

      $rightNotNull = $right->flatMap(function($x) use ($rightValue) { return Either::left($x)->leftNotNull($rightValue); });
      $leftNotNull = $left->flatMap(function($x) use ($rightValue) { return Either::left($x)->leftNotNull($rightValue); });
      $leftNullNotNull = $leftNull->flatMap(function($x) use ($rightValue) { return Either::left($x)->leftNotNull($rightValue); });

      $this->assertFalse($rightNotNull->isLeft());
      $this->assertTrue($leftNotNull->isLeft());
      $this->assertFalse($leftNullNotNull->isLeft());
   }

   public function testFiltering() {
      $rightValue = "goodbye";
      $right = Either::right($rightValue);
      $left = Either::left("a", $rightValue);

      $leftTrue = $left->filterLeft(true, $rightValue);
      $leftFalse = $left->filterLeft(false, $rightValue);
      $rightTrue = $right->filterLeft(true, $rightValue);
      $rightFalse = $right->filterLeft(false, $rightValue);

      $this->assertTrue($leftTrue->isLeft());
      $this->assertFalse($leftFalse->isLeft());

      $this->assertFalse($rightTrue->isLeft());
      $this->assertFalse($rightFalse->isLeft());

      $rightNotA = $right->filterLeftIf(function($x) { return $x != "a"; }, $rightValue);
      $leftNotA = $left->filterLeftIf(function($x) { return $x != "a"; }, $rightValue);
      $rightA = $right->filterLeftIf(function($x) { return $x == "a"; }, $rightValue);
      $leftA = $left->filterLeftIf(function($x) { return $x == "a"; }, $rightValue);

      $this->assertFalse($rightNotA->isLeft());
      $this->assertFalse($leftNotA->isLeft());
      $this->assertFalse($rightA->isLeft());
      $this->assertTrue($leftA->isLeft());

      $leftNull = Either::left(null);
      $this->assertTrue($leftNull->isLeft());
      $rightNull = $leftNull->leftNotNull($rightValue);
      $this->assertFalse($rightNull->isLeft());

      $leftEmpty = Either::left("");
      $this->assertTrue($leftEmpty->isLeft());
      $rightEmpty = $leftEmpty->leftNotFalsy($rightValue);
      $this->assertFalse($rightEmpty->isLeft());
   }

   public function testContains() {
      $rightValue = "goodbye";
      $right = Either::right($rightValue);
      $leftString = Either::left("a", $rightValue);
      $leftInt = Either::left(1, $rightValue);

      $this->assertTrue($leftString->leftContains("a"));
      $this->assertFalse($leftString->leftContains("A"));
      $this->assertFalse($leftString->leftContains(1));
      $this->assertFalse($leftString->leftContains(null));

      $this->assertTrue($leftInt->leftContains(1));
      $this->assertFalse($leftInt->leftContains(2));
      $this->assertFalse($leftInt->leftContains("A"));
      $this->assertFalse($leftInt->leftContains(null));

      $this->assertFalse($right->leftContains(1));
      $this->assertFalse($right->leftContains(2));
      $this->assertFalse($right->leftContains("A"));
      $this->assertFalse($right->leftContains(null));
   }

   public function testExists() {
      $rightValue = "goodbye";
      $right = Either::right($rightValue);
      $left = Either::left(10, $rightValue);

      $rightFalse = $right->existsLeft(function($x) { return $x == 10; });
      $leftTrue = $left->existsLeft(function($x) { return $x >= 10; });
      $leftFalse = $left->existsLeft(function($x) { return $x == "Thing"; });

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

      $this->assertSame($name->leftOr(''), 'FirstLast');
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

      $this->assertFalse($name->isLeft());
      $this->assertSame($name->leftOr('oh no'), 'oh no');
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

      $this->assertFalse($name->isLeft());
      $this->assertSame($name->leftOr('oh no'), 'oh no');

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

      $leftOption = $left->toOptionFromLeft();
      $rightOption = $right->toOptionFromLeft();

      $this->assertEquals($leftOption, Option::some(10));
      $this->assertEquals($rightOption, Option::none());
   }
}