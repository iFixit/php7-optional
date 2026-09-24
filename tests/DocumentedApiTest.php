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
    * @return iterable<string, array{string, string, bool}>
    */
   public static function publicMethodProvider(): iterable {
      foreach (self::publicClasses() as $class) {
         $reflection = new ReflectionClass($class);

         foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $class) {
               continue;
            }

            if (in_array($method->getName(), self::EXEMPT, true)) {
               continue;
            }

            // An @internal method is how the library works, not what it offers
            if (str_contains((string) $method->getDocComment(), '@internal')) {
               continue;
            }

            $label = sprintf('%s::%s()', $reflection->getShortName(), $method->getName());
            yield $label => [$label, (string) $method->getDocComment(), $method->isStatic()];
         }
      }
   }

   #[DataProvider('publicMethodProvider')]
   public function testPublicMethodHasAWorkedExample(string $label, string $docblock): void {
      $this->assertNotSame('', $docblock, "{$label} has no docblock at all");

      $this->assertMatchesRegularExpression(
         '/```php\n.*?```/s',
         $docblock,
         "{$label} has no complete worked example. Every public method needs a closed ```php "
         . "block, and DocblockExampleTest will run it, so it has to be code that works. An "
         . "unclosed fence used to slip past this check and then be skipped by the next one."
      );
   }

   /**
    * An example that never calls the method it documents is a copy of the
    * neighbour's. It runs, so DocblockExampleTest passes it, and it carries a
    * ```php block, so the check above passes it too. A falsy check once shipped
    * for years with the null check's example.
    *
    * The call has to be on the right class, too: a factory by its class name,
    * and a method on a box the example builds from that class. Otherwise
    * `Option::some('Ada')->toNullable()` passes as the example for
    * `Result::toNullable()`.
    */
   #[DataProvider('publicMethodProvider')]
   public function testExampleCallsTheMethodItDocuments(string $label, string $docblock, bool $isStatic): void {
      [$class, $method] = explode('::', substr($label, 0, -2));

      $this->assertSame(
         1,
         preg_match('/```php\n(.*?)```/s', $docblock, $m),
         "{$label} has no complete example, which the check above reports. Not skipping, "
         . "because a skipped test fails nothing."
      );

      $code = self::stripCommentsAndStrings($m[1]);

      $this->assertMatchesRegularExpression(
         $isStatic ? '/\b' . $class . '::' . $method . '\s*\(/' : '/->' . $method . '\s*\(/',
         $code,
         "{$label}'s example never calls it, so it is documenting some other method. A call "
         . "inside a comment or a string literal does not count."
      );
      $this->assertMatchesRegularExpression(
         '/\b' . $class . '::/',
         $code,
         "{$label}'s example never builds a {$class}, so the {$method}() it calls belongs to another class."
      );
   }

   /**
    * README.md carries a hand-written list of every factory and method, one
    * pair of lists per class. A list like that rots the first time someone
    * adds a method, and the rot is invisible: nothing breaks, the list is just
    * wrong. Each list has to match reflection exactly, so a method left out
    * and a name that is not a method both fail.
    */
   public function testTheReadmeListsMatchTheClasses(): void {
      $expected = [];

      foreach (self::publicMethodProvider() as $label => [, , $isStatic]) {
         [$class, $method] = explode('::', substr($label, 0, -2));
         $expected["`{$class}` " . ($isStatic ? 'factories' : 'methods')][] = $method;
      }

      $listed = [];
      preg_match_all('/^(`\w+` (?:factories|methods)):(.*?)(?:\n\n|\z)/ms', self::readmeMethodList(), $lists, PREG_SET_ORDER);

      foreach ($lists as [, $heading, $names]) {
         preg_match_all('/`(\w+)`/', $names, $found);
         $listed[$heading] = $found[1];
      }

      ksort($expected);
      ksort($listed);
      $expected = array_map(fn(array $names): array => self::sorted($names), $expected);
      $listed = array_map(fn(array $names): array => self::sorted($names), $listed);

      $this->assertSame($expected, $listed, "README.md's 'Full method list' does not match the classes.");
   }

   /**
    * @param list<string> $names
    * @return list<string>
    */
   private static function sorted(array $names): array {
      sort($names);

      return $names;
   }

   /**
    * Every class under src/, so a new one cannot be added outside the gates.
    *
    * @return list<class-string>
    */
   private static function publicClasses(): array {
      $classes = [];
      $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__) . '/src'));

      /** @var \SplFileInfo $file */
      foreach ($files as $file) {
         if ($file->getExtension() !== 'php') {
            continue;
         }

         $source = (string) file_get_contents($file->getPathname());

         if (preg_match('/namespace\s+([^;]+);/', $source, $ns) !== 1) {
            continue;
         }

         // Anchored, because an unanchored match finds "class is" in a docblock.
         // Any run of modifiers, because `final readonly class` has two.
         if (preg_match('/^(?:(?:final|abstract|readonly)\s+)*(?:class|enum|interface|trait)\s+(\w+)/m', $source, $cls) !== 1) {
            continue;
         }

         $name = trim($ns[1]) . '\\' . $cls[1];
         self::assertTrue(
            class_exists($name) || interface_exists($name) || trait_exists($name),
            "{$file->getPathname()} declares {$name}, which the autoloader cannot find"
         );

         // An @internal class is how the library works, not what it offers
         if (!str_contains((string) (new \ReflectionClass($name))->getDocComment(), '@internal')) {
            $classes[] = $name;
         }
      }

      sort($classes);

      return $classes;
   }

   /**
    * The "Full method list" section only. A method named anywhere else in the
    * README, in prose or in an example, is not a listing.
    */
   private static function readmeMethodList(): string {
      $readme = (string) file_get_contents(dirname(__DIR__) . '/README.md');
      $start = strpos($readme, '## Full method list');

      if ($start === false) {
         return '';
      }

      $end = strpos($readme, "\n## ", $start + 1);

      return $end === false ? substr($readme, $start) : substr($readme, $start, $end - $start);
   }

   /**
    * Removes comments and string literals, so a method name mentioned in either
    * does not count as the example calling the method.
    */
   private static function stripCommentsAndStrings(string $code): string {
      $stripped = '';

      foreach (token_get_all('<?php ' . $code) as $token) {
         if (!is_array($token)) {
            $stripped .= $token;
            continue;
         }

         $stripped .= in_array($token[0], [T_COMMENT, T_DOC_COMMENT, T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true)
            ? ' '
            : $token[1];
      }

      return $stripped;
   }

   public function testProviderSeesTheWholeApi(): void {
      $labels = array_keys(iterator_to_array(self::publicMethodProvider()));

      $this->assertGreaterThan(40, count($labels), 'Reflection found suspiciously few public methods');
      $this->assertContains('Option::map()', $labels);
      $this->assertContains('Result::mapErr()', $labels);
   }
}
