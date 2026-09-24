<?php

declare(strict_types = 1);

namespace Optional\Tests;

use Optional\Either;
use Optional\Option;
use PHPUnit\Framework\TestCase;

/**
 * A box's serialized form is public API, whatever its visibility says.
 *
 * serialize() writes the class name and every private property name into the
 * payload, and boxes get cached that way. A lookup that found nothing is worth
 * caching as Option::none(), because a cache that answers a miss with null
 * cannot also store a null and tell the two apart. Rename a private property
 * and every box cached before the deploy comes back wrong until it expires,
 * and not loudly: a cached Some whose flag has no property left to land in
 * reads as a None, with only a deprecation notice to say so.
 *
 * The payloads below were written by tag 3.0, byte for byte.
 */
final class SerializationTest extends TestCase {
   private const WRITTEN_BY_3_0 = [
      'someInt' => "O:15:\"Optional\\Option\":2:{s:25:\"\0Optional\\Option\0hasValue\";b:1;s:22:\"\0Optional\\Option\0value\";i:5;}",
      'someArray' => "O:15:\"Optional\\Option\":2:{s:25:\"\0Optional\\Option\0hasValue\";b:1;s:22:\"\0Optional\\Option\0value\";a:2:{s:3:\"min\";d:0.5;s:3:\"max\";d:9;}}",
      'none' => "O:15:\"Optional\\Option\":2:{s:25:\"\0Optional\\Option\0hasValue\";b:0;s:22:\"\0Optional\\Option\0value\";N;}",
      'someNull' => "O:15:\"Optional\\Option\":2:{s:25:\"\0Optional\\Option\0hasValue\";b:1;s:22:\"\0Optional\\Option\0value\";N;}",
      'left' => "O:15:\"Optional\\Either\":3:{s:23:\"\0Optional\\Either\0isLeft\";b:1;s:26:\"\0Optional\\Either\0leftValue\";s:1:\"x\";s:27:\"\0Optional\\Either\0rightValue\";N;}",
      'right' => "O:15:\"Optional\\Either\":3:{s:23:\"\0Optional\\Either\0isLeft\";b:0;s:26:\"\0Optional\\Either\0leftValue\";N;s:27:\"\0Optional\\Either\0rightValue\";s:5:\"error\";}",
   ];

   public function testASomeWrittenBy30ReadsBackAsTheSameSome(): void {
      $this->assertSame(5, self::readOption('someInt')->valueOr(-1));
      $this->assertSame(['min' => 0.5, 'max' => 9.0], self::readOption('someArray')->valueOr([]));
   }

   public function testANoneWrittenBy30ReadsBackAsANone(): void {
      $this->assertFalse(self::readOption('none')->hasValue());
   }

   public function testASomeNullWrittenBy30IsStillASome(): void {
      $box = self::readOption('someNull');

      $this->assertTrue($box->hasValue(), 'a stored null is still a Some after the round trip');
      $this->assertNull($box->valueOr('x'));
   }

   public function testAnEitherWrittenBy30ReadsBackOnTheSameSide(): void {
      $this->assertSame('x', self::readEither('left')->leftOr('fallback'));
      $this->assertSame('error', self::readEither('right')->rightOr('fallback'));
   }

   /**
    * The other direction. What this version writes has to match what 3.0
    * wrote, so a box cached after an upgrade is still readable if the upgrade
    * is rolled back.
    */
   public function testThisVersionWritesWhat30Wrote(): void {
      $this->assertSame(self::WRITTEN_BY_3_0['someInt'], serialize(Option::some(5)));
      $this->assertSame(self::WRITTEN_BY_3_0['someArray'], serialize(Option::some(['min' => 0.5, 'max' => 9.0])));
      $this->assertSame(self::WRITTEN_BY_3_0['none'], serialize(Option::none()));
      $this->assertSame(self::WRITTEN_BY_3_0['someNull'], serialize(Option::some(null)));
      $this->assertSame(self::WRITTEN_BY_3_0['left'], serialize(Either::left('x')));
      $this->assertSame(self::WRITTEN_BY_3_0['right'], serialize(Either::right('error')));
   }

   /**
    * @param key-of<self::WRITTEN_BY_3_0> $name
    * @return Option<mixed>
    */
   private static function readOption(string $name): Option {
      $box = unserialize(self::WRITTEN_BY_3_0[$name], ['allowed_classes' => [Option::class]]);
      self::assertInstanceOf(Option::class, $box);

      return $box;
   }

   /**
    * @param key-of<self::WRITTEN_BY_3_0> $name
    * @return Either<mixed, mixed>
    */
   private static function readEither(string $name): Either {
      $box = unserialize(self::WRITTEN_BY_3_0[$name], ['allowed_classes' => [Either::class]]);
      self::assertInstanceOf(Either::class, $box);

      return $box;
   }
}
