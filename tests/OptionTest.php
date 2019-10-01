<?php
declare(strict_types = 1);

require_once dirname(__FILE__) . '/../src/Option.php';

use Optional\Option;

class OptionTest extends PHPUnit\Framework\TestCase {

   public function testCreateAndCheckExistence() {
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

   public function testCreateAndCheckExistenceWhen() {
      $someThing = Option::someWhen(1, function($x) { return $x > 0; });
      $someThing2 = Option::someWhen(-1, function($x) { return $x > 0; });

      $this->assertSame($someThing->valueOr(-5), 1);
      $this->assertSame($someThing2->valueOr(-5), -5);

      $someThing3 = Option::noneWhen(1, function($x) { return $x > 0; });
      $someThing4 = Option::noneWhen(-1, function($x) { return $x > 0; });

      $this->assertSame($someThing3->valueOr(-5), -5);
      $this->assertSame($someThing4->valueOr(-5), -1);
   }

   public function testGettingValue() {
      $noneOption = Option::none();

      $this->assertSame($noneOption->valueOr(-1), -1);

      $someObject = new SomeObject();

      $someThing = Option::some(1);
      $someClass = Option::some($someObject);

      $this->assertSame($someThing->valueOr(-1), 1);
      $this->assertSame($someClass->valueOr(-1), $someObject);
   }

   public function testGettingValueLazily() {
      $noneOption = Option::none();

      $this->assertSame($noneOption->valueOrCreate(function() { return -1; }), -1);

      $someObject = new SomeObject();

      $someThing = Option::some(1);
      $someClass = Option::some($someObject);

      $this->assertSame($someThing->valueOrCreate(function() { return -1; }), 1);
      $this->assertSame($someClass->valueOrCreate(function() { return -1; }), $someObject);

      $this->assertSame($someThing->valueOrCreate(function() {
         $this->fail('Callback should not have been run!');
         return -1;
      }), 1);

      $this->assertSame($someClass->valueOrCreate(function() {
         $this->fail('Callback should not have been run!');
         return -1;
      }), $someObject);
   }

   public function testGettingAlternitiveValue() {
      $someObject = new SomeObject();
      $noneOption = Option::none();

      $this->assertFalse($noneOption->hasValue());

      $someThing= $noneOption->or(1);
      $someClass = $noneOption->or($someObject);

      $this->assertTrue($someThing->hasValue());
      $this->assertTrue($someClass->hasValue());

      $this->assertSame($someThing->valueOr(-1), 1);
      $this->assertSame($someClass->valueOr("-1"), $someObject);
   }

   public function testGettingAlternitiveOption() {
      $someObject = new SomeObject();
      $noneOption = Option::none();

      $this->assertFalse($noneOption->hasValue());

      $noneOption2 = $noneOption->else(Option::none());
      $this->assertFalse($noneOption2->hasValue());

      $someThing = $noneOption->else(Option::some(1));
      $someClass = $noneOption->else(Option::some($someObject));

      $this->assertTrue($someThing->hasValue());
      $this->assertTrue($someClass->hasValue());

      $this->assertSame($someThing->valueOr(-1), 1);
      $this->assertSame($someClass->valueOr("-1"), $someObject);
   }

   public function testGettingAlternitiveOptionLazy() {
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

      $someThing->elseCreate(function() {
         $this->fail('Callback should not have been run!');
         return Option::none();
      });
      $someClass->elseCreate(function() {
         $this->fail('Callback should not have been run!');
         return Option::none();
      });
   }

   public function testMatching() {
      $none = Option::none();
      $some = Option::some(1);

      $failure = $none->match(
          function($x) { return 2; },
          function() { return -2; }
      );

      $success = $some->match(
          function($x) { return 2; },
          function() { return -2; }
      );

      $this->assertSame($failure, -2);
      $this->assertSame($success, 2);

      $hasMatched = false;
      $none->match(
          function($x) { $this->fail('Callback should not have been run!'); },
          function() use (&$hasMatched) { $hasMatched = true; }
      );
      $this->assertTrue($hasMatched);

      $hasMatched = false;
      $some->match(
          function($x) use (&$hasMatched) { return $hasMatched = $x == 1; },
          function() use (&$hasMatched) { $this->fail('Callback should not have been run!'); }
      );
      $this->assertTrue($hasMatched);

      $none->matchSome(function($x) { $this->fail('Callback should not have been run!'); });

      $hasMatched = false;
      $some->matchSome(function($x) use (&$hasMatched) { return $hasMatched = $x == 1; });
      $this->assertTrue($hasMatched);

      $some->matchNone(function() { $this->fail('Callback should not have been run!'); });
      $hasMatched = false;

      $none->matchNone(function() use (&$hasMatched) { $hasMatched = true; });
      $this->assertTrue($hasMatched);
   }

   public function testMapping() {
      $none = Option::none();
      $some = Option::some("a");
      $someNull = Option::some(null);

      $noneUpper = $none->map(function($x) { return strtoupper($x); });
      $someUpper = $some->map(function($x) { return strtoupper($x); });

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

   public function testFiltering() {
      $none = Option::none();
      $some = Option::some("a");

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

   public function testFlatMap() {
      $somePerson = [
         'name' => [
            'first' => 'First',
            'last' => 'Last'
         ]
      ];

      $person = Option::fromArray($somePerson, 'name');

      $name = $person->andThen(function($person) {
         $fullName = $person['first'] . $person['last'];

         try {
            $thing = SomeComplexThing::doWork($fullName);
         } catch (ErrorException $e) {
            return Option::none();
         }

         return Option::some($thing);
      });

      $this->assertSame($name->valueOr(''), 'FirstLast');
   }

   public function testFlatMapWithException() {
      $somePerson = [
         'name' => [
            'first' => 'First',
            'last' => 'Last'
         ]
      ];

      $person = Option::fromArray($somePerson, 'name');

      $name = $person->andThen(function($person) {
         $fullName = $person['first'] . $person['last'];

         try {
            $thing = SomeComplexThing::doWork($fullName, "Forcing some exception");
         } catch (\Exception $e) {
            return Option::none();
         }

         return Option::some($thing);
      });

      $this->assertFalse($name->hasValue());
      $this->assertSame($name->valueOr('oh no'), 'oh no');
   }

   public function testSafelyMapWithException() {
      $somePerson = [
         'name' => [
            'first' => 'First',
            'last' => 'Last'
         ]
      ];

      $person = Option::fromArray($somePerson, 'name');

      $name = $person->mapSafely(function($person): string {
         $fullName = $person['first'] . $person['last'];
         return SomeComplexThing::doWork($fullName, "Forcing some exception");
      });

      $this->assertFalse($name->hasValue());
      $this->assertSame($name->valueOr('oh no'), 'oh no');
   }
}

class SomeObject {};
class SomeComplexThing {
   public static function doWork(string $thing, string $ex = null): string {
      if($ex) {
         throw new \Exception($ex);
      }

      return $thing;
   }
};
