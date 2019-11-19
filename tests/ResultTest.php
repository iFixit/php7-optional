<?php
declare(strict_types = 1);

require_once dirname(__FILE__) . '/../src/Result.php';

use Optional\Result;

class ResultTest extends PHPUnit\Framework\TestCase {
   public function testCreateAndCheckExistence() {
      $errorValue = new \Exception("Oh no!");
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

      $nonameNull = Result::fromArray(['name' => 'value'], 'missing', 'noname');
      $this->assertTrue($nonameNull->isError());

      $error = Result::okayNotNull(null, "Oh no!");
      $okay = Result::okayNotNull('', "Oh no!");

      $this->assertFalse($error->isOkay());
      $this->assertTrue($okay->isOkay());
   }

   public function testCreateAndCheckExistenceWhen() {
      $errorValue = "Oh no!";

      $okayThing = Result::okayWhen(1, $errorValue, function($x) { return $x > 0; });
      $okayThing2 = Result::okayWhen(-1, $errorValue, function($x) { return $x > 0; });

      $this->assertSame($okayThing->dataOrThrow(), 1);
      $this->assertTrue($okayThing2->isError());

      $okayThing3 = Result::errorWhen(1, $errorValue, function($x) { return $x > 0; });
      $okayThing4 = Result::errorWhen(-1, $errorValue, function($x) { return $x > 0; });

      $this->assertTrue($okayThing3->isError());
      $this->assertSame($okayThing4->dataOrThrow(), -1);
   }

   public function testGettingValue() {
      $errorValue = new \Exception("Oh no!");

      $someObject = new SomeObject();

      $okayThing = Result::okay(1);
      $okayClass = Result::okay($someObject);

      $this->assertSame($okayThing->dataOrThrow(), 1);
      $this->assertSame($okayClass->dataOrThrow(), $someObject);
   }

   public function testGettingAlternitiveValue() {
      $errorValue = new \Exception("Oh no!");
      $someObject = new SomeObject();
      $errorResult = Result::error($errorValue);

      $this->assertFalse($errorResult->isOkay());

      $okayThing = $errorResult->orSetDataTo(1);
      $okayClass = $errorResult->orSetDataTo($someObject);

      $this->assertTrue($okayThing->isOkay());
      $this->assertTrue($okayClass->isOkay());

      $this->assertSame($okayThing->dataOrThrow(), 1);
      $this->assertSame($okayClass->dataOrThrow(), $someObject);

      $lazyokay = $errorResult->orCreateResultWithData(function() { return 10; });
      $this->assertTrue($lazyokay->isOkay());
      $this->assertSame($lazyokay->dataOrThrow(), 10);

      $lazyPassThrough = $okayThing->orCreateResultWithData(function() { return 10; });
      $this->assertTrue($lazyPassThrough->isOkay());
      $this->assertSame($lazyPassThrough->dataOrThrow(), 1);
   }

   public function testGettingAlternitiveResult() {
      $errorValue = new \Exception("Oh no!");
      $someObject = new SomeObject();
      $errorResult = Result::error($errorValue);

      $this->assertFalse($errorResult->isOkay());

      $errorResult2 = $errorResult->okayOr(Result::error($errorValue));
      $this->assertFalse($errorResult2->isOkay());

      $okayThing = $errorResult->okayOr(Result::okay(1));
      $okayClass = $errorResult->okayOr(Result::okay($someObject));

      $this->assertTrue($okayThing->isOkay());
      $this->assertTrue($okayClass->isOkay());

      $this->assertSame($okayThing->dataOrThrow(), 1);
      $this->assertSame($okayClass->dataOrThrow(), $someObject);
   }

   public function testGettingAlternitiveResultLazy() {
      $errorValue = new \Exception("Oh no!");
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

      $this->assertSame($okayThing->dataOrThrow(), 1);
      $this->assertSame($okayClass->dataOrThrow(), $someObject);

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
      $errorValue = new \Exception("Oh no!");
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
      $errorValue = new \Exception("Oh no!");
      $error = Result::error($errorValue);
      $okay = Result::okay("a");
      $okayNull = Result::okay(null);

      $errorUpper = $error->map(function($x) { return strtoupper($x); });
      $okayUpper = $okay->map(function($x) { return strtoupper($x); });

      $this->assertFalse($errorUpper->isOkay());
      $this->assertTrue($okayUpper->isOkay());
      $this->assertSame($okayUpper->dataOrThrow(), "A");

      $error = Result::error(new \Exception("a"));
      $okay = Result::okay("a");
      $okayNull = Result::okay(null);

      $errorNotNull = $error->flatMap(function($x) use ($errorValue) { return Result::okay($x)->notNull("Oh no!"); });
      $notNull = $okay->flatMap(function($x) use ($errorValue) { return Result::okay($x)->notNull("Oh no!"); });
      $okayNullNotNull = $okayNull->flatMap(function($x) use ($errorValue) { return Result::okay($x)->notNull("Oh no!"); });

      $this->assertFalse($errorNotNull->isOkay());
      $this->assertTrue($notNull->isOkay());
      $this->assertFalse($okayNullNotNull->isOkay());

      $errorUpper = $error->mapError(function($x) { return new \Exception(strtoupper($x->getMessage())); });
      $okayUpper = $okay->mapError(function($x) { return strtoupper($x); });

      $this->assertFalse($errorUpper->isOkay());
      $this->assertTrue($okayUpper->isOkay());
   }

