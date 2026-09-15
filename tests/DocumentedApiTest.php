<?php

declare(strict_types = 1);

namespace Optional\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Every public method must carry a worked example in its docblock.
 *
 * The docblocks are this library's only API documentation, and
 * DocblockExampleTest executes every example in them. Those two facts are only
 * worth something together: an undocumented method is a method nothing
 * executes and nothing shows you how to call.
 *
 * Adding a public method therefore means adding a ```php block that runs.
 */
final class DocumentedApiTest extends TestCase {
   /**
    * `__toString` is exempt. It is invoked by string interpolation rather than
    * called by name, and it appears in the examples of most other methods.
    */
   private const EXEMPT = ['__toString'];

   /**
    * @return iterable<string, array{string, string}>
    */
   public static function publicMethodProvider(): iterable {
      foreach ([\Optional\Option::class, \Optional\Either::class] as $class) {
         $reflection = new ReflectionClass($class);

         foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $class) {
               continue;
            }

            if (in_array($method->getName(), self::EXEMPT, true)) {
               continue;
            }

            $label = sprintf('%s::%s()', $reflection->getShortName(), $method->getName());
            yield $label => [$label, (string) $method->getDocComment()];
         }
      }
   }

   #[DataProvider('publicMethodProvider')]
   public function testPublicMethodHasAWorkedExample(string $label, string $docblock): void {
      $this->assertNotSame('', $docblock, "{$label} has no docblock at all");

      $this->assertStringContainsString(
         '```php',
         $docblock,
         "{$label} has no worked example. Every public method needs a ```php block, and "
         . "DocblockExampleTest will run it, so it has to be code that works."
      );
   }

   public function testProviderSeesTheWholeApi(): void {
      $labels = array_keys(iterator_to_array(self::publicMethodProvider()));

      $this->assertGreaterThan(70, count($labels), 'Reflection found suspiciously few public methods');
      $this->assertContains('Option::map()', $labels);
      $this->assertContains('Either::mapRight()', $labels);
   }
}
