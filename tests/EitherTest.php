<?php
declare(strict_types = 1);

require_once dirname(__FILE__) . '/../src/Either.php';

use Optional\Either;

class EitherTest extends PHPUnit\Framework\TestCase {

   public function testCreateAndCheckExistence() {
      $noneValue = "goodbye";
      $noneOption = Either::none($noneValue);

      $this->assertFalse($noneOption->hasValue());

      $someThing = Either::some(1, $noneValue);
      $someNullable = Either::some(null, $noneValue);
      $someClass = Either::some(new SomeObject(), $noneValue);

      $this->assertTrue($someThing->hasValue());
      $this->assertTrue($someNullable->hasValue());
      $this->assertTrue($someClass->hasValue());
   }

   public function testCreateAndCheckExistenceWhen() {
      $noneValue = "goodbye";

      $someThing = Either::someWhen(1, $noneValue, function($x) { return $x > 0; });
      $someThing2 = Either::someWhen(-1, $noneValue, function($x) { return $x > 0; });

      $this->assertSame($someThing->valueOr(-5), 1);
      $this->assertSame($someThing2->valueOr(-5), -5);

      $someThing3 = Either::noneWhen(1, $noneValue, function($x) { return $x > 0; });
      $someThing4 = Either::noneWhen(-1, $noneValue, function($x) { return $x > 0; });

      $this->assertSame($someThing3->valueOr(-5), -5);
      $this->assertSame($someThing4->valueOr(-5), -1);
   }

   public function testGettingValue() {
      $noneValue = "goodbye";
      $noneOption = Either::none($noneValue);

      $this->assertSame($noneOption->valueOr(-1), -1);

      $someObject = new SomeObject();

      $someThing = Either::some(1, $noneValue);
      $someClass = Either::some($someObject, $noneValue);

      $this->assertSame($someThing->valueOr(-1), 1);
      $this->assertSame($someClass->valueOr(-1), $someObject);
   }

   public function testGettingValueLazily() {
      $noneValue = "goodbye";
      $noneOption = Either::none($noneValue);

      $this->assertSame($noneOption->valueOrCreate(function($x) { return $x; }), $noneValue);

      $someObject = new SomeObject();

      $someThing = Either::some(1, $noneValue);
      $someClass = Either::some($someObject, $noneValue);

      $this->assertSame($someThing->valueOrCreate(function($x) { return $x; }), 1);
      $this->assertSame($someClass->valueOrCreate(function($x) { return $x; }), $someObject);

      $this->assertSame($someThing->valueOrCreate(function($x) {
         $this->fail('Callback should not have been run!');
         return $x;
      }), 1);

      $this->assertSame($someClass->valueOrCreate(function($x) {
         $this->fail('Callback should not have been run!');
         return $x;
      }), $someObject);
   }

   public function testGettingAlternitiveValue() {
      $noneValue = "goodbye";
      $someObject = new SomeObject();
      $noneOption = Either::none($noneValue);

      $this->assertFalse($noneOption->hasValue());

      $someThing= $noneOption->or(1);
      $someClass = $noneOption->or($someObject);

      $this->assertTrue($someThing->hasValue());
      $this->assertTrue($someClass->hasValue());

      $this->assertSame($someThing->valueOr(-1), 1);
      $this->assertSame($someClass->valueOr("-1"), $someObject);
   }

   public function testGettingAlternitiveOption() {
      $noneValue = "goodbye";
      $someObject = new SomeObject();
      $noneOption = Either::none($noneValue);

      $this->assertFalse($noneOption->hasValue());

      $noneOption2 = $noneOption->else(Either::none($noneValue));
      $this->assertFalse($noneOption2->hasValue());

      $someThing = $noneOption->else(Either::some(1, $noneValue));
      $someClass = $noneOption->else(Either::some($someObject, $noneValue));

      $this->assertTrue($someThing->hasValue());
      $this->assertTrue($someClass->hasValue());

      $this->assertSame($someThing->valueOr(-1), 1);
      $this->assertSame($someClass->valueOr("-1"), $someObject);
   }

   public function testGettingAlternitiveOptionLazy() {
      $noneValue = "goodbye";
      $someObject = new SomeObject();
      $noneOption = Either::none($noneValue);

      $this->assertFalse($noneOption->hasValue());

      $noneOption2 = $noneOption->elseCreate(function($x) {
         return Either::none($x);
      });
      $this->assertFalse($noneOption2->hasValue());

      $someThing = $noneOption->elseCreate(function($x) {
         return Either::some(1);
      });

      $someClass = $noneOption->elseCreate(function($x) use ($someObject) {
         return Either::some($someObject);
      });

      $this->assertTrue($someThing->hasValue());
      $this->assertTrue($someClass->hasValue());

      $this->assertSame($someThing->valueOr(-1), 1);
      $this->assertSame($someClass->valueOr("-1"), $someObject);

      $someThing->elseCreate(function($x) {
         $this->fail('Callback should not have been run!');
         return Either::none($x);
      });
      $someClass->elseCreate(function($x) {
         $this->fail('Callback should not have been run!');
         return Either::none($x);
      });
   }

   public function testMatching() {
      $noneValue = "goodbye";
      $none = Either::none($noneValue);
      $some = Either::some(1, $noneValue);

      $failure = $none->match(
          function($x) { return 2; },
          function($x) { return $x; }
      );

      $success = $some->match(
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
      $some->match(
          function($x) use (&$hasMatched) { return $hasMatched = $x == 1; },
          function($x) use (&$hasMatched) { $this->fail('Callback should not have been run!'); }
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
      $noneValue = "goodbye";
      $none = Either::none($noneValue);
      $some = Either::some("a", $noneValue);
      $someNull = Either::some(null);

      $noneUpper = $none->map(function($x) { return strtoupper($x); });
      $someUpper = $some->map(function($x) { return strtoupper($x); });

      $this->assertFalse($noneUpper->hasValue());
      $this->assertTrue($someUpper->hasValue());
      $this->assertSame($noneUpper->valueOr("b"), "b");
      $this->assertSame($someUpper->valueOr("b"), "A");

      $noneNotNull = $none->flatMap(function($x) use ($noneValue) { return Either::some($x)->notNull($noneValue); });
      $someNotNull = $some->flatMap(function($x) use ($noneValue) { return Either::some($x)->notNull($noneValue); });
      $someNullNotNull = $someNull->flatMap(function($x) use ($noneValue) { return Either::some($x)->notNull($noneValue); });

      $this->assertFalse($noneNotNull->hasValue());
      $this->assertTrue($someNotNull->hasValue());
      $this->assertFalse($someNullNotNull->hasValue());
   }

   public function testFiltering() {
      $noneValue = "goodbye";
      $none = Either::none($noneValue);
      $some = Either::some("a", $noneValue);

      $noneNotA = $none->filterIf(function($x) { return $x != "a"; }, $noneValue);
      $someNotA = $some->filterIf(function($x) { return $x != "a"; }, $noneValue);
      $noneA = $none->filterIf(function($x) { return $x == "a"; }, $noneValue);
      $someA = $some->filterIf(function($x) { return $x == "a"; }, $noneValue);

      $this->assertFalse($noneNotA->hasValue());
      $this->assertFalse($someNotA->hasValue());
      $this->assertFalse($noneA->hasValue());
      $this->assertTrue($someA->hasValue());
   }
}