   public function testFiltering() {
      $errorValue = new \Exception("Oh no!");
      $error = Result::error($errorValue);
      $okay = Result::okay("a");

      $okayTrue = $error->toOkay("a");
      $this->assertTrue($okayTrue->isOkay());

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

      $nowOkay = $error->toOkayIf(function($e) { return $e == "a"; }, "Hello");
      $this->assertTrue($nowOkay->isOkay());

      $okayNull = Result::okay(null);
      $this->assertTrue($okayNull->isOkay());
      $errorNull = $okayNull->notNull("Oh no!");
      $this->assertFalse($errorNull->isOkay());

      $okayEmpty = Result::okay("");
      $this->assertTrue($okayEmpty->isOkay());
      $errorEmpty = $okayEmpty->notFalsy("Oh no!");
      $this->assertFalse($errorEmpty->isOkay());
   }

   public function testContains() {
      $errorValue = new \Exception("Oh no!");
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

      $this->assertTrue($error->errorContains($errorValue));
      $this->assertFalse($error->contains(1));
      $this->assertFalse($error->contains(2));
      $this->assertFalse($error->contains("A"));
      $this->assertFalse($error->contains(null));
   }

   public function testExists() {
      $errorValue = new \Exception("Oh no!");
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
         $thing = SomeComplexThing::doWork($fullName);
         return Result::okay($thing);
      });

      $this->assertSame($name->dataOrThrow(), 'FirstLast');
   }

   public function testFlatMapWithThrowable() {
      $okayPerson = [
         'name' => [
            'first' => 'First',
            'last' => 'Last'
         ]
      ];

      $person = Result::fromArray($okayPerson, 'name', 'name was missing');

      $name = $person->andThen(function($person) {
         $fullName = $person['first'] . $person['last'];
         $thing = SomeComplexThing::doWork($fullName, "Forcing Throwable");
         return Result::okay($thing);
      });

      $this->assertFalse($name->isOkay());

      try {
         $data = $name->dataOrThrow();
         $this->fail("Expected to throw Exception");
      } catch (\Throwable $e) {}
   }

   public function testSafelyMapWithThrowable() {
      $okayPerson = [
         'name' => [
            'first' => 'First',
            'last' => 'Last'
         ]
      ];

      $person = Result::fromArray($okayPerson, 'name', 'name was missing');

      $name = $person->map(function($person): string {
         $fullName = $person['first'] . $person['last'];
         return SomeComplexThing::doWork($fullName, "Forcing Throwable");
      });

      $this->assertFalse($name->isOkay());

      try {
         $data = $name->dataOrThrow();
         $this->fail("Expected to throw Exception");
      } catch (\Throwable $e) {}

      $out = 'This should change';

      $name->run(
         function ($okayValue) { $this->fail('Callback should not have been run!'); },
         function ($Throwable) use(&$out) { $out = $Throwable->getMessage(); }
      );

      $this->assertSame($out, "Forcing Throwable");
   }

   public function testThrowableToErrorState() {
      $lazyThrow = function() { throw new \Exception("Forced Throwable!"); };
      $okay = Result::okay("It's Okay!");

      $errorValue = "Oh no!";
      $error = Result::error(new \Exception($errorValue));

      $after = $error->orCreateResultWithData($lazyThrow);
      $this->assertTrue($after->isError());

      $after = $error->createIfError($lazyThrow);
      $this->assertTrue($after->isError());

      $after = $okay->map($lazyThrow);
      $this->assertTrue($after->isError());

      $after = $error->mapError($lazyThrow);
      $this->assertTrue($after->isError());

      $after = $okay->andThen($lazyThrow);
      $this->assertTrue($after->isError());

      $after = $okay->flatMap($lazyThrow);
      $this->assertTrue($after->isError());

      $after = $okay->toErrorIf($lazyThrow, new \Exception("Another Throwable"));
      $this->assertTrue($after->isError());

      $after = $error->toOkayIf($lazyThrow, new \Exception("Another Throwable"));
      $this->assertTrue($after->isError());

      $after = Result::okayWhen("Okay", $errorValue, $lazyThrow);
      $this->assertTrue($after->isError());

      $after = Result::errorWhen("Okay", $errorValue, $lazyThrow);
      $this->assertTrue($after->isError());

      try {
         $after = $okay->run($lazyThrow, $lazyThrow);
         $this->fail("Run will throw an Exception");
      } catch (\Throwable $e) {}

      try {
         $after = $error->dataOrReturn($lazyThrow);
         $this->fail("DataOrReturn will throw an Exception");
      } catch (\Throwable $e) {}

      try {
         $after = $okay->exists($lazyThrow);
         $this->fail("Exists will throw an Exception");
      } catch (\Throwable $e) {}

      try {
         $after = $okay->runOnOkay($lazyThrow);
         $this->fail("RunOnOkay will throw an Exception");
      } catch (\Throwable $e) {}

      try {
         $after = $okay->runOnError($lazyThrow);
         $this->fail("RunOnError will throw an Exception");
      } catch (\Throwable $e) {}
   }

   public function testToString() {
      $this->assertEquals("Okay(null)", (string)Result::okay(null));
      $this->assertEquals("Okay(10)", (string)Result::okay(10));

      $this->assertEquals("Error(Error!)", (string)Result::error(new \Exception("Error!")));
   }
}