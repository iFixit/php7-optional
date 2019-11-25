<?php
declare(strict_types = 1);

require_once dirname(__FILE__) . '/../src/SimpleResult.php';

use Optional\SimpleResult;

class SimpleResultTest extends PHPUnit\Framework\TestCase {
   public function testCreateAndCheckExistence() {
      $errorValue = new \Exception("Oh no!");
      $errorSimpleResult = SimpleResult::error($errorValue);

      $this->assertFalse($errorSimpleResult->isOkay());

      $okayThing = SimpleResult::okay(1);
      $okayNullable = SimpleResult::okay(null);
      $okayClass = SimpleResult::okay(new SomeObject());

      $this->assertTrue($okayThing->isOkay());
      $this->assertTrue($okayNullable->isOkay());
      $this->assertTrue($okayClass->isOkay());

      $this->assertFalse($okayThing->isError());
      $this->assertFalse($okayNullable->isError());
      $this->assertFalse($okayClass->isError());

      $noname = SimpleResult::fromArray(['name' => 'value'], 'noname', 'oh no');
      $this->assertTrue($noname->isError());
      $this->assertFalse($noname->isOkay());

      $name = SimpleResult::fromArray(['name' => 'value'], 'name', 'oh no');
      $this->assertTrue($name->isOkay());
      $this->assertFalse($name->isError());

      $nonameNull = SimpleResult::fromArray(['name' => 'value'], 'missing', 'noname');
      $this->assertTrue($nonameNull->isError());

      $error = SimpleResult::okayNotNull(null, "Oh no!");
      $okay = SimpleResult::okayNotNull('', "Oh no!");

      $this->assertFalse($error->isOkay());
      $this->assertTrue($okay->isOkay());
   }

   public function testCreateAndCheckExistenceWhen() {
      $errorValue = "Oh no!";

      $okayThing = SimpleResult::okayWhen(1, $errorValue, function($x) { return $x > 0; });
      $okayThing2 = SimpleResult::okayWhen(-1, $errorValue, function($x) { return $x > 0; });

      $this->assertSame($okayThing->dataOrThrow(), 1);
      $this->assertTrue($okayThing2->isError());

      $okayThing3 = SimpleResult::errorWhen(1, $errorValue, function($x) { return $x > 0; });
      $okayThing4 = SimpleResult::errorWhen(-1, $errorValue, function($x) { return $x > 0; });

      $this->assertTrue($okayThing3->isError());
      $this->assertSame($okayThing4->dataOrThrow(), -1);
   }

   public function testGettingValue() {
      $errorValue = new \Exception("Oh no!");

      $someObject = new SomeObject();

      $okayThing = SimpleResult::okay(1);
      $okayClass = SimpleResult::okay($someObject);

      $this->assertSame($okayThing->dataOrThrow(), 1);
      $this->assertSame($okayClass->dataOrThrow(), $someObject);
   }

   public function testGettingAlternitiveValue() {
      $errorValue = new \Exception("Oh no!");
      $someObject = new SomeObject();
      $errorSimpleResult = SimpleResult::error($errorValue);

      $this->assertFalse($errorSimpleResult->isOkay());

      $okayThing = $errorSimpleResult->orSetDataTo(1);
      $okayClass = $errorSimpleResult->orSetDataTo($someObject);

      $this->assertTrue($okayThing->isOkay());
      $this->assertTrue($okayClass->isOkay());

      $this->assertSame($okayThing->dataOrThrow(), 1);
      $this->assertSame($okayClass->dataOrThrow(), $someObject);

      $lazyokay = $errorSimpleResult->orCreateSimpleResultWithData(function() { return 10; });
      $this->assertTrue($lazyokay->isOkay());
      $this->assertSame($lazyokay->dataOrThrow(), 10);

      $lazyPassThrough = $okayThing->orCreateSimpleResultWithData(function() { return 10; });
      $this->assertTrue($lazyPassThrough->isOkay());
      $this->assertSame($lazyPassThrough->dataOrThrow(), 1);
   }

   public function testGettingAlternitiveSimpleResult() {
      $errorValue = new \Exception("Oh no!");
      $someObject = new SomeObject();
      $errorSimpleResult = SimpleResult::error($errorValue);

      $this->assertFalse($errorSimpleResult->isOkay());

      $errorSimpleResult2 = $errorSimpleResult->okayOr(SimpleResult::error($errorValue));
      $this->assertFalse($errorSimpleResult2->isOkay());

      $okayThing = $errorSimpleResult->okayOr(SimpleResult::okay(1));
      $okayClass = $errorSimpleResult->okayOr(SimpleResult::okay($someObject));

      $this->assertTrue($okayThing->isOkay());
      $this->assertTrue($okayClass->isOkay());

      $this->assertSame($okayThing->dataOrThrow(), 1);
      $this->assertSame($okayClass->dataOrThrow(), $someObject);
   }

