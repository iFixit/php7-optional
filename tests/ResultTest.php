<?php
declare(strict_types = 1);

require_once dirname(__FILE__) . '/../src/Result.php';

use Optional\Result;

class ResultTest extends PHPUnit\Framework\TestCase {
   public function testCreateAndCheckExistence() {
      $errorValue = "goodbye";
      $errorResult = Result::error($errorValue);

      $this->assertFalse($errorResult->isOkay());

      $okayThing = Result::okay(1);
      $okayNullable = Result::okay(null);
      $okayClass = Result::okay(new SomeObject());

      $this->assertTrue($okayThing->isOkay());
      $this->assertTrue($okayNullable->isOkay());
      $this->assertTrue($okayClass->isOkay());

      $this->assertFalse($okayThing->isError());
      $this->assertFalse($okayNullable->isError());
      $this->assertFalse($okayClass->isError());

      $noname = Result::fromArray(['name' => 'value'], 'noname', 'oh no');
      $this->assertTrue($noname->isError());
      $this->assertFalse($noname->isOkay());

      $name = Result::fromArray(['name' => 'value'], 'name', 'oh no');
      $this->assertTrue($name->isOkay());
      $this->assertFalse($name->isError());

      $nonameResult = Result::fromArray(['name' => 'value'], 'noname');
      $nonameNull = Result::fromArray(['name' => 'value'], 'noname');
      $this->assertFalse($nonameResult->isOkay());
      $this->assertFalse($nonameNull->isOkay());

      $error = Result::okayNotNull(null, $errorValue);
      $okay = Result::okayNotNull('', $errorValue);

      $this->assertFalse($error->isOkay());
      $this->assertTrue($okay->isOkay());
   }

   public function testCreateAndCheckExistenceWhen() {
      $errorValue = "goodbye";

      $okayThing = Result::okayWhen(1, $errorValue, function($x) { return $x > 0; });
      $okayThing2 = Result::okayWhen(-1, $errorValue, function($x) { return $x > 0; });

      $this->assertSame($okayThing->dataOr(-5), 1);
      $this->assertSame($okayThing2->dataOr(-5), -5);

      $okayThing3 = Result::errorWhen(1, $errorValue, function($x) { return $x > 0; });
      $okayThing4 = Result::errorWhen(-1, $errorValue, function($x) { return $x > 0; });

      $this->assertSame($okayThing3->dataOr(-5), -5);
      $this->assertSame($okayThing4->dataOr(-5), -1);

      $errorThing = Result::errorWhen(1, 100, function($x) { return $x > 0; });
      $errorThing2 = Result::errorWhen(-1, 100, function($x) { return $x > 0; });
   }

   public function testGettingValue() {
      $errorValue = "goodbye";
      $errorResult = Result::error($errorValue);

      $this->assertSame($errorResult->dataOr(-1), -1);

      $someObject = new SomeObject();

      $okayThing = Result::okay(1);
      $okayClass = Result::okay($someObject);

      $this->assertSame($okayThing->dataOr(-1), 1);
      $this->assertSame($okayClass->dataOr(-1), $someObject);
   }

   public function testGettingValueLazily() {
      $errorValue = "goodbye";
      $errorResult = Result::error($errorValue);

      $this->assertSame($errorResult->dataOrReturn(function($x) { return $x; }), $errorValue);

      $someObject = new SomeObject();

      $okayThing = Result::okay(1);
      $okayClass = Result::okay($someObject);

      $this->assertSame($okayThing->dataOrReturn(function($x) { return $x; }), 1);
      $this->assertSame($okayClass->dataOrReturn(function($x) { return $x; }), $someObject);

      $this->assertSame($okayThing->dataOrReturn(function($x) {
         $this->fail('Callback should not have been run!');
         return $x;
      }), 1);

      $this->assertSame($okayClass->dataOrReturn(function($x) {
         $this->fail('Callback should not have been run!');
         return $x;
      }), $someObject);
   }

   public function testGettingAlternitiveValue() {
      $errorValue = "goodbye";
      $someObject = new SomeObject();
      $errorResult = Result::error($errorValue);

      $this->assertFalse($errorResult->isOkay());

      $okayThing = $errorResult->orSetDataTo(1);
      $okayClass = $errorResult->orSetDataTo($someObject);

      $this->assertTrue($okayThing->isOkay());
      $this->assertTrue($okayClass->isOkay());

      $this->assertSame($okayThing->dataOr(-1), 1);
      $this->assertSame($okayClass->dataOr("-1"), $someObject);

      $lazyokay = $errorResult->orCreateResultWithData(function() { return 10; });
      $this->assertTrue($lazyokay->isOkay());
      $this->assertSame($lazyokay->dataOr(-1), 10);

      $lazyPassThrough = $okayThing->orCreateResultWithData(function() { return 10; });
      $this->assertTrue($lazyPassThrough->isOkay());
      $this->assertSame($lazyPassThrough->dataOr(-1), 1);
   }

   public function testGettingAlternitiveResult() {
      $errorValue = "goodbye";
      $someObject = new SomeObject();
      $errorResult = Result::error($errorValue);

      $this->assertFalse($errorResult->isOkay());

      $errorResult2 = $errorResult->okayOr(Result::error($errorValue));
      $this->assertFalse($errorResult2->isOkay());

      $okayThing = $errorResult->okayOr(Result::okay(1));
      $okayClass = $errorResult->okayOr(Result::okay($someObject));

      $this->assertTrue($okayThing->isOkay());
      $this->assertTrue($okayClass->isOkay());

      $this->assertSame($okayThing->dataOr(-1), 1);
      $this->assertSame($okayClass->dataOr("-1"), $someObject);
   }

   public function testGettingAlternitiveResultLazy() {
      $errorValue = "goodbye";
      $someObject = new SomeObject();
      $errorResult = Result::error($errorValue);

      $this->assertFalse($errorResult->isOkay());

      $errorResult2 = $errorResult->createIfError(function($x) {
         return Result::error($x);
      });
      $this->assertFalse($errorResult2->isOkay());

      $okayThing = $errorResult->createIfError(function($x) {
         return Result::okay(1);
      });

      $okayClass = $errorResult->createIfError(function($x) use ($someObject) {
         return Result::okay($someObject);
      });

      $this->assertTrue($okayThing->isOkay());
      $this->assertTrue($okayClass->isOkay());

      $this->assertSame($okayThing->dataOr(-1), 1);
      $this->assertSame($okayClass->dataOr("-1"), $someObject);

      $okayThing->createIfError(function($x) {
         $this->fail('Callback should not have been run!');
         return Result::error($x);
      });
      $okayClass->createIfError(function($x) {
         $this->fail('Callback should not have been run!');
         return Result::error($x);
      });
   }

   public function testMatching() {
      $errorValue = "goodbye";
      $error = Result::error($errorValue);
      $okay = Result::okay(1);

      $failure = $error->run(
            function($x) { return 2; },
            function($x) { return $x; }
      );

      $success = $okay->run(
            function($x) { return 2; },
            function($x) { return $x; }
      );

      $this->assertSame($failure, $errorValue);
      $this->assertSame($success, 2);

      $hasMatched = false;
      $error->run(
            function($x) { $this->fail('Callback should not have been run!'); },
            function($x) use (&$hasMatched) { $hasMatched = true; }
      );
      $this->assertTrue($hasMatched);

      $hasMatched = false;
      $okay->run(
            function($x) use (&$hasMatched) { return $hasMatched = $x == 1; },
            function($x) use (&$hasMatched) { $this->fail('Callback should not have been run!'); }
      );
      $this->assertTrue($hasMatched);

      $error->runOnOkay(function($x) { $this->fail('Callback should not have been run!'); });

      $hasMatched = false;
      $okay->runOnOkay(function($x) use (&$hasMatched) { return $hasMatched = $x == 1; });
      $this->assertTrue($hasMatched);

      $okay->runOnError(function() { $this->fail('Callback should not have been run!'); });
      $hasMatched = false;

      $error->runOnError(function() use (&$hasMatched) { $hasMatched = true; });
      $this->assertTrue($hasMatched);
   }

   public function testMapping() {
      $errorValue = "goodbye";
      $error = Result::error($errorValue);
      $okay = Result::okay("a");
      $okayNull = Result::okay(null);

      $errorUpper = $error->map(function($x) { return strtoupper($x); });
      $okayUpper = $okay->map(function($x) { return strtoupper($x); });

      $this->assertFalse($errorUpper->isOkay());
      $this->assertTrue($okayUpper->isOkay());
      $this->assertSame($errorUpper->dataOr("b"), "b");
      $this->assertSame($okayUpper->dataOr("b"), "A");

      $error = Result::error("a");
      $okay = Result::okay("a");
      $okayNull = Result::okay(null);

      $errorNotNull = $error->flatmap(function($x) use ($errorValue) { return Result::okay($x)->notNull($errorValue); });
      $notNull = $okay->flatmap(function($x) use ($errorValue) { return Result::okay($x)->notNull($errorValue); });
      $okayNullNotNull = $okayNull->flatmap(function($x) use ($errorValue) { return Result::okay($x)->notNull($errorValue); });

      $this->assertFalse($errorNotNull->isOkay());
      $this->assertTrue($notNull->isOkay());
      $this->assertFalse($okayNullNotNull->isOkay());

      $errorUpper = $error->mapError(function($x) { return strtoupper($x); });
      $okayUpper = $okay->mapError(function($x) { return strtoupper($x); });

      $this->assertFalse($errorUpper->isOkay());
      $this->assertTrue($okayUpper->isOkay());
      $this->assertSame($errorUpper->errorOr("b"), "A");
      $this->assertSame($okayUpper->errorOr("b"), "b");
   }

