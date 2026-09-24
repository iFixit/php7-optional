<?php

declare(strict_types = 1);

namespace Optional\Tests;

use Exception;
use Optional\Either;
use Optional\Exceptions\MissingValueException;
use Optional\Option;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use stdClass;

final class EitherTest extends TestCase
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

   public function testTheBoxIsAReadonlyClass(): void {
      $this->assertTrue((new \ReflectionClass(Either::class))->isReadOnly(), 'PHP enforces the immutability only while the class is readonly');
   }

   public function testTheThrowingGettersWithNoFactoryReturnTheValue(): void {
      $this->assertSame(1, Either::left(1)->getLeftOrThrow());
      $this->assertSame("e", Either::right("e")->getRightOrThrow());
      $this->assertSame(1, Either::success(1)->successOrThrow());
   }

   public function testGetRightOrThrowWithNoFactoryThrowsMissingValueException(): void {
      $this->expectException(MissingValueException::class);
      $this->expectExceptionMessage("Right value is missing.");

      Either::left(1)->getRightOrThrow();
   }

   public function testGetLeftOrThrowWithNoFactoryThrowsMissingValueException(): void {
      $this->expectException(MissingValueException::class);
      $this->expectExceptionMessage("Left value is missing.");

      Either::right(1)->getLeftOrThrow();
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

      $factoryRan = false;
      $kept = $leftThing->elseCreateLeft(function() use (&$factoryRan): Either {
         $factoryRan = true;
         return Either::left('unreachable');
      });
      $this->assertFalse($factoryRan, 'elseCreateLeft must not build an alternative for a Left');
      $this->assertSame($leftThing, $kept, 'a Left comes back as it was');

      $factoryRan = false;
      $kept = $leftClass->elseCreateLeft(function() use (&$factoryRan): Either {
         $factoryRan = true;
         return Either::left('unreachable');
      });
      $this->assertFalse($factoryRan, 'elseCreateLeft must not build an alternative for a Left');
      $this->assertSame($leftClass, $kept, 'a Left comes back as it was');


      $rightThing = $rightEither->elseCreateRight(function($_x) {
         return Either::right(1);
      });

      $this->assertTrue($rightThing->isRight());
      $this->assertSame($rightThing->rightOr(-1), $rightValue);

      $factoryRan = false;
      $kept = $rightThing->elseCreateRight(function() use (&$factoryRan): Either {
         $factoryRan = true;
         return Either::left('unreachable');
      });
      $this->assertFalse($factoryRan, 'elseCreateRight must not build an alternative for a Right');
      $this->assertSame($rightThing, $kept, 'a Right comes back as it was');
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
          function() { return -1; }
      );

      $this->assertSame($failure, $rightValue);
      $this->assertSame($success, 2);


      $whichRan = $right->match(
          function() { return 'left'; },
          function() { return 'right'; }
      );

      $this->assertSame('right', $whichRan, 'match on a Right runs the right branch');

      $whichRan = $left->match(
          function(int $x) { return "left:{$x}"; },
          function() { return 'right'; }
      );

      /**
       * @psalm-suppress RedundantConditionGivenDocblockType psalm folds this to
       * 'left:1' because Either::left(1) types the right branch as never, so it
       * proves the outcome statically. Keeping the runtime check anyway: it is
       * what catches match() wiring the two callables up backwards, which the
       * types alone would not.
       */
      $this->assertSame('left:1', $whichRan, 'match on a Left runs the left branch with its value');

      $leftRan = false;
      $right->matchLeft(function() use (&$leftRan) { $leftRan = true; });
      $this->assertFalse($leftRan, 'matchLeft must not run its callback on a Right');

      $hasMatched = false;
      $left->matchLeft(function($x) use (&$hasMatched) { return $hasMatched = $x == 1; });
      $this->assertTrue($hasMatched);

      $rightRan = false;
      $left->matchRight(function() use (&$rightRan) { $rightRan = true; });
      $this->assertFalse($rightRan, 'matchRight must not run its callback on a Left');

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
      $leftUpper = $left->mapRight(function() { return 'UNREACHABLE'; });

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
   }

   /**
    * Deprecated, not removed, so both have to keep working until a major
    * version takes them away.
    *
    * @psalm-suppress DeprecatedMethod
    */
   #[IgnoreDeprecations]
   public function testFilterLeftIfAndFilterRightIfStillWork(): void {
      $left = Either::left("a");
      $right = Either::right("a");

      $this->assertTrue($left->filterLeftIf(function($x) { return $x == "a"; }, 'no')->isLeft());
      $this->assertTrue($left->filterLeftIf(function($x) { return $x != "a"; }, 'no')->isRight());
      $this->assertTrue($right->filterLeftIf(function($x) { return $x == "a"; }, 'no')->isRight(), 'a Right is untouched');

      $this->assertTrue($right->filterRightIf(function($x) { return $x == "a"; }, 0)->isRight());
      $this->assertTrue($right->filterRightIf(function($x) { return $x != "a"; }, 0)->isLeft());
      $this->assertTrue($left->filterRightIf(function($x) { return $x == "a"; }, 0)->isLeft(), 'a Left is untouched');
   }

   /**
    * @psalm-suppress DeprecatedMethod
    */
   #[IgnoreDeprecations]
   public function testFilterLeftIfSaysWhatReplacesIt(): void {
      $this->expectUserDeprecationMessage(
         'Method Optional\Either::filterLeftIf() is deprecated since 4.0, pass the callable to filterLeft() instead'
      );

      $_ = Either::left(1)->filterLeftIf(fn(int $n) => $n > 0, 'no');
   }

   /**
    * @psalm-suppress DeprecatedMethod
    */
   #[IgnoreDeprecations]
   public function testFilterRightIfSaysWhatReplacesIt(): void {
      $this->expectUserDeprecationMessage(
         'Method Optional\Either::filterRightIf() is deprecated since 4.0, pass the callable to filterRight() instead'
      );

      $_ = Either::right('e')->filterRightIf(is_string(...), 0);
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

   public function testGetLeftOrThrow(): void {
      $left = Either::left(1);
      $this->assertSame(1, $left->getLeftOrThrow(function(): \Exception {
         throw new \LogicException('factory must not be called on a Left');
      }));

      $this->expectException(\RuntimeException::class);
      $this->expectExceptionMessage("no build id");

      Either::right(new \Exception("the api said no"))
         ->getLeftOrThrow(function(\Throwable $c): \Exception {
            return new \RuntimeException("no build id", 0, $c);
         });
   }

   /**
    * The factory is handed the other side's value, which is what lets a caller
    * keep the original failure as the cause. Built through fromCallable so the
    * either's side is not a compile-time constant, which is the realistic shape
    * and keeps both branches live.
    */
   public function testGetLeftOrThrowPassesTheRightValueToTheFactory(): void {
      $cause = new \Exception("the api said no");
      $either = Either::fromCallable(function() use ($cause): int { throw $cause; });

      try {
         $either->getLeftOrThrow(function(\Throwable $c): \Exception {
            return new \RuntimeException("no build id", 0, $c);
         });
      } catch (\RuntimeException $e) {
         $caught = $e;
      }

      $this->assertInstanceOf(\RuntimeException::class, $caught, 'getLeftOrThrow must throw on a Right');
      $this->assertSame($cause, $caught->getPrevious(), 'the factory receives the right value as the cause');
   }

   public function testGetRightOrThrow(): void {
      $right = Either::right("nope");
      $this->assertSame("nope", $right->getRightOrThrow(function(): \Exception {
         throw new \LogicException('factory must not be called on a Right');
      }));

      $this->expectException(\RuntimeException::class);
      $this->expectExceptionMessage("expected an error, got 1");

      Either::left(1)->getRightOrThrow(function($leftValue): \Exception {
         return new \RuntimeException("expected an error, got {$leftValue}");
      });
   }

   public function testTapLeftAndTapRight(): void {
      $seen = null;
      $left = Either::left(5);
      $returned = $left->tapLeft(function(int $x) use (&$seen): void { $seen = $x; });

      $this->assertSame(5, $seen);
      $this->assertSame($left, $returned, 'tapLeft returns the original either');

      $ran = false;
      $left->tapRight(function() use (&$ran): void { $ran = true; });
      $this->assertFalse($ran, 'tapRight must not run on a Left');

      $seenRight = null;
      $right = Either::right("boom");
      $right->tapRight(function(string $e) use (&$seenRight): void { $seenRight = $e; });
      $this->assertSame("boom", $seenRight);

      $ranLeft = false;
      $right->tapLeft(function() use (&$ranLeft): void { $ranLeft = true; });
      $this->assertFalse($ranLeft, 'tapLeft must not run on a Right');

      $doubled = Either::left(5)
         ->tapLeft(function(): void {})
         ->mapLeft(function(int $x): int { return $x * 2; });
      $this->assertSame(10, $doubled->leftOr(-1), 'tapLeft does not break the chain');
   }

   public function testNotFalsyLeft(): void {
      $this->assertTrue(Either::notFalsyLeft(0, "was falsy")->isRight());
      $this->assertTrue(Either::notFalsyLeft('', "was falsy")->isRight());
      $this->assertTrue(Either::notFalsyLeft(null, "was falsy")->isRight());
      $this->assertSame("was falsy", Either::notFalsyLeft([], "was falsy")->rightOr("x"));

      $kept = Either::notFalsyLeft(10, "was falsy");
      $this->assertTrue($kept->isLeft());
      $this->assertSame(10, $kept->leftOr(-1));
   }

   public function testFromCallable(): void {
      $left = Either::fromCallable(function(): int { return 7; });
      $this->assertTrue($left->isLeft());
      $this->assertSame(7, $left->leftOr(-1));

      $right = Either::fromCallable(function(): int { throw new \Exception("boom"); });
      $this->assertTrue($right->isRight(), 'a thrown Exception becomes a Right holding it');
      $this->assertSame("boom", $right->getRightOrThrow(function(): \Exception {
         return new \LogicException('unreachable');
      })->getMessage());
   }

   /**
    * The counterpart to OptionTest::testFromCallableLetsErrorsThrough. Either
    * keeps what it caught, so catching Throwable loses nothing; Option discards
    * it, so Option::fromCallable deliberately catches only Exception.
    */
   public function testFromCallableCatchesErrorsToo(): void {
      $either = Either::fromCallable(function(): int { throw new \TypeError("not an exception"); });

      $this->assertTrue($either->isRight(), 'an Error lands on the right rather than escaping');
      $this->assertInstanceOf(\TypeError::class, $either->rightOr(new \Exception('x')));
   }

   public function testFromCallableCatchesTheNamedClassAndItsSubclasses(): void {
      $named = Either::fromCallable(function(): int { throw new \OutOfBoundsException('no user 5'); }, \OutOfBoundsException::class);
      $this->assertSame('no user 5', $named->mapRight(fn(\OutOfBoundsException $e) => $e->getMessage())->rightOr('x'));

      $subclass = Either::fromCallable(function(): int { throw new \OutOfBoundsException('no user 5'); }, \RuntimeException::class);
      $this->assertTrue($subclass->isRight(), 'a subclass of the named class is caught, as a catch clause would');
   }

   public function testFromCallableLetsAnyOtherClassThrough(): void {
      $this->expectException(\LogicException::class);
      Either::fromCallable(function(): int { throw new \LogicException('a bug'); }, \OutOfBoundsException::class);
   }

   public function testMapLeftSafelyCatchesTheNamedClass(): void {
      $right = Either::left(1)->mapLeftSafely(function(int $_x): int { throw new \OutOfBoundsException('x'); }, \OutOfBoundsException::class);
      $this->assertTrue($right->isRight(), 'the named class lands on the right');
   }

   public function testMapLeftSafelyLetsAnyOtherClassThrough(): void {
      $this->expectException(\LogicException::class);
      $_ = Either::left(1)->mapLeftSafely(function(int $_x): int { throw new \LogicException('a bug'); }, \OutOfBoundsException::class);
   }

   public function testMapRightSafelyCatchesTheNamedClass(): void {
      $right = Either::right('e')->mapRightSafely(function(string $_e): string { throw new \OutOfBoundsException('x'); }, \OutOfBoundsException::class);
      $this->assertTrue($right->isRight(), 'the named class replaces the right');
   }

   public function testMapRightSafelyLetsAnyOtherClassThrough(): void {
      $this->expectException(\LogicException::class);
      $_ = Either::right('e')->mapRightSafely(function(string $_e): string { throw new \LogicException('a bug'); }, \OutOfBoundsException::class);
   }

   /**
    * Either::fromArray used isset() while Option::fromArray used
    * array_key_exists(), so the two disagreed about a key holding null. They
    * agree now: present means present, and null is collapsed explicitly.
    */
   public function testFromArrayTreatsAPresentNullAsPresent(): void {
      $either = Either::fromArray(['k' => null], 'k', 'missing');

      $this->assertTrue($either->isLeft(), 'a key holding null is still a key that exists');
      $this->assertNull($either->leftOr('fallback'));

      $option = Option::fromArray(['k' => null], 'k');
      $this->assertSame(
         $option->hasValue(),
         $either->isLeft(),
         'Option::fromArray and Either::fromArray must agree about a present null'
      );

      $collapsed = Either::fromArray(['k' => null], 'k', 'missing')->leftNotNull('was null');
      $this->assertTrue($collapsed->isRight(), 'leftNotNull is how you opt into treating null as missing');
      $this->assertSame('was null', $collapsed->rightOr('x'));

      $absent = Either::fromArray(['k' => null], 'nope', 'missing');
      $this->assertTrue($absent->isRight());
      $this->assertSame('missing', $absent->rightOr('x'));
   }

   public function testToOptionFromLeftKeepsTheLeftValue(): void {
      $this->assertSame(10, Either::left(10)->toOptionFromLeft()->valueOr(-1));
      $this->assertFalse(Either::right('err')->toOptionFromLeft()->hasValue());
   }

   /**
    * The counterpart to OptionTest::testMapSafelyLetsErrorsThrough. Nothing
    * pinned either half before, so the asymmetry the docs insist on was
    * free to drift.
    */
   public function testMapLeftSafelyCatchesThrowableAndKeepsIt(): void {
      $fromException = Either::left(1)->mapLeftSafely(function(int $_x): int { throw new \Exception("boom"); });

      $this->assertTrue($fromException->isRight());
      $this->assertSame("boom", $fromException->rightOr(new \Exception('x'))->getMessage());

      $fromError = Either::left(1)->mapLeftSafely(function(int $_x): int { throw new \TypeError("not an exception"); });

      $this->assertTrue($fromError->isRight(), 'an Error lands on the right rather than escaping');
      $this->assertInstanceOf(\TypeError::class, $fromError->rightOr(new \Exception('x')));
   }

   /**
    * fromArray with no $rightValue fabricates its own Exception. Both the
    * docblock and AGENTS.md describe this, and nothing tested it, so the whole
    * branch could be deleted with CI green.
    */
   public function testFromArrayFabricatesAnExceptionWhenNoRightIsGiven(): void {
      foreach ([Either::fromArray(['k' => 'v'], 'nope'), Either::fromArray(['k' => 'v'], 'nope', null)] as $either) {
         $this->assertTrue($either->isRight());

         $right = $either->rightOr('not an exception');
         $this->assertInstanceOf(\Exception::class, $right);
         $this->assertSame('Either got null for rightValue', $right->getMessage());
      }

      $explicit = Either::fromArray(['k' => 'v'], 'nope', 'my own message');
      $this->assertSame('my own message', $explicit->rightOr('x'), 'an explicit right value is kept as given');
   }

   /**
    * Four left-side methods had no right-side twin, which makes the API
    * unguessable at exactly the point where every other pair mirrors.
    *
    * Checked in both directions. The first version only walked left to right,
    * so a right-only method would have gone unnoticed, and the right side is
    * where new methods get added because that is where errors live.
    */
   public function testFilterLeftAndFilterRightTakeEitherSpelling(): void {
      $this->assertSame(10, Either::left(10)->filterLeft(true, 'too small')->leftOr(-1));
      $this->assertSame('too small', Either::left(10)->filterLeft(false, 'too small')->rightOr('x'));

      $big = function(int $n): bool { return $n > 5; };
      $this->assertSame(10, Either::left(10)->filterLeft($big, 'too small')->leftOr(-1));
      $this->assertSame('too small', Either::left(4)->filterLeft($big, 'too small')->rightOr('x'));

      $longEnough = function(string $s): bool { return strlen($s) > 1; };
      $this->assertSame('err', Either::right('err')->filterRight($longEnough, 0)->rightOr('x'));
      $this->assertSame(0, Either::right('e')->filterRight($longEnough, 0)->leftOr(-1));

      $ranOnRight = false;
      $untouchedRight = Either::right('e')->filterLeft(function() use (&$ranOnRight): bool { $ranOnRight = true; return true; }, 'x');
      $this->assertFalse($ranOnRight, 'filterLeft must not run its callable on a Right');
      $this->assertSame('e', $untouchedRight->rightOr('x'));

      $ranOnLeft = false;
      $untouchedLeft = Either::left(1)->filterRight(function() use (&$ranOnLeft): bool { $ranOnLeft = true; return true; }, 'x');
      $this->assertFalse($ranOnLeft, 'filterRight must not run its callable on a Left');
      $this->assertSame(1, $untouchedLeft->leftOr(-1));
   }

   public function testFlatMapRight(): void {
      $recovered = Either::right('not found')->flatMapRight(function(string $_error): Either {
         return Either::left('default');
      });

      $this->assertTrue($recovered->isLeft(), 'flatMapRight can turn an error into a success');
      $this->assertSame('default', $recovered->leftOr('x'));

      $rethrown = Either::right('on fire')->flatMapRight(function(string $error): Either {
         return Either::right("still {$error}");
      });

      $this->assertTrue($rethrown->isRight());
      $this->assertSame('still on fire', $rethrown->rightOr('x'));

      $ran = false;
      $untouched = Either::left(1)->flatMapRight(function() use (&$ran): Either {
         $ran = true;
         return Either::right('never');
      });

      $this->assertFalse($ran, 'flatMapRight must not run its callback on a Left');
      $this->assertSame(1, $untouched->leftOr(-1));
   }

   public function testMapRightSafely(): void {
      $thrower = function(string $error): string { throw new \Exception("could not parse: {$error}"); };

      $caught = Either::right('{bad json}')->mapRightSafely($thrower);
      $thrown = $caught->rightOr('x');
      $this->assertInstanceOf(\Exception::class, $thrown);
      $this->assertSame('could not parse: {bad json}', $thrown->getMessage());

      $fromError = Either::right('x')->mapRightSafely(function(): string { throw new \TypeError("boom"); });
      $this->assertInstanceOf(\TypeError::class, $fromError->rightOr('x'), 'catches Throwable like mapLeftSafely');

      $ran = false;
      $left = Either::left(1)->mapRightSafely(function() use (&$ran): string { $ran = true; return 'never'; });
      $this->assertFalse($ran, 'mapRightSafely must not run its callback on a Left');
      $this->assertSame(1, $left->leftOr(-1));

      $mapped = Either::right('ab')->mapRightSafely(function(string $e): string { return strtoupper($e); });
      $this->assertSame('AB', $mapped->rightOr('x'));
   }

   public function testNotFalseLeftAndNotFalseRight(): void {
      $index = Either::notFalseLeft(array_search('a', ['a', 'b']), 'not in the list');
      $this->assertSame(0, $index->leftOr(-1), 'index 0 is found');

      $missing = Either::notFalseLeft(array_search('z', ['a', 'b']), 'not in the list');
      $this->assertSame('not in the list', $missing->rightOr('x'));

      $this->assertSame('', Either::notFalseLeft('', 'was false')->leftOr('x'), 'an empty string is not false');
      $this->assertSame('no error', Either::notFalseRight(false, 'no error')->leftOr('x'));
      $this->assertSame('', Either::notFalseRight('', 'no error')->rightOr('x'), 'an empty string is not false');
   }

   public function testLeftNotFalseAndRightNotFalse(): void {
      $this->assertSame('was false', Either::left(false)->leftNotFalse('was false')->rightOr('x'));
      $this->assertSame(0, Either::left(0)->leftNotFalse('was false')->leftOr(-1));
      $this->assertSame('e', Either::right('e')->leftNotFalse('was false')->rightOr('x'), 'a Right is untouched');

      $this->assertSame('was false', Either::right(false)->rightNotFalse('was false')->leftOr('x'));
      $this->assertSame('', Either::right('')->rightNotFalse('was false')->rightOr('x'));
      $this->assertSame(1, Either::left(1)->rightNotFalse('was false')->leftOr(-1), 'a Left is untouched');
   }

   public function testNotNullRightAndNotFalsyRight(): void {
      $this->assertTrue(Either::notNullRight(null, 'no error')->isLeft());
      $this->assertSame('no error', Either::notNullRight(null, 'no error')->leftOr('x'));
      $this->assertSame('boom', Either::notNullRight('boom', 'no error')->rightOr('x'));

      $this->assertTrue(Either::notFalsyRight('', 'no error')->isLeft());
      $this->assertTrue(Either::notFalsyRight(0, 'no error')->isLeft());
      $this->assertSame('boom', Either::notFalsyRight('boom', 'no error')->rightOr('x'));
      $this->assertSame('0.0', Either::notFalsyRight('0.0', 'no error')->rightOr('x'), "'0.0' is truthy in PHP");
   }

   public function testEveryOneSidedMethodHasItsTwin(): void {
      $reflection = new \ReflectionClass(Either::class);
      $names = [];

      foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
         if ($method->getDeclaringClass()->getName() === Either::class) {
            $names[] = $method->getName();
         }
      }

      $unmirrored = [];

      foreach ($names as $name) {
         foreach ([['Left', 'Right'], ['left', 'right'], ['Right', 'Left'], ['right', 'left']] as [$from, $to]) {
            if (!str_contains($name, $from)) {
               continue;
            }

            $twin = str_replace($from, $to, $name);

            if (!in_array($twin, $names, true)) {
               $unmirrored[] = "{$name} has no {$twin}";
            }
         }
      }

      $this->assertSame([], $unmirrored, 'Either is a symmetric box; a method on one side needs its twin');
   }

   /**
    * The success/failure names are delegation, not a second implementation.
    * Asserting they agree with their targets is what stops the two drifting.
    */
   public function testSuccessAndFailureNamesDelegate(): void {
      $ok = Either::success(42);
      $failed = Either::failure('the api said no');

      $this->assertSame((string) Either::left(42), (string) $ok);
      $this->assertSame((string) Either::right('the api said no'), (string) $failed);

      $this->assertSame($ok->isLeft(), $ok->isSuccess());
      $this->assertSame($ok->isRight(), $ok->isFailure());
      $this->assertSame($failed->isLeft(), $failed->isSuccess());
      $this->assertSame($failed->isRight(), $failed->isFailure());

      $double = function(int $n): int { return $n * 2; };
      $describe = function(string $error): string { return "request {$error}"; };

      $this->assertSame((string) $ok->mapLeft($double), (string) $ok->mapSuccess($double));
      $this->assertSame((string) $failed->mapRight($describe), (string) $failed->mapFailure($describe));

      $this->assertSame($ok->leftOr(0), $ok->successOr(0));
      $this->assertSame($failed->leftOr(0), $failed->successOr(0));

      $this->assertSame(42, $ok->successOrThrow(function(): \Exception {
         throw new \LogicException('factory must not run on a success');
      }));
   }

   /**
    * The aliases carry their own copy of the target's psalm annotations, because
    * dropping them loses every type: psalm reports a bare `Optional\Either` for
    * mapSuccess and `mixed` for successOr when it has to infer from the
    * delegated call. Hand-copied annotations drift, and a runtime test cannot
    * see it, so compare the annotations themselves.
    */
   public function testAliasAnnotationsMatchTheirTargets(): void {
      $pairs = [
         'success' => 'left',
         'failure' => 'right',
         'isSuccess' => 'isLeft',
         'isFailure' => 'isRight',
         'mapSuccess' => 'mapLeft',
         'mapFailure' => 'mapRight',
         'successOr' => 'leftOr',
         'failureOr' => 'rightOr',
         'successOrThrow' => 'getLeftOrThrow',
      ];

      $reflection = new \ReflectionClass(Either::class);

      /**
       * Parameter names differ on purpose ($value reads better than $leftValue
       * on a method called success), so compare the types and drop the names.
       *
       * @return list<string>
       */
      $annotations = static function (string $method) use ($reflection): array {
         $doc = (string) $reflection->getMethod($method)->getDocComment();
         preg_match_all('/@psalm-(?:return|param|pure|mutation-free)[^\n]*/', $doc, $found);

         return array_map(
            static fn(string $line): string => trim((string) preg_replace('/\s+\$\w+\s*$/', '', trim($line))),
            $found[0]
         );
      };

      foreach ($pairs as $alias => $target) {
         $this->assertSame(
            $annotations($target),
            $annotations($alias),
            "Either::{$alias}() and Either::{$target}() have drifted apart. The alias delegates, "
            . "so its psalm annotations have to say the same thing as the method it calls."
         );
      }
   }

   public function testFailureOr(): void {
      $this->assertSame('timeout', Either::failure('timeout')->failureOr('no error'));
      $this->assertSame('no error', Either::success(42)->failureOr('no error'));
      $this->assertSame(
         Either::failure('timeout')->rightOr('no error'),
         Either::failure('timeout')->failureOr('no error')
      );
   }

   public function testSuccessOrThrowReceivesTheError(): void {
      $this->expectException(\RuntimeException::class);
      $this->expectExceptionMessage('could not load: the api said no');

      Either::failure('the api said no')->successOrThrow(function(string $error): \Exception {
         return new \RuntimeException("could not load: {$error}");
      });
   }

   public function testToOptionFromRight(): void {
      $this->assertSame("boom", Either::right("boom")->toOptionFromRight()->valueOr("x"));
      $this->assertFalse(Either::left(1)->toOptionFromRight()->hasValue());
   }
}