   public function testGettingAlternitiveSimpleResultLazy() {
      $errorValue = new \Exception("Oh no!");
      $someObject = new SomeObject();
      $errorSimpleResult = SimpleResult::error($errorValue);

      $this->assertFalse($errorSimpleResult->isOkay());

      $errorSimpleResult2 = $errorSimpleResult->createIfError(function($x) {
         return SimpleResult::error($x);
      });
      $this->assertFalse($errorSimpleResult2->isOkay());

      $okayThing = $errorSimpleResult->createIfError(function($x) {
         return SimpleResult::okay(1);
      });

      $okayClass = $errorSimpleResult->createIfError(function($x) use ($someObject) {
         return SimpleResult::okay($someObject);
      });

      $this->assertTrue($okayThing->isOkay());
      $this->assertTrue($okayClass->isOkay());

      $this->assertSame($okayThing->dataOrThrow(), 1);
      $this->assertSame($okayClass->dataOrThrow(), $someObject);

      $okayThing->createIfError(function($x) {
         $this->fail('Callback should not have been run!');
         return SimpleResult::error($x);
      });
      $okayClass->createIfError(function($x) {
         $this->fail('Callback should not have been run!');
         return SimpleResult::error($x);
      });
   }

   public function testMatching() {
      $errorValue = new \Exception("Oh no!");
      $error = SimpleResult::error($errorValue);
      $okay = SimpleResult::okay(1);

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
      $error = SimpleResult::error($errorValue);
      $okay = SimpleResult::okay("a");
      $okayNull = SimpleResult::okay(null);

      $errorUpper = $error->map(function($x) { return strtoupper($x); });
      $okayUpper = $okay->map(function($x) { return strtoupper($x); });

      $this->assertFalse($errorUpper->isOkay());
      $this->assertTrue($okayUpper->isOkay());
      $this->assertSame($okayUpper->dataOrThrow(), "A");

      $error = SimpleResult::error(new \Exception("a"));
      $okay = SimpleResult::okay("a");
      $okayNull = SimpleResult::okay(null);

      $errorNotNull = $error->flatMap(function($x) use ($errorValue) { return SimpleResult::okay($x)->notNull("Oh no!"); });
      $notNull = $okay->flatMap(function($x) use ($errorValue) { return SimpleResult::okay($x)->notNull("Oh no!"); });
      $okayNullNotNull = $okayNull->flatMap(function($x) use ($errorValue) { return SimpleResult::okay($x)->notNull("Oh no!"); });

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
      $error = SimpleResult::error($errorValue);
      $okay = SimpleResult::okay("a");

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

      $okayNull = SimpleResult::okay(null);
      $this->assertTrue($okayNull->isOkay());
      $errorNull = $okayNull->notNull("Oh no!");
      $this->assertFalse($errorNull->isOkay());

      $okayEmpty = SimpleResult::okay("");
      $this->assertTrue($okayEmpty->isOkay());
      $errorEmpty = $okayEmpty->notFalsy("Oh no!");
      $this->assertFalse($errorEmpty->isOkay());
   }

   public function testContains() {
      $errorValue = new \Exception("Oh no!");
      $error = SimpleResult::error($errorValue);
      $okayString = SimpleResult::okay("a");
      $okayInt = SimpleResult::okay(1);

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
      $error = SimpleResult::error($errorValue);
      $okay = SimpleResult::okay(10);

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

      $person = SimpleResult::fromArray($okayPerson, 'name', 'name was missing');

      $name = $person->andThen(function($person) {
         $fullName = $person['first'] . $person['last'];
         $thing = SomeComplexThing::doWork($fullName);
         return SimpleResult::okay($thing);
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

      $person = SimpleResult::fromArray($okayPerson, 'name', 'name was missing');

      $name = $person->andThen(function($person) {
         $fullName = $person['first'] . $person['last'];
         $thing = SomeComplexThing::doWork($fullName, "Forcing Throwable");
         return SimpleResult::okay($thing);
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

      $person = SimpleResult::fromArray($okayPerson, 'name', 'name was missing');

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
      $okay = SimpleResult::okay("It's Okay!");

      $errorValue = "Oh no!";
      $error = SimpleResult::error(new \Exception($errorValue));

      $after = $error->orCreateSimpleResultWithData($lazyThrow);
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

      $after = SimpleResult::okayWhen("Okay", $errorValue, $lazyThrow);
      $this->assertTrue($after->isError());

      $after = SimpleResult::errorWhen("Okay", $errorValue, $lazyThrow);
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
      $this->assertEquals("Okay(null)", (string)SimpleResult::okay(null));
      $this->assertEquals("Okay(10)", (string)SimpleResult::okay(10));

      $this->assertEquals("Error(Error!)", (string)SimpleResult::error(new \Exception("Error!")));
   }
}