   public function testFiltering() {
      $errorValue = "goodbye";
      $error = Result::error($errorValue);
      $okay = Result::okay("a");

      $okayTrue = $okay->toError($errorValue);
      $okayFalse = $okay->toError($errorValue);
      $errorTrue = $error->toError($errorValue);
      $errorFalse = $error->toError($errorValue);

      $this->assertFalse($okayFalse->isOkay());

      $this->assertFalse($errorTrue->isOkay());
      $this->assertFalse($errorFalse->isOkay());

      $errorNotA = $error->toErrorIf(function($x) { return $x != "a"; }, $errorValue);
      $okayNotA = $okay->toErrorIf(function($x) { return $x != "a"; }, $errorValue);
      $errorA = $error->toErrorIf(function($x) { return $x == "a"; }, $errorValue);
      $okayA = $okay->toErrorIf(function($x) { return $x == "a"; }, $errorValue);

      $this->assertFalse($errorNotA->isOkay());
      $this->assertFalse($okayNotA->isOkay());
      $this->assertFalse($errorA->isOkay());
      $this->assertTrue($okayA->isOkay());

      $okayNull = Result::okay(null);
      $this->assertTrue($okayNull->isOkay());
      $errorNull = $okayNull->notNull($errorValue);
      $this->assertFalse($errorNull->isOkay());

      $okayEmpty = Result::okay("");
      $this->assertTrue($okayEmpty->isOkay());
      $errorEmpty = $okayEmpty->notFalsy($errorValue);
      $this->assertFalse($errorEmpty->isOkay());
   }

   public function testContains() {
      $errorValue = "goodbye";
      $error = Result::error($errorValue);
      $okayString = Result::okay("a");
      $okayInt = Result::okay(1);

      $this->assertTrue($okayString->contains("a"));
      $this->assertFalse($okayString->contains("A"));
      $this->assertFalse($okayString->contains(1));
      $this->assertFalse($okayString->contains(null));

      $this->assertTrue($okayInt->contains(1));
      $this->assertFalse($okayInt->contains(2));
      $this->assertFalse($okayInt->contains("A"));
      $this->assertFalse($okayInt->contains(null));

      $this->assertFalse($error->contains(1));
      $this->assertFalse($error->contains(2));
      $this->assertFalse($error->contains("A"));
      $this->assertFalse($error->contains(null));

      $errorString = Result::error("a");
      $errorInt = Result::error(1);

      $this->assertTrue($errorString->errorContains("a"));
      $this->assertFalse($errorString->errorContains("A"));
      $this->assertFalse($errorString->errorContains(1));
      $this->assertFalse($errorString->errorContains(null));

      $this->assertTrue($errorInt->errorContains(1));
      $this->assertFalse($error->errorContains(2));
      $this->assertFalse($error->errorContains("A"));
      $this->assertFalse($error->errorContains(null));
   }

   public function testExists() {
      $errorValue = "goodbye";
      $error = Result::error($errorValue);
      $okay = Result::okay(10);

      $errorFalse = $error->exists(function($x) { return $x == 10; });
      $okayTrue = $okay->exists(function($x) { return $x >= 10; });
      $okayFalse = $okay->exists(function($x) { return $x == "Thing"; });

      $this->assertTrue($okayTrue);
      $this->assertFalse($errorFalse);
      $this->assertFalse($okayFalse);
   }

   public function testFlatMap() {
      $okayPerson = [
         'name' => [
            'first' => 'First',
            'last' => 'Last'
         ]
      ];

      $person = Result::fromArray($okayPerson, 'name', 'name was missing');

      $name = $person->andThen(function($person) {
         $fullName = $person['first'] . $person['last'];

         try {
            $thing = SomeComplexThing::doWork($fullName);
         } catch (ErrorException $e) {
            return Result::error('SomeComplexThing had an error!');
         }

         return Result::okay($thing);
      });

      $this->assertSame($name->dataOr(''), 'FirstLast');
   }

   public function testFlatMapWithException() {
      $okayPerson = [
         'name' => [
            'first' => 'First',
            'last' => 'Last'
         ]
      ];

      $person = Result::fromArray($okayPerson, 'name', 'name was missing');

      $name = $person->andThen(function($person) {
         $fullName = $person['first'] . $person['last'];

         try {
            $thing = SomeComplexThing::doWork($fullName, "Forcing exception");
         } catch (\Exception $e) {
            return Result::error('SomeComplexThing had an error!');
         }

         return Result::okay($thing);
      });

      $this->assertFalse($name->isOkay());
      $this->assertSame($name->dataOr('oh no'), 'oh no');
   }

   public function testSafelyMapWithException() {
      $okayPerson = [
         'name' => [
            'first' => 'First',
            'last' => 'Last'
         ]
      ];

      $person = Result::fromArray($okayPerson, 'name', 'name was missing');

      $name = $person->mapSafely(function($person): string {
         $fullName = $person['first'] . $person['last'];
         return SomeComplexThing::doWork($fullName, "Forcing exception");
      });

      $this->assertFalse($name->isOkay());
      $this->assertSame($name->dataOr('oh no'), 'oh no');

      $out = 'This should change';

      $name->run(
         function ($okayValue) { $this->fail('Callback should not have been run!'); },
         function ($exception) use(&$out) { $out = $exception->getMessage(); }
      );

      $this->assertSame($out, "Forcing exception");
   }
}