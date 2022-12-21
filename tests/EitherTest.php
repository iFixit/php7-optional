<?php

declare(strict_types = 1);

namespace Optional\Tests;

use Exception;
use Optional\Either;
use Optional\Option;
use PHPUnit\Framework\TestCase;
use stdClass;

class EitherTest extends TestCase
{
   public function testCreateAndCheckExistence(): void
   {
      $rightValue = "goodbye";
      $rightEither = Either::right($rightValue);

      $this->assertFalse($rightEither->isLeft());

      $leftThing = Either::left(1);
      $leftNullable = Either::left(null);
      $leftClass = Either::left(new stdClass());

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

   public function testCreateAndCheckExistenceWhen(): void {
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

   public function testGettingValue(): void {
      $rightValue = "goodbye";
      $rightEither = Either::right($rightValue);

      $this->assertSame($rightEither->leftOr(-1), -1);

      $someObject = new stdClass();

      $leftThing = Either::left(1);
      $leftClass = Either::left($someObject);

      $this->assertSame($leftThing->leftOr(-1), 1);
      $this->assertSame($leftClass->leftOr(-1), $someObject);
   }

   public function testGettingValueLazily(): void {
      $leftEither = Either::left("Hello");

      $this->assertSame($leftEither->rightOrCreate(function($x) { return $x; }), "Hello");

      $someObject = new stdClass();

      $rightThing = Either::right(1);
      $rightClass = Either::right($someObject);

      $this->assertSame($rightThing->rightOrCreate(function($_x) {
         $this->fail('Callback should not have been run!');
      }), 1);

      $this->assertSame($rightClass->rightOrCreate(function($_x) {
         $this->fail('Callback should not have been run!');
      }), $someObject);
   }

   public function testGettingAlternitiveValue(): void {
      $rightValue = "goodbye";
      $someObject = new stdClass();
      $rightEither = Either::right($rightValue);

      $this->assertSame($rightEither, $rightEither->orRight(1));

      $this->assertFalse($rightEither->isLeft());

      $leftThing = $rightEither->orLeft(1);
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


      $leftEither = Either::left("Hello");

      $this->assertSame($leftEither, $leftEither->orLeft(1));

      $rightThing= $leftEither->orRight(1);
      $rightClass = $leftEither->orRight($someObject);

      $this->assertTrue($rightThing->isRight());
      $this->assertTrue($rightClass->isRight());

      $lazyRight = $leftEither->orCreateRight(function() { return 10; });
      $this->assertTrue($lazyRight->isRight());
      $this->assertSame($lazyRight->rightOr(-1), 10);

      $lazyPassThrough = $rightEither->orCreateRight(function() { return 10; });
      $this->assertTrue($lazyPassThrough->isRight());
      $this->assertSame($lazyPassThrough->rightOr(-1), $rightValue);
   }

   public function testGettingAlternitiveEither(): void {
      $rightValue = "goodbye";
      $someObject = new stdClass();
      $rightEither = Either::right($rightValue);

      $this->assertFalse($rightEither->isLeft());

      $rightEither2 = $rightEither->elseLeft(Either::right($rightValue));
      $this->assertFalse($rightEither2->isLeft());

      $this->assertSame($rightEither, $rightEither->elseRight(Either::left(5)));
      $leftThing = $rightEither->elseLeft(Either::left(1));
      $leftClass = $rightEither->elseLeft(Either::left($someObject));

      $this->assertSame($leftThing, $leftThing->elseLeft(Either::left(5)));

      $this->assertTrue($leftThing->isLeft());
      $this->assertTrue($leftClass->isLeft());

      $this->assertSame($leftThing->leftOr(-1), 1);
      $this->assertSame($leftClass->leftOr("-1"), $someObject);


      $leftEither = Either::left("Hello");

      $rightThing = $leftEither->elseRight(Either::right(1));
      $rightClass = $leftEither->elseRight(Either::right($someObject));

      $this->assertTrue($rightThing->isRight());
      $this->assertTrue($rightClass->isRight());

      $this->assertSame($rightThing->rightOr(-1), 1);
      $this->assertSame($rightClass->rightOr("-1"), $someObject);
   }

   public function testGettingAlternitiveEitherLazy(): void {
      $rightValue = "goodbye";
      $someObject = new stdClass();
      $rightEither = Either::right($rightValue);

      $this->assertFalse($rightEither->isLeft());

      $rightEither2 = $rightEither->elseCreateLeft(function($x) {
         return Either::right($x);
      });
      $this->assertFalse($rightEither2->isLeft());

      $leftThing = $rightEither->elseCreateLeft(function($_x) {
         return Either::left(1);
      });

      $leftClass = $rightEither->elseCreateLeft(function($_x) use ($someObject) {
         return Either::left($someObject);
      });

      $this->assertTrue($leftThing->isLeft());
      $this->assertTrue($leftClass->isLeft());

      $this->assertSame($leftThing->leftOr(-1), 1);
      $this->assertSame($leftClass->leftOr("-1"), $someObject);

      /** @psalm-suppress UnusedMethodCall so we can show that psalm even knows this is impossible */
      $leftThing->elseCreateLeft(
         /** @return never */
         function(string $_x) {
            $this->fail('Callback should not have been run!');
         }
      );

      /** @psalm-suppress UnusedMethodCall so we can show that psalm even knows this is impossible */
      $leftClass->elseCreateLeft(
         /** @return never */
         function(string $_x) {
            $this->fail('Callback should not have been run!');
         }
      );


      $rightThing = $rightEither->elseCreateRight(function($_x) {
         return Either::right(1);
      });

      $this->assertTrue($rightThing->isRight());
      $this->assertSame($rightThing->rightOr(-1), $rightValue);

      /** @psalm-suppress UnusedMethodCall so we can show that psalm even knows this is impossible */
      $rightThing->elseCreateRight(
         /** @return never */
         function(string $_x) {
            $this->fail('Callback should not have been run!');
         }
      );
   }

   public function testMatching(): void {
      $rightValue = "goodbye";
      $right = Either::right($rightValue);
      $left = Either::left(1);

      $failure = $right->match(
          function($_x) { return 2; },
          function($x) { return $x; }
      );

      $success = $left->match(
          function($_x) { return 2; },
          /** @psalm-suppress NoValue */
          function(int $x) { return $x; }
      );

      $this->assertSame($failure, $rightValue);
      $this->assertSame($success, 2);


      $hasMatched = $right->match(
          function($_x) { $this->fail('Callback should not have been run!'); },
          function($_x) { return true; }
      );

      $this->assertTrue($hasMatched);

      $hasMatched = $left->match(
          function($x) { return $x == 1; },
          function($_x) { $this->fail('Callback should not have been run!'); }
      );

      $this->assertTrue($hasMatched);

      $right->matchleft(function($_x) { $this->fail('Callback should not have been run!'); });

      $hasMatched = false;
      $left->matchleft(function($x) use (&$hasMatched) { return $hasMatched = $x == 1; });
      $this->assertTrue($hasMatched);

      $left->matchRight(function() { $this->fail('Callback should not have been run!'); });
      $hasMatched = false;

      $right->matchRight(function() use (&$hasMatched) { $hasMatched = true; });
      $this->assertTrue($hasMatched);
   }

   public function testMapping(): void {
      $rightValue = "goodbye";
      $right = Either::right($rightValue);
      $left = Either::left("a");

      /** @psalm-suppress NoValue */
      $rightUpper = $right->mapLeft(function($x) { return strtoupper($x); });
      /** @psalm-suppress NoValue */
      $leftUpper = $left->mapLeft(function($x) { return strtoupper($x); });

      $this->assertFalse($rightUpper->isLeft());
      $this->assertTrue($leftUpper->isLeft());
      $this->assertSame($rightUpper->leftOr("b"), "b");
      $this->assertSame($leftUpper->leftOr("b"), "A");

      $right = Either::right("a");
      $left = Either::left("a");

      $rightUpper = $right->mapRight(function(string $x) { return strtoupper($x); });
      $leftUpper = $left->mapRight(
         /** @psalm-suppress NoValue */
         function(string $x) { return strtoupper($x); }
      );

      $this->assertFalse($rightUpper->isLeft());
      $this->assertTrue($leftUpper->isLeft());
      $this->assertSame($rightUpper->rightOr("b"), "A");
      $this->assertSame($leftUpper->rightOr("b"), "b");

      $rightNotNull = $right->flatMapLeft(function($_x) use ($rightValue) { return Either::left($rightValue); });
      $leftNotNull = $left->flatMapLeft(function($_x) use ($rightValue) { return Either::left($rightValue); });

      $this->assertFalse($rightNotNull->isLeft());
      $this->assertTrue($leftNotNull->isLeft());
   }

   public function testFiltering(): void {
      $rightValue = "goodbye";
      $right = Either::right($rightValue);
      $left = Either::left("a");

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

      $rightNull = Either::right(null);
      $this->assertFalse($rightNull->isLeft());
      $left = $rightNull->rightNotNull("Hello");
      $this->assertTrue($left->isLeft());

      $rightEmpty = Either::right("");
      $this->assertTrue($rightEmpty->isRight());
      $leftEmpty = $rightEmpty->rightNotFalsy("Hello");
      $this->assertFalse($leftEmpty->isRight());

      $rightTrue = $right->filterRight(true, "Hello");
      $rightFalse = $right->filterRight(false, "Hello");
      $leftTrue = $left->filterRight(true, "Hello");

      $this->assertFalse($leftTrue->isRight());
      $this->assertTrue($rightTrue->isRight());
      $this->assertFalse($rightFalse->isRight());

      $rightNotA = $right->filterRightIf(function($x) { return $x != "a"; }, $rightValue);
      $leftNotA = $left->filterRightIf(function($x) { return $x != "a"; }, $rightValue);
      /** @psalm-suppress DocblockTypeContradiction */
      $rightA = $right->filterRightIf(function($x) { return $x == "a"; }, $rightValue);
      $leftA = $left->filterRightIf(function($x) { return $x == "a"; }, $rightValue);

      $this->assertTrue($rightNotA->isRight());
      $this->assertFalse($leftNotA->isRight());
      $this->assertFalse($rightA->isRight());
      $this->assertFalse($leftA->isRight());
   }

   public function testContains(): void {
      $rightValue = "goodbye";
      $right = Either::right($rightValue);
      $leftString = Either::left("a");
      $leftInt = Either::left(1);

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

      $rightString = Either::right("a");
      $rightInt = Either::right(1);

      $this->assertTrue($rightString->rightContains("a"));
      $this->assertFalse($rightString->rightContains("A"));
      $this->assertFalse($rightString->rightContains(1));
      $this->assertFalse($rightString->rightContains(null));

      $this->assertTrue($rightInt->rightContains(1));
      $this->assertFalse($right->rightContains(2));
      $this->assertFalse($right->rightContains("A"));
      $this->assertFalse($right->rightContains(null));

      $this->assertFalse($leftString->rightContains(2));
   }

   public function testExists(): void {
      $rightValue = "goodbye";
      $right = Either::right($rightValue);
      $left = Either::left(10);

      $rightFalse = $right->existsLeft(function($x) { return $x == 10; });
      $leftTrue = $left->existsLeft(function($x) { return $x >= 10; });
      $leftFalse = $left->existsLeft(function($x) { return $x == "Thing"; });

      $this->assertTrue($leftTrue);
      $this->assertFalse($rightFalse);
      $this->assertFalse($leftFalse);


      $right = Either::right(10);
      $left = Either::left(10);

      $exists = $right->existsRight(function($x) { return $x == 10; });
      $this->assertTrue($exists);

      $exists = $left->existsRight(function($x) { return $x >= 10; });
      $this->assertFalse($exists);

      $exists = $right->existsRight(function($x) { return $x == "Thing"; });
      $this->assertFalse($exists);
   }

   public function testFlatMap(): void {
      $leftPerson = [
         'name' => [
            'first' => 'First',
            'last' => 'Last'
         ]
      ];

      /** @var Either<array{first: string, last: string},never> */
      $person = Either::fromArray($leftPerson, 'name', 'name was missing');

      $name = $person->andThen(
         /**
          * @param array{first: string, last: string} $person
          * @return Either<string, never>
          */
         function(array $person) {
            $fullName = $person['first'] . $person['last'];
            return Either::left($fullName);
         }
      );

      $this->assertSame($name->leftOr(''), 'FirstLast');
   }

   public function testFlatMapWithException(): void {
      $leftPerson = [
         'name' => [
            'first' => 'First',
            'last' => 'Last'
         ]
      ];

      /** @var Either<array{first: string, last: string}, never> */
      $person = Either::fromArray($leftPerson, 'name', 'name was missing');

      $name = $person->andThen(
         /**
          * @param array{first: string, last: string} $_person
          * @return Either<string, never>
          */
         function(array $_person) {
            try {
               throw new Exception('BAD VALUE');
            }
            catch(\Exception $e) {
               return Either::left($e->getMessage());
            }
         }
      );

      $this->assertTrue($name->isLeft());
      $this->assertSame($name->leftOr('oh no'), 'BAD VALUE');
   }

   public function testSafelyMapWithException(): void {
      $leftPerson = [
         'name' => [
            'first' => 'First',
            'last' => 'Last'
         ]
      ];

      /** @var Either<array{first: string, last: string}, never> */
      $person = Either::fromArray($leftPerson, 'name', 'name was missing');

      /** @var Either<never, \Exception> */
      $name = $person->mapLeftSafely(
         /** @param array{first: string, last: string} $_person */
         function(array $_person) {
            throw new \Exception("Forcing left exception");
         }
      );

      $this->assertFalse($name->isLeft());
      $this->assertSame($name->leftOr('oh no'), 'oh no');

      $out = $name->match(
         function ($_leftValue) { $this->fail('Callback should not have been run!'); },
         function (\Exception $exception) { return $exception->getMessage(); }
      );

      $this->assertSame($out, "Forcing left exception");
   }

   public function testToOption(): void {
      $left = Either::left(10);
      $right = Either::right("Some Error Message");

      $leftOption = $left->toOptionFromLeft();
      $rightOption = $right->toOptionFromLeft();

      $this->assertEquals($leftOption, Option::some(10));
      $this->assertEquals($rightOption, Option::none());
   }

   public function testToString(): void {
      $this->assertEquals("Left(null)", (string)Either::left(null));
      $this->assertEquals("Left(10)", (string)Either::left(10));

      $this->assertEquals("Right(null)", (string)Either::right(null));
      $this->assertEquals("Right(10)", (string)Either::right(10));
   }
}