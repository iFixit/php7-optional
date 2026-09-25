<?php

declare(strict_types = 1);

namespace Optional\Tests;

use Optional\Exceptions\MissingValueException;
use Optional\Option;
use Optional\Result;
use PHPUnit\Framework\TestCase;

final class OptionTest extends TestCase {
   public function testCreateAndCheckExistence(): void {
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

   public function testTheBoxIsAReadonlyClass(): void {
      $this->assertTrue((new \ReflectionClass(Option::class))->isReadOnly(), 'PHP enforces the immutability only while the class is readonly');
   }

   public function testValueOrThrowWithNoFactoryReturnsTheValue(): void {
      $this->assertSame(1, Option::some(1)->valueOrThrow());
   }

   public function testValueOrThrowWithNoFactoryThrowsMissingValueException(): void {
      $this->expectException(MissingValueException::class);
      $this->expectExceptionMessage("Option is None");

      Option::none()->valueOrThrow();
   }

   public function testGettingValue(): void {
      $noneOption = Option::none();

      $this->assertSame(-1, $noneOption->valueOr(-1));

      $someObject = new SomeObject();

      $someThing = Option::some(1);
      $someClass = Option::some($someObject);

      $this->assertSame(1, $someThing->valueOr(-1));
      $this->assertSame($someObject, $someClass->valueOr(-1));
   }

   public function testGettingValueLazily(): void {
      $noneOption = Option::none();

      $this->assertSame(-1, $noneOption->valueOrCreate(function() { return -1; }));

      $someObject = new SomeObject();

      $someThing = Option::some(1);
      $someClass = Option::some($someObject);

      $this->assertSame(1, $someThing->valueOrCreate(function() { return -1; }));
      $this->assertSame($someObject, $someClass->valueOrCreate(function() { return -1; }));

      $this->assertSame($someThing->valueOrCreate(function() {
         $this->fail('Callback should not have been run!');
      }), 1);

      $this->assertSame($someClass->valueOrCreate(function() {
         $this->fail('Callback should not have been run!');
      }), $someObject);
   }

   public function testGettingAnAlternativeValue(): void {
      $someObject = new SomeObject();
      $noneOption = Option::none();

      $this->assertFalse($noneOption->hasValue());

      $someThing= $noneOption->or(1);
      $someClass = $noneOption->or($someObject);

      $this->assertSame($someClass, $someClass->or("Hello"));

      $this->assertTrue($someThing->hasValue());
      $this->assertTrue($someClass->hasValue());

      $this->assertSame(1, $someThing->valueOr(-1));
      $this->assertSame($someObject, $someClass->valueOr("-1"));

      $lazySome = $noneOption->orCreate(function() { return 10; });
      $this->assertTrue($lazySome->hasValue());
      $this->assertSame(10, $lazySome->valueOr(-1));

      $lazyPassThrough = $someThing->orCreate(function() { return 10; });
      $this->assertTrue($lazyPassThrough->hasValue());
      $this->assertSame(1, $lazyPassThrough->valueOr(-1));
   }

   public function testGettingAnAlternativeOption(): void {
      $someObject = new SomeObject();
      $noneOption = Option::none();

      $this->assertFalse($noneOption->hasValue());

      $noneOption2 = $noneOption->else(Option::none());
      $this->assertFalse($noneOption2->hasValue());

      $someThing = $noneOption->else(Option::some(1));
      $someClass = $noneOption->else(Option::some($someObject));

      $this->assertSame($someThing, $someThing->else(Option::some(1)));

      $this->assertTrue($someThing->hasValue());
      $this->assertTrue($someClass->hasValue());

      $this->assertSame(1, $someThing->valueOr(-1));
      $this->assertSame($someObject, $someClass->valueOr("-1"));
   }

   public function testGettingAnAlternativeOptionLazily(): void {
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

      $this->assertSame(1, $someThing->valueOr(-1));
      $this->assertSame($someObject, $someClass->valueOr("-1"));

      $kept = $someThing->elseCreate(
         /** @return never */
         function() {
            $this->fail('Callback should not have been run!');
         }
      );
      $this->assertSame($someThing, $kept, 'elseCreate hands back the Some it was called on');

      $kept = $someClass->elseCreate(
         /** @return never */
         function() {
            $this->fail('Callback should not have been run!');
         }
      );
      $this->assertSame($someClass, $kept, 'elseCreate hands back the Some it was called on');
   }

   public function testMatching(): void {
      $none = Option::none();
      $some = Option::some(1);

      $failure = $none->match(
          function($_x) { return 2; },
          function() { return -2; }
      );

      $success = $some->match(
          function($_x) { return 2; },
          function() { return -2; }
      );

      $this->assertSame(-2, $failure);
      $this->assertSame(2, $success);

      $whichRan = $none->match(
          function() { return 'some'; },
          function() { return 'none'; }
      );
      $this->assertSame('none', $whichRan, 'match on a None runs the none branch');

      $whichRan = $some->match(
          function() { return 'some'; },
          function() { return 'none'; }
      );
      $this->assertSame('some', $whichRan, 'match on a Some runs the some branch');
   }

   public function testMapping(): void {
      $none = Option::none();
      $some = Option::some("a");
      $someNull = Option::some(null);

      $noneUpper = $none->map(function() { return 'UNREACHABLE'; });
      $someUpper = $some->map(function(string $x) { return strtoupper($x); });

      $this->assertFalse($noneUpper->hasValue());
      $this->assertTrue($someUpper->hasValue());
      $this->assertSame("b", $noneUpper->valueOr("b"));
      $this->assertSame("A", $someUpper->valueOr("b"));

      $noneNotNull = $none->flatMap(function($x) { return Option::some($x)->notNull(); });
      $someNotNull = $some->flatMap(function($x) { return Option::some($x)->notNull(); });
      $someNullNotNull = $someNull->flatMap(function($x) { return Option::some($x)->notNull(); });

      $this->assertFalse($noneNotNull->hasValue());
      $this->assertTrue($someNotNull->hasValue());
      $this->assertFalse($someNullNotNull->hasValue());
   }

   public function testFiltering(): void {
      $none = Option::none();
      $some = Option::some("a");

      $someTrue = $some->filter(true);
      $someFalse = $some->filter(false);
      $noneTrue = $none->filter(true);
      $noneFalse = $none->filter(false);

      $this->assertTrue($someTrue->hasValue());
      $this->assertFalse($someFalse->hasValue());

      $this->assertFalse($noneTrue->hasValue());
      $this->assertFalse($noneFalse->hasValue());

      $someNull = Option::some(null);
      $this->assertTrue($someNull->hasValue());
      $noneNull = $someNull->notNull();
      $this->assertFalse($noneNull->hasValue());

      $someEmpty = Option::some("");
      $this->assertTrue($someEmpty->hasValue());
      $noneEmpty = $someEmpty->notFalsy();
      $this->assertFalse($noneEmpty->hasValue());
   }

   public function testContains(): void {
      $none = Option::none();
      $someString = Option::some("a");
      $someInt = Option::some(1);

      $this->assertTrue($someString->contains("a"));
      $this->assertFalse($someString->contains("A"));
      $this->assertFalse($someString->contains(1));
      $this->assertFalse($someString->contains(null));

      $this->assertTrue($someInt->contains(1));
      $this->assertFalse($someInt->contains(2));
      $this->assertFalse($someInt->contains("A"));
      $this->assertFalse($someInt->contains(null));

      $this->assertFalse($none->contains(1));
      $this->assertFalse($none->contains(2));
      $this->assertFalse($none->contains("A"));
      $this->assertFalse($none->contains(null));
   }

   /**
    * contains() compares with ===, as Result::contains() does. Under the ==
    * of 3.x, each of these was true.
    */
   public function testContainsComparesStrictly(): void {
      $this->assertFalse(Option::some(1)->contains('1'));
      $this->assertFalse(Option::some('1e1')->contains('10'));
      $this->assertFalse(Option::some(null)->contains(false));
      $this->assertFalse(Option::some(new \stdClass())->contains(new \stdClass()));

      $object = new \stdClass();
      $this->assertTrue(Option::some($object)->contains($object), 'the same instance still matches');
   }

   public function testExists(): void {
      $none = Option::none();
      $some = Option::some(10);

      $noneFalse = $none->exists(function($x) { return $x == 10; });
      $someTrue = $some->exists(function($x) { return $x >= 10; });
      $someFalse = $some->exists(function($x) { return $x == "Thing"; });

      $this->assertTrue($someTrue);
      $this->assertFalse($noneFalse);
      $this->assertFalse($someFalse);
   }

   /**
    * exists() reads its callback's result the way if does, like filter() and
    * Result::exists(). It used to return the result as is, so a callback that
    * returned 1 threw a TypeError from the bool return type.
    *
    * @psalm-suppress InvalidArgument these callbacks break callable(T):bool on purpose
    */
   public function testExistsReadsTheCallbackResultTheWayIfDoes(): void {
      $this->assertTrue(Option::some(5)->exists(fn() => 1));
      $this->assertFalse(Option::some(5)->exists(fn() => '0'));
      $this->assertFalse(Option::some(5)->exists(fn() => null));
   }

   /**
    * A callback that takes its argument by reference, like end() or reset(),
    * gets a copy of the value. Handing it the readonly property itself threw
    * "Cannot indirectly modify readonly property"; 3.0 gave Some(3).
    */
   public function testCallbacksMayTakeTheirArgumentByReference(): void {
      $box = Option::some([1, 2, 3]);
      $last = function(array &$values): int {
         return (int) end($values);
      };

      $this->assertSame(3, $box->map(end(...))->valueOr(0));
      $this->assertSame(3, $box->flatMap(fn(array $v) => Option::some($last($v)))->valueOr(0));
      $this->assertSame(3, $box->match(some: $last, none: fn() => 0));
      $this->assertTrue($box->filter(function(array &$values): bool { return end($values) === 3; })->hasValue());
      $this->assertTrue($box->exists(function(array &$values): bool { return end($values) === 3; }));
      $_ = $box->tap(function(array &$values): void { end($values); });

      $this->assertSame([1, 2, 3], $box->valueOr([]), 'the callbacks changed a copy, never the box');

      $sorted = Option::first([[3, 1, 2]], function(array &$values): bool { return sort($values); });
      $this->assertSame([3, 1, 2], $sorted->valueOr([]), 'first() boxes the value, not what its predicate did to it');
   }

   /**
    * NAN is truthy, and PHP 8.5 warns when it is turned into a bool, which
    * this suite fails on
    */
   public function testNanIsTruthyWithoutAWarning(): void {
      $this->assertSame('Some(NAN)', (string) Option::someNotFalsy(NAN));
      $this->assertSame('Some(NAN)', (string) Option::some(NAN)->notFalsy());
      $this->assertSame('None', (string) Option::someNotFalsy(0.0));
   }

   /**
    * filter() takes a Closure, not any callable, so a string is never called.
    * Code without strict types, which eval'd code is, coerces one to true, as
    * 3.x did. A callable parameter would have called var_dump() here.
    */
   public function testFilterNeverCallsAString(): void {
      ob_start();
      /** @var mixed $kept */
      $kept = eval("return \\Optional\\Option::some('held')->filter('var_dump');");
      $printed = (string) ob_get_clean();

      $this->assertSame('', $printed, 'var_dump() must not run');
      $this->assertInstanceOf(Option::class, $kept);
      $this->assertSame("Some('held')", (string) $kept);
   }

   /**
    * @psalm-suppress InvalidArgument a string where a Closure belongs is the point
    */
   public function testFilterRejectsAStringInStrictCode(): void {
      $this->expectException(\TypeError::class);

      $_ = Option::some(5)->filter(self::text('is_numeric'));
   }

   /**
    * Each callback runs only on the side it is for, which the docblocks promise
    * and which makes an expensive or throwing callback safe to pass. They count
    * rather than call fail(), since mapSafely() would catch what fail() throws.
    */
   public function testNoCallbackRunsOnTheSideItIsNotFor(): void {
      $ran = 0;
      $value = function() use (&$ran): int {
         $ran++;

         return 1;
      };
      $box = function() use (&$ran): Option {
         $ran++;

         return Option::none();
      };
      $bool = function() use (&$ran): bool {
         $ran++;

         return true;
      };
      $throwable = function() use (&$ran): \LogicException {
         $ran++;

         return new \LogicException('never thrown');
      };
      $none = self::nothing();
      $some = Option::some(1);

      $_ = $none->map($value);
      $_ = $none->mapSafely($value);
      $_ = $none->flatMap($box);
      $_ = $none->filter($bool);
      $_ = $none->exists($bool);
      $_ = $none->tap($value);
      $_ = $none->match(some: $value, none: fn(): int => 0);
      $_ = $some->tapNone($value);
      $_ = $some->valueOrCreate($value);
      $_ = $some->orCreate($value);
      $_ = $some->elseCreate($box);
      $_ = $some->valueOrThrow($throwable);
      $_ = $some->match(some: fn(): int => 0, none: $value);

      $this->assertSame(0, $ran);
   }

   /**
    * The library calls a callback from its own file, which has strict types,
    * so a callback typed int that is given '7' throws even when the calling
    * file has no strict types. The README says so.
    */
   public function testCallbacksRunUnderTheLibrarysStrictTypes(): void {
      $this->expectException(\TypeError::class);

      $_ = eval('return \\Optional\\Option::some("7")->map(fn(int $id) => $id);');
   }

   public function testFirstTakesAnyIterable(): void {
      $key = new \stdClass();
      /** @var \WeakMap<\stdClass, int> $ages */
      $ages = new \WeakMap();
      $ages[$key] = 41;

      $this->assertSame(41, Option::first($ages)->valueOr(0), 'a WeakMap has object keys, which first() ignores');
   }

   public function testFlatMapNeverRunsItsCallbackOnANone(): void {
      $ran = false;

      $result = Option::none()->flatMap(function() use (&$ran): Option {
         $ran = true;

         return Option::some(1);
      });

      $this->assertFalse($ran, 'flatMap must not run its callback on a None');
      $this->assertFalse($result->hasValue());
   }

   public function testAllKeepsTheKeysOrStopsAtTheFirstNone(): void {
      $this->assertSame(['a' => 1, 'b' => null], Option::all(['a' => Option::some(1), 'b' => Option::some(null)])->valueOr([]));
      $this->assertSame([], Option::all([])->valueOr(['not', 'empty']));

      $pulled = 0;
      $options = (function() use (&$pulled): \Generator {
         foreach ([Option::some(1), Option::none(), Option::some(3)] as $option) {
            $pulled++;
            yield $option;
         }
      })();

      $this->assertFalse(Option::all($options)->hasValue());
      $this->assertSame(2, $pulled, 'all() stops reading at the first None');
   }

   public function testAllKeepsTheLastValueOfAKeyThatRepeats(): void {
      $options = (function(): \Generator {
         yield 'a' => Option::some(1);
         yield 'a' => Option::some(2);
      })();

      $this->assertSame(['a' => 2], Option::all($options)->valueOr([]), 'as iterator_to_array() would');
   }

   /**
    * Without the check, a number gave a warning and a None, and a null key is
    * deprecated on PHP 8.5.
    *
    * @psalm-suppress InvalidArgument these items are wrong on purpose
    */
   public function testAllRejectsWhatItCannotCollect(): void {
      $cases = [
         'Option::all() takes only Optional\\Option items, got int' => [Option::some(1), 2],
         'Option::all() takes only Optional\\Option items, got Optional\\Result' => [Result::ok(1)],
         'Option::all() needs int or string keys, got null' => (fn() => yield null => Option::some(1))(),
         'Option::all() needs int or string keys, got stdClass' => (fn() => yield new \stdClass() => Option::some(1))(),
      ];

      foreach ($cases as $message => $items) {
         try {
            $_ = Option::all($items);
            $this->fail("all() took the items it should reject with: {$message}");
         } catch (\TypeError $e) {
            $this->assertSame($message, $e->getMessage());
         }
      }
   }

   public function testFlatMap(): void {
      $somePerson = [
         'name' => [
            'first' => 'First',
            'last' => 'Last'
         ]
      ];

      /** @var Option<array{first: string, last: string}> */
      $person = Option::fromArray($somePerson, 'name');

      $name = $person->flatMap(
         /**
          * @param array{first: string, last: string} $person
          * @return Option<string>
          */
         function(array $person) {
            $fullName = $person['first'] . $person['last'];

            try {
               $thing = SomeComplexThing::doWork($fullName);
            } catch (\ErrorException $_e) {
               return Option::none();
            }

            return Option::some($thing);
         });

      $this->assertSame('FirstLast', $name->valueOr(''));
   }

   /**
    * 3.x interpolated the value, so an array printed a warning and
    * "Some(Array)", an object with no __toString threw an Error, and false
    * printed "Some()".
    */
   public function testToStringShowsAnyValueWithoutWarning(): void {
      $this->assertSame('Some(10)', (string) Option::some(10));
      $this->assertSame('None', (string) Option::none());
      $this->assertSame('Some(null)', (string) Option::some(null));
      $this->assertSame('Some(false)', (string) Option::some(false));
      $this->assertSame('Some(array)', (string) Option::some([1, 2]), 'an array is shown by its type');
      $this->assertSame('Some(stdClass)', (string) Option::some(new \stdClass()), 'an object with no string form is shown by its class');
      $this->assertSame("Some(RuntimeException(boom))", (string) Option::some(new \RuntimeException('boom')), 'an exception by its class and message, not its trace');
   }

   /**
    * Two different scalars, strings or objects with a string form never print
    * the same way. Before strings were quoted, Some('null') printed as
    * Some(null), and Some('1'), Some(1) and Some(1.0) all printed as Some(1).
    * Before a Stringable showed its class, Some(new SplFileInfo('5')) printed
    * as Some(5).
    */
   public function testToStringTellsDifferentValuesApart(): void {
      $shown = [
         'null' => Option::some(null),
         "'null'" => Option::some('null'),
         '1' => Option::some(1),
         "'1'" => Option::some('1'),
         '1.0' => Option::some(1.0),
         '0.30000000000000004' => Option::some(0.1 + 0.2),
         "''" => Option::some(''),
         'false' => Option::some(false),
         "'it\\'s'" => Option::some("it's"),
         "'two\\nlines'" => Option::some("two\nlines"),
         "'a\\177b'" => Option::some("a\x7fb"),
         "'caf\\351'" => Option::some("caf\xe9"),
         "'café'" => Option::some('café'),
         "'a\\342\\200\\256b'" => Option::some("a\u{202E}b"),
         "'a\\342\\200\\250b'" => Option::some("a\u{2028}b"),
         "'\\302\\233[31m'" => Option::some("\u{9b}[31m"),
         'SplFileInfo(5)' => Option::some(new \SplFileInfo('5')),
         'SplFileInfo(null)' => Option::some(new \SplFileInfo('null')),
         'RuntimeException(two\\nlines)' => Option::some(new \RuntimeException("two\nlines")),
         "Some('a')" => Option::some(Option::some('a')),
         'NAN' => Option::some(NAN),
         'INF' => Option::some(INF),
         'Optional\\Tests\\OptionTestSuit::Hearts' => Option::some(OptionTestSuit::Hearts),
         'Optional\\Tests\\OptionTestCode::NotFound' => Option::some(OptionTestCode::NotFound),
      ];

      foreach ($shown as $expected => $box) {
         $this->assertSame("Some({$expected})", (string) $box);
      }
   }

   /**
    * Each of the twelve characters Unicode lists as a bidirectional control can
    * make a log line display differently from what it holds, so each is
    * escaped. Some("\u{200F}1 2\u{200F}") used to display as Some('2 1').
    */
   public function testToStringEscapesEveryBidirectionalControl(): void {
      $controls = [
         "\u{061C}", "\u{200E}", "\u{200F}",
         "\u{202A}", "\u{202B}", "\u{202C}", "\u{202D}", "\u{202E}",
         "\u{2066}", "\u{2067}", "\u{2068}", "\u{2069}",
      ];

      foreach ($controls as $control) {
         $this->assertSame(
            "Some('1" . addcslashes($control, "\200..\377") . "2')",
            (string) Option::some("1{$control}2"),
            'the UTF-8 bytes ' . bin2hex($control)
         );
      }
   }

   public function testToStringShowsAStringableByItsStringForm(): void {
      $id = new class implements \Stringable {
         #[\Override]
         public function __toString(): string {
            return 'order-42';
         }
      };

      $this->assertSame("Some(Stringable@anonymous(order-42))", (string) Option::some($id));
   }

   /**
    * A value whose __toString() prints another box, such as a linked list, used
    * to have that box's quotes escaped again at every level, doubling the size
    * each time: 30 levels ran out of memory. The text an object gives itself is
    * kept as it is now, so the size grows with the depth, not faster.
    */
   public function testToStringOfNestedValuesStaysSmall(): void {
      $list = Option::none();

      for ($i = 30; $i >= 1; $i--) {
         $list = Option::some(new OptionTestNode($i, $list));
      }

      $this->assertLessThan(2000, strlen((string) $list));
      $this->assertStringStartsWith('Some(Optional\\Tests\\OptionTestNode(Node(1, next: Some(', (string) $list);
   }

   /**
    * @psalm-suppress InvalidStringClass a class name that is not UTF-8 can only
    *    be declared, and so named, from a string
    */
   public function testToStringEscapesAClassNameThatIsNotUtf8(): void {
      $class = "OptionTestCaf\xe9";
      eval("final class {$class} {}");

      $shown = (string) Option::some(new $class());

      $this->assertSame('Some(OptionTestCaf\\351)', $shown);
      $this->assertNotFalse(json_encode($shown));
   }

   /**
    * getMessage() converts whatever a subclass stored in the untyped property,
    * which warns for an array and throws for an object. The message is read
    * as it was stored instead.
    *
    * @psalm-suppress InvalidPropertyAssignmentValue a malformed subclass is the point
    */
   public function testToStringNeverWarnsForAnExceptionWithAnOddMessage(): void {
      $this->assertSame('Some(RuntimeException@anonymous(stdClass))', (string) Option::some(new class() extends \RuntimeException {
         public function __construct() {
            parent::__construct();
            $this->message = new \stdClass();
         }
      }));
      $this->assertSame('Some(RuntimeException@anonymous(array))', (string) Option::some(new class() extends \RuntimeException {
         public function __construct() {
            parent::__construct();
            $this->message = ['a'];
         }
      }));
      $this->assertSame('Some(RuntimeException@anonymous())', (string) Option::some(new class() extends \RuntimeException {
         public function __construct() {
            parent::__construct();
            unset($this->message);
         }
      }));
      // a float prints the way describe() prints one, so 1.0 keeps its point
      $this->assertSame('Some(RuntimeException@anonymous(1.0))', (string) Option::some(new class() extends \RuntimeException {
         public function __construct() {
            parent::__construct();
            $this->message = 1.0;
         }
      }));
      // PHP 8.5 warns when NAN is turned into a string, which this suite fails on
      $this->assertSame('Some(RuntimeException@anonymous(NAN))', (string) Option::some(new class() extends \RuntimeException {
         public function __construct() {
            parent::__construct();
            $this->message = NAN;
         }
      }));
      // a hook on the property would otherwise run, and this one throws
      $this->assertSame('Some(RuntimeException@anonymous(kept))', (string) Option::some(new class('kept') extends \RuntimeException {
         public $message {
            get {
               throw new \LogicException('the hook ran');
            }
         }
      }));
   }

   /**
    * A box whose own string form throws, as a lazy box does when its
    * initializer fails, shows by its type, like any other value that does
    */
   public function testToStringNeverThrowsForABoxThatDoes(): void {
      $ghost = (new \ReflectionClass(Option::class))->newLazyGhost(function(): never {
         throw new \RuntimeException('db down');
      });

      $this->assertSame('Some(Optional\\Option)', (string) Option::some($ghost));
   }

   public function testToStringNeverThrowsForAValueThatDoes(): void {
      $throws = new class implements \Stringable {
         #[\Override]
         public function __toString(): string {
            throw new \LogicException('no string form');
         }
      };

      $this->assertSame('Some(Stringable@anonymous)', (string) Option::some($throws));
      $this->assertSame("Some(RuntimeException@anonymous(disk full))", (string) Option::some(new class('disk full') extends \RuntimeException {}));
   }

   /**
    * A string is always a message, even one that names a function: that is
    * why the factory has to be a Closure rather than any callable.
    */
   public function testValueOrThrowTakesAMessage(): void {
      $this->assertSame(1, Option::some(1)->valueOrThrow('never used'));

      $this->expectException(MissingValueException::class);
      $this->expectExceptionMessage('strtoupper');

      Option::none()->valueOrThrow(self::text('strtoupper'));
   }

   /**
    * A class name meant as the exception to throw used to become the message.
    * Only the empty path checks, so a value costs no autoload.
    */
   /**
    * A message that is exactly the name of an exception class gets a hint,
    * since it was almost certainly meant as the class to throw. It is still a
    * MissingValueException, so a catch for one still works.
    */
   public function testValueOrThrowHintsWhenTheMessageNamesAnExceptionClass(): void {
      $this->assertSame(1, Option::some(1)->valueOrThrow(\DomainException::class));

      $messages = [];
      $sent = [
         \DomainException::class,
         '\\DomainException',
         'App\\PaymentFailedException',
         'stdClass',
         'error',
         'DomainExceptionn',
         'lookup failed with App\\PaymentFailedException',
         "App\\PaymentFailedException\n",
         'League\\Csv\\Exception',
         'NotFoundError',
      ];

      foreach ($sent as $message) {
         try {
            self::nothing()->valueOrThrow($message);
         } catch (MissingValueException $e) {
            $messages[] = $e->getMessage();
         }
      }

      $this->assertSame([
         'DomainException (a class name: pass valueOrThrow() a closure to throw one)',
         '\\DomainException (a class name: pass valueOrThrow() a closure to throw one)',
         'App\\PaymentFailedException (a class name: pass valueOrThrow() a closure to throw one)',
         'stdClass',
         'error',
         'DomainExceptionn',
         'lookup failed with App\\PaymentFailedException',
         "App\\PaymentFailedException\n",
         'League\\Csv\\Exception (a class name: pass valueOrThrow() a closure to throw one)',
         'NotFoundError',
      ], $messages, 'only an exception class gets the hint: a loaded one spelled with its own case, or a namespaced one spelled like it');
   }

   /**
    * The hint only looks at classes PHP has already loaded, so a message is
    * never handed to an autoloader, which may require a file named after it.
    */
   public function testValueOrThrowNeverAutoloadsTheMessage(): void {
      $asked = [];
      $spy = function(string $class) use (&$asked): void {
         $asked[] = $class;
      };
      spl_autoload_register($spy);

      try {
         self::nothing()->valueOrThrow('OptionTestNeverDeclared');
      } catch (MissingValueException) {
         // what it throws is the other test's business
      } finally {
         spl_autoload_unregister($spy);
      }

      $this->assertSame([], $asked, 'a message is not a class to load');
   }

   /**
    * In a file without strict types, PHP would turn the exception object into
    * its own __toString() text, stack trace and all, and throw that as the
    * message of a MissingValueException, which a catch for the real class
    * misses. It is rejected even with a value, so the mistake shows up there.
    *
    * @psalm-suppress ImplicitToStringCast an exception object is the mistake under test
    */
   public function testValueOrThrowRejectsAnExceptionObject(): void {
      $this->expectException(\TypeError::class);
      $this->expectExceptionMessage(
         'valueOrThrow() takes a closure that builds the exception, such as fn() => new DomainException(...), not the exception itself'
      );

      $_ = Option::some(1)->valueOrThrow(new \DomainException('no id'));
   }

   /**
    * Its PHP type takes any object, so that PHPStan reads the docblock, and a
    * closure is the only object it keeps
    *
    * @psalm-suppress InvalidArgument any other object is the mistake under test
    */
   public function testValueOrThrowRejectsAnyOtherObject(): void {
      $this->expectException(\TypeError::class);
      $this->expectExceptionMessage('valueOrThrow() takes a message or a closure that builds the exception, got stdClass');

      $_ = Option::some(1)->valueOrThrow(new \stdClass());
   }

   public function testValueOrThrowUsesTheSuppliedException(): void {
      $some = Option::some(1);
      $this->assertSame(1, $some->valueOrThrow(function(): \Exception {
         throw new \LogicException('factory must not be called on a Some');
      }));

      $this->expectException(\RuntimeException::class);
      $this->expectExceptionMessage("no device id");

      Option::none()->valueOrThrow(function(): \Exception { return new \RuntimeException("no device id"); });
   }

   public function testTap(): void {
      $seen = null;
      $some = Option::some(5);
      $returned = $some->tap(function(int $x) use (&$seen): void { $seen = $x; });

      $this->assertSame(5, $seen, 'tap runs its callback on a Some');
      $this->assertSame($some, $returned, 'tap returns the original option so a chain continues');

      $ran = false;
      $none = Option::none();
      $returnedNone = $none->tap(function() use (&$ran): void { $ran = true; });

      $this->assertFalse($ran, 'tap must not run its callback on a None');
      $this->assertSame($none, $returnedNone);

      $doubled = Option::some(5)
         ->tap(function() use (&$ran): void { $ran = true; })
         ->map(function(int $x): int { return $x * 2; });

      $this->assertSame(10, $doubled->valueOr(-1), 'tap does not break the chain');
   }

   public function testTapNone(): void {
      $ran = false;
      $none = Option::none();
      $returned = $none->tapNone(function() use (&$ran): void { $ran = true; });

      $this->assertTrue($ran, 'tapNone runs its callback on a None');
      $this->assertSame($none, $returned, 'tapNone returns the original option so a chain continues');

      $ranOnSome = false;
      $some = Option::some(1);
      $this->assertSame($some, $some->tapNone(function() use (&$ranOnSome): void { $ranOnSome = true; }));
      $this->assertFalse($ranOnSome, 'tapNone must not run its callback on a Some');

      $recovered = Option::none()
         ->tapNone(function(): void {})
         ->orCreate(function(): string { return 'from source'; });

      $this->assertSame('from source', $recovered->valueOr('x'), 'tapNone does not break the chain');
   }

   /**
    * filter takes a bool or a callable now. The short, guessable name used to
    * be the eager one, so the cheapest thing to write was the one that costs.
    */
   public function testFilterTakesEitherSpelling(): void {
      $this->assertSame(5, Option::some(5)->filter(true)->valueOr(-1));
      $this->assertFalse(Option::some(5)->filter(false)->hasValue());

      $this->assertSame(5, Option::some(5)->filter(function(int $n): bool { return $n > 3; })->valueOr(-1));
      $this->assertFalse(Option::some(5)->filter(function(int $n): bool { return $n > 9; })->hasValue());

      $ran = false;
      $filtered = Option::none()->filter(function() use (&$ran): bool { $ran = true; return true; });
      $this->assertFalse($ran, 'the callable must not run on a None');
      $this->assertFalse($filtered->hasValue());

   }

   /**
    * A callback's result counts the way `if` counts it, as filterIf's did in
    * 3.x. A flag read back from a database as 1 or '1' has to keep the value,
    * not drop it because it is not the bool true.
    *
    * @psalm-suppress InvalidArgument these callbacks break callable(T):bool on purpose
    */
   public function testFilterReadsTheCallbackResultTheWayIfDoes(): void {
      $this->assertSame(5, Option::some(5)->filter(fn() => 1)->valueOr(-1));
      $this->assertSame(5, Option::some(5)->filter(fn() => '1')->valueOr(-1));
      $this->assertFalse(Option::some(5)->filter(fn() => '0')->hasValue());
      $this->assertFalse(Option::some(5)->filter(fn() => null)->hasValue());
   }

   public function testFirst(): void {
      $this->assertSame('a', Option::first(['a', 'b'])->valueOr('x'));
      $this->assertFalse(Option::first([])->hasValue());
      $this->assertSame('a', Option::first(['k' => 'a'])->valueOr('x'), 'keys are ignored');
      $this->assertTrue(Option::first([null])->hasValue(), 'a first value of null is still a Some');
   }

   /**
    * The point of taking an iterable rather than an array: a generator should
    * stop after the first value instead of being drained.
    */
   public function testFirstDoesNotDrainAGenerator(): void {
      $drained = false;

      $values = (function() use (&$drained) {
         yield 'first';
         $drained = true;
         yield 'second';
      })();

      $this->assertSame('first', Option::first($values)->valueOr('x'));
      $this->assertFalse($drained, 'first() pulled more than one value from the generator');
   }

   public function testFirstWithAPredicateTakesTheFirstMatch(): void {
      $this->assertSame('bb', Option::first(['a', 'bb', 'ccc'], fn(string $s) => strlen($s) > 1)->valueOr('x'));
      $this->assertFalse(Option::first(['a', 'b'], fn(string $s) => strlen($s) > 1)->hasValue(), 'no match is a None');
      $this->assertTrue(
         Option::first(['a', null], fn(?string $s) => $s === null)->hasValue(),
         'a match on null is Some(null), where array_find would return null for a miss too'
      );
   }

   public function testFirstWithAPredicateStopsAtTheMatch(): void {
      $checked = [];

      $values = (function() {
         yield 1;
         yield 2;
         yield 3;
      })();

      $even = function(int $n) use (&$checked): bool {
         $checked[] = $n;
         return $n % 2 === 0;
      };

      $this->assertSame(2, Option::first($values, $even)->valueOr(-1));
      $this->assertSame([1, 2], $checked, 'first() kept checking values after the first match');
   }

   public function testToNullable(): void {
      $this->assertSame('Ada', Option::some('Ada')->toNullable());
      $this->assertNull(Option::none()->toNullable());
      $this->assertNull(Option::some(null)->toNullable(), 'a stored null and an empty box look the same at a ?T boundary');
      $this->assertSame(
         Option::some('Ada')->valueOr(null),
         Option::some('Ada')->toNullable(),
         'toNullable is valueOr(null) with a name that says why'
      );
   }

   /**
    * someNotFalse exists for PHP's false-returning functions, where a match
    * at index 0 is falsy but found. someNotFalsy gets that case wrong.
    */
   public function testSomeNotFalse(): void {
      $this->assertSame(0, Option::someNotFalse(array_search('a', ['a', 'b']))->valueOr(-1), 'index 0 is found');
      $this->assertFalse(Option::someNotFalsy(array_search('a', ['a', 'b']))->hasValue(), 'the falsy check drops it');
      $this->assertFalse(Option::someNotFalse(false)->hasValue());
      $this->assertSame('', Option::someNotFalse('')->valueOr('x'), 'an empty string is not false');
      $this->assertNull(Option::someNotFalse(null)->valueOr('x'), 'null is not false either');
   }

   public function testNotFalse(): void {
      $this->assertFalse(Option::some(false)->notFalse()->hasValue());
      $this->assertSame(0, Option::some(0)->notFalse()->valueOr(-1));
      $this->assertFalse(Option::none()->notFalse()->hasValue());
   }

   public function testSomeNotFalsy(): void {
      $this->assertFalse(Option::someNotFalsy(0)->hasValue());
      $this->assertFalse(Option::someNotFalsy('')->hasValue());
      $this->assertFalse(Option::someNotFalsy([])->hasValue());
      $this->assertFalse(Option::someNotFalsy(null)->hasValue());
      $this->assertFalse(Option::someNotFalsy(false)->hasValue());
      $this->assertFalse(Option::someNotFalsy('0')->hasValue());

      $this->assertTrue(Option::someNotFalsy('0.0')->hasValue());
      $this->assertTrue(Option::someNotFalsy(-1)->hasValue());
      $this->assertSame([1], Option::someNotFalsy([1])->valueOr([]));
   }

   public function testFromCallable(): void {
      $some = Option::fromCallable(function(): int { return 7; });
      $this->assertSame(7, $some->valueOr(-1));

      $none = Option::fromCallable(function(): int { throw new \Exception("boom"); });
      $this->assertFalse($none->hasValue(), 'a thrown Exception becomes a None');

      $stillSome = Option::fromCallable(function() { return null; });
      $this->assertTrue($stillSome->hasValue(), 'a returned null is still a Some, use notNull to collapse it');
   }

   /**
    * mapSafely deliberately catches Exception and not Throwable. A mutation
    * sweep found nothing pinned that, so the two boxes could be made to agree
    * in exactly the way AGENTS.md says not to, with CI staying green.
    */
   public function testMapSafelyCatchesExceptionAndDropsIt(): void {
      $none = Option::some(1)->mapSafely(function(int $_x): int { throw new \Exception("boom"); });

      $this->assertFalse($none->hasValue(), 'a thrown Exception becomes a None');
   }

   public function testMapSafelyLetsErrorsThrough(): void {
      $this->expectException(\TypeError::class);

      $_ = Option::some(1)->mapSafely(function(int $_x): int { throw new \TypeError("not an exception"); });
   }

   /**
    * It catches Throwable and then checks the class, so a named Error is
    * caught. Narrowing that catch to Exception would drop it.
    */
   public function testMapSafelyCatchesAnErrorWhenNamed(): void {
      $none = Option::some(1)->mapSafely(function(int $_x): int { throw new \TypeError("not an exception"); }, \Error::class);

      $this->assertFalse($none->hasValue());
   }

   /**
    * A method on an empty box hands back that box rather than building a new
    * one. The README's cost numbers depend on it, and a revert would change
    * no other result.
    */
   public function testAnEmptyBoxComesBackAsItIs(): void {
      /** @var Option<int> $none typed as a caller would hold it, so psalm allows the callbacks */
      $none = Option::none();

      $this->assertSame($none, $none->map(fn(int $x): int => $x + 1));
      $this->assertSame($none, $none->mapSafely(fn(int $x): int => $x + 1));
      $this->assertSame($none, $none->flatMap(fn(int $x): Option => Option::some($x)));
      $this->assertSame($none, $none->filter(fn(int $x): bool => $x > 0));
      $this->assertSame($none, $none->notNull());
      $this->assertSame($none, $none->notFalsy());
      $this->assertSame($none, $none->notFalse());
   }

   public function testFromCallableLetsErrorsThrough(): void {
      $this->expectException(\TypeError::class);
      $_ = Option::fromCallable(function(): int { throw new \TypeError("not an exception"); });
   }

   public function testFromCallableCatchesTheNamedClassAndItsSubclasses(): void {
      $named = Option::fromCallable(function(): int { throw new \OutOfBoundsException('no user 5'); }, \OutOfBoundsException::class);
      $this->assertFalse($named->hasValue(), 'the named class becomes a None');

      $subclass = Option::fromCallable(function(): int { throw new \OutOfBoundsException('no user 5'); }, \RuntimeException::class);
      $this->assertFalse($subclass->hasValue(), 'a subclass of the named class is caught, as a catch clause would');
   }

   public function testFromCallableLetsAnyOtherClassThrough(): void {
      $this->expectException(\LogicException::class);
      $_ = Option::fromCallable(function(): int { throw new \LogicException('a bug'); }, \OutOfBoundsException::class);
   }

   public function testFromCallableCatchesAnErrorOnlyWhenNamed(): void {
      $none = Option::fromCallable(function(): int { throw new \TypeError('x'); }, \TypeError::class);
      $this->assertFalse($none->hasValue(), 'naming an Error class opts into catching it');
   }

   public function testMapSafelyCatchesTheNamedClass(): void {
      $none = Option::some(1)->mapSafely(function(int $_x): int { throw new \OutOfBoundsException('x'); }, \OutOfBoundsException::class);
      $this->assertFalse($none->hasValue(), 'the named class becomes a None');
   }

   public function testMapSafelyLetsAnyOtherClassThrough(): void {
      $this->expectException(\LogicException::class);
      $_ = Option::some(1)->mapSafely(function(int $_x): int { throw new \LogicException('a bug'); }, \OutOfBoundsException::class);
   }


   /**
    * A plain string to psalm. A literal that names a function, such as
    * 'is_numeric', reads as a callable, and not the same way on every run:
    * one run in three reported ArgumentTypeCoercion where the others reported
    * InvalidArgument.
    */
   private static function text(string $text): string {
      return $text;
   }

   /**
    * A None psalm cannot see is one, so a test can check what happens after
    *
    * @return Option<int>
    */
   private static function nothing(): Option {
      return Option::none();
   }
}

final class SomeObject {}
final class SomeComplexThing {
   public static function doWork(string $thing): string {
      return $thing;
   }
}

final class OptionTestNode implements \Stringable {
   /** @param Option<OptionTestNode> $next */
   public function __construct(private int $value, private Option $next) {
   }

   #[\Override]
   public function __toString(): string {
      return "Node({$this->value}, next: {$this->next})";
   }
}

enum OptionTestSuit {
   case Hearts;
}

enum OptionTestCode: int {
   case NotFound = 404;
}
