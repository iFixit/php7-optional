<?php

declare(strict_types = 1);

namespace Optional\Tests;

use Optional\Option;
use Optional\Result;
use PHPUnit\Framework\TestCase;

/**
 * A box's serialized form is public API, whatever its visibility says.
 *
 * serialize() writes the class name and every private property name into the
 * payload, and boxes get cached that way. A lookup that found nothing is worth
 * caching as Option::none(), because a cache that answers a miss with null
 * cannot also store a null and tell the two apart. Rename a private property
 * and every box cached before the deploy breaks until it expires: the classes
 * are readonly, so reading one back throws "Cannot create dynamic property" on
 * every cache hit.
 *
 * The payloads below are pinned byte for byte. Option's have not changed since
 * tag 3.0 wrote them.
 */
final class SerializationTest extends TestCase {
   private const PAYLOADS = [
      'someInt' => "O:15:\"Optional\\Option\":2:{s:25:\"\0Optional\\Option\0hasValue\";b:1;s:22:\"\0Optional\\Option\0value\";i:5;}",
      'someArray' => "O:15:\"Optional\\Option\":2:{s:25:\"\0Optional\\Option\0hasValue\";b:1;s:22:\"\0Optional\\Option\0value\";a:2:{s:3:\"min\";d:0.5;s:3:\"max\";d:9;}}",
      'none' => "O:15:\"Optional\\Option\":2:{s:25:\"\0Optional\\Option\0hasValue\";b:0;s:22:\"\0Optional\\Option\0value\";N;}",
      'someNull' => "O:15:\"Optional\\Option\":2:{s:25:\"\0Optional\\Option\0hasValue\";b:1;s:22:\"\0Optional\\Option\0value\";N;}",
      'okInt' => "O:15:\"Optional\\Result\":3:{s:21:\"\0Optional\\Result\0isOk\";b:1;s:22:\"\0Optional\\Result\0value\";i:5;s:22:\"\0Optional\\Result\0error\";N;}",
      'errString' => "O:15:\"Optional\\Result\":3:{s:21:\"\0Optional\\Result\0isOk\";b:0;s:22:\"\0Optional\\Result\0value\";N;s:22:\"\0Optional\\Result\0error\";s:7:\"timeout\";}",
   ];

   public function testACachedSomeReadsBackAsTheSameSome(): void {
      $this->assertSame(5, self::readOption('someInt')->valueOr(-1));
      $this->assertSame(['min' => 0.5, 'max' => 9.0], self::readOption('someArray')->valueOr([]));
   }

   public function testACachedNoneReadsBackAsANone(): void {
      $this->assertFalse(self::readOption('none')->hasValue());
   }

   public function testACachedSomeNullIsStillASome(): void {
      $box = self::readOption('someNull');

      $this->assertTrue($box->hasValue(), 'a stored null is still a Some after the round trip');
      $this->assertNull($box->valueOr('x'));
   }

   public function testACachedResultReadsBackOnTheSameSide(): void {
      $this->assertSame(5, self::readResult('okInt')->valueOr(-1));
      $this->assertSame('timeout', self::readResult('errString')->errOr('x'));
   }

   /**
    * The other direction. What this version writes has to match the pinned
    * bytes, so a box cached before a deploy is still readable after it, and
    * after a rollback.
    */
   public function testThisVersionWritesThePinnedBytes(): void {
      $this->assertSame(self::PAYLOADS['someInt'], serialize(Option::some(5)));
      $this->assertSame(self::PAYLOADS['someArray'], serialize(Option::some(['min' => 0.5, 'max' => 9.0])));
      $this->assertSame(self::PAYLOADS['none'], serialize(Option::none()));
      $this->assertSame(self::PAYLOADS['someNull'], serialize(Option::some(null)));
      $this->assertSame(self::PAYLOADS['okInt'], serialize(Result::ok(5)));
      $this->assertSame(self::PAYLOADS['errString'], serialize(Result::err('timeout')));
   }

   /**
    * @param key-of<self::PAYLOADS> $name
    * @return Option<mixed>
    */
   private static function readOption(string $name): Option {
      $box = unserialize(self::PAYLOADS[$name], ['allowed_classes' => [Option::class]]);
      self::assertInstanceOf(Option::class, $box);

      return $box;
   }

   /**
    * @param key-of<self::PAYLOADS> $name
    * @return Result<mixed, mixed>
    */
   private static function readResult(string $name): Result {
      $box = unserialize(self::PAYLOADS[$name], ['allowed_classes' => [Result::class]]);
      self::assertInstanceOf(Result::class, $box);

      return $box;
   }
}
