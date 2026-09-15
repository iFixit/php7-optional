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

   /**
    * An example that never calls the method it documents is a copy of the
    * neighbour's. It runs, so DocblockExampleTest passes it, and it carries a
    * ```php block, so the check above passes it too. Either::leftNotFalsy
    * shipped for years with leftNotNull's example.
    */
   #[DataProvider('publicMethodProvider')]
   public function testExampleCallsTheMethodItDocuments(string $label, string $docblock): void {
      $method = substr($label, (int) strpos($label, '::') + 2, -2);

      if (preg_match('/```php\n(.*?)```/s', $docblock, $m) !== 1) {
         $this->markTestSkipped("{$label} has no example; the check above reports that");
      }

      $this->assertMatchesRegularExpression(
         '/(?:->|::)' . preg_quote($method, '/') . '\s*\(/',
         $m[1],
         "{$label}'s example never calls it, so it is documenting some other method"
      );
   }

   /**
    * README.md carries a hand-written list of every method. A list like that
    * rots the first time someone adds one, and the rot is invisible: nothing
    * breaks, the list is just quietly wrong.
    */
   #[DataProvider('publicMethodProvider')]
   public function testPublicMethodIsListedInTheReadme(string $label): void {
      $readme = (string) file_get_contents(dirname(__DIR__) . '/README.md');
      $method = substr($label, (int) strpos($label, '::') + 2, -2);

      $this->assertStringContainsString(
         "`{$method}`",
         $readme,
         "{$label} is missing from the method list in README.md"
      );
   }

   public function testProviderSeesTheWholeApi(): void {
      $labels = array_keys(iterator_to_array(self::publicMethodProvider()));

      $this->assertGreaterThan(70, count($labels), 'Reflection found suspiciously few public methods');
      $this->assertContains('Option::map()', $labels);
      $this->assertContains('Either::mapRight()', $labels);
   }
}
