<?php

declare(strict_types = 1);

namespace Optional\Tests;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\ParserFactory;
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

      $this->assertTrue(
         self::callsItOn($m[1], $class, $method, $isStatic),
         "{$label}'s example never calls it on a {$class}, so it is documenting some other method. "
         . 'A factory has to be called by its class name, and a method on a box that its chain, or '
         . 'the variable it was assigned to, starts from that class. A call in a comment or a string '
         . 'does not count.'
      );
   }

   /**
    * The check above passes whatever callsItOn() passes, so it gets cases of
    * its own. Each is an example's code, the class and method it documents,
    * whether that method is a factory, and whether the example calls it.
    *
    * @return iterable<string, array{string, string, string, bool, bool}>
    */
   public static function exampleCallProvider(): iterable {
      yield 'a chain from the class' => ['Option::some(1)->map(fn($x) => $x);', 'Option', 'map', false, true];
      yield 'a chain from the other class' => ['Result::ok(1)->map(fn($x) => $x);', 'Option', 'map', false, false];
      yield 'toResult() switches the class' => ["Option::some(1)->toResult('e')->mapErr(fn(\$e) => \$e);", 'Result', 'mapErr', false, true];
      yield 'toOption() switches it back' => ['Result::ok(1)->toOption()->isSome();', 'Option', 'isSome', false, true];
      yield 'a variable assigned a box' => ["\$box = Result::ok(1);\n\$box->isOk();", 'Result', 'isOk', false, true];
      yield 'a nullsafe call' => ["\$box = Option::some(1);\n\$box?->isSome();", 'Option', 'isSome', false, true];
      yield "a closure's typed parameter" => ['$check = fn(Result $box) => $box->isOk();', 'Result', 'isOk', false, true];
      yield 'a closure parameter is not the variable outside' => [
         "\$box = Option::some(1);\n\$check = fn(Result \$box) => \$box;\n\$box->isSome();",
         'Option',
         'isSome',
         false,
         true,
      ];
      yield 'the variable outside is not a closure parameter' => [
         "\$box = Option::some(1);\n\$check = fn(Result \$box) => \$box->isOk();",
         'Option',
         'isOk',
         false,
         false,
      ];
      yield 'a closure sees the variables around it' => ["\$box = Option::some(1);\n\$check = fn() => \$box->isSome();", 'Option', 'isSome', false, true];
      yield 'a named function does not' => ["\$box = Option::some(1);\nfunction check() { return \$box->isSome(); }", 'Option', 'isSome', false, false];
      yield 'a call in a comment' => ["// Option::some(1)->isSome()\nOption::none();", 'Option', 'isSome', false, false];
      yield 'a call in a string' => ["\$code = 'Option::some(1)->isSome()';", 'Option', 'isSome', false, false];
      yield 'a factory by its class name' => ['Result::ok(1);', 'Result', 'ok', true, true];
      yield "a factory of the other class's name" => ['Option::some(1);', 'Result', 'some', true, false];
      yield "a docblock's asterisks" => ["\$box = Option::some(1);\n * \$box->isSome();", 'Option', 'isSome', false, true];
   }

   #[DataProvider('exampleCallProvider')]
   public function testTheCallCheckTellsTheClassesApart(string $code, string $class, string $method, bool $isStatic, bool $calls): void {
      $this->assertSame($calls, self::callsItOn($code, $class, $method, $isStatic));
   }

   /**
    * Whether `$code` calls `$method` on a `$class`, found by parsing it
    *
    * A factory counts as `Class::method(`. A method counts when the box it is
    * called on comes from `$class`: a chain that starts at `Class::`, a
    * variable assigned one, or a closure parameter typed as one. `toResult()`
    * and `toOption()` switch a chain to the other class. A variable is looked
    * up in the function or closure it is used in, then in the ones around it,
    * so a closure's parameter does not stand in for a variable outside it.
    */
   private static function callsItOn(string $code, string $class, string $method, bool $isStatic): bool {
      $code = (string) preg_replace('/^\s*\*\s?/m', '', $code);
      $nodes = (new ParserFactory())->createForHostVersion()->parse("<?php\n{$code}") ?? [];
      (new NodeTraverser(new ParentConnectingVisitor()))->traverse($nodes);
      $finder = new NodeFinder();
      $origins = [];

      foreach ($finder->findInstanceOf($nodes, Expr\Assign::class) as $assign) {
         if ($assign->var instanceof Expr\Variable && is_string($assign->var->name)) {
            $origins[self::scopeId($assign)][$assign->var->name] = $assign->expr;
         }
      }

      foreach ($finder->findInstanceOf($nodes, Node\Param::class) as $param) {
         if ($param->var instanceof Expr\Variable && is_string($param->var->name) && $param->type instanceof Name) {
            $origins[self::scopeId($param)][$param->var->name] = $param->type->getLast();
         }
      }

      $calls = $isStatic
         ? $finder->findInstanceOf($nodes, Expr\StaticCall::class)
         : [
            ...$finder->findInstanceOf($nodes, Expr\MethodCall::class),
            ...$finder->findInstanceOf($nodes, Expr\NullsafeMethodCall::class),
         ];

      foreach ($calls as $call) {
         if (!$call->name instanceof Identifier || $call->name->name !== $method) {
            continue;
         }

         if (self::classOf($call instanceof Expr\StaticCall ? $call : $call->var, $origins) === $class) {
            return true;
         }
      }

      return false;
   }

   /**
    * The class of the box `$expr` holds, or null when the example does not say
    *
    * @param array<int, array<string, Expr|string>> $origins for each scope, what each variable was
    *    assigned, or its parameter type
    */
   private static function classOf(Expr|string $expr, array $origins, int $depth = 0): ?string {
      if (is_string($expr)) {
         return $expr;
      }

      if ($depth > 20) {
         return null;
      }

      if ($expr instanceof Expr\StaticCall) {
         return $expr->class instanceof Name ? $expr->class->getLast() : null;
      }

      if ($expr instanceof Expr\MethodCall || $expr instanceof Expr\NullsafeMethodCall) {
         $name = $expr->name instanceof Identifier ? $expr->name->name : '';

         return match ($name) {
            'toResult' => 'Result',
            'toOption' => 'Option',
            default => self::classOf($expr->var, $origins, $depth + 1),
         };
      }

      if ($expr instanceof Expr\Variable && is_string($expr->name)) {
         for ($scope = self::scopeOf($expr); ; $scope = self::scopeOf($scope)) {
            $found = $origins[$scope === null ? 0 : spl_object_id($scope)][$expr->name] ?? null;

            if ($found !== null) {
               return self::classOf($found, $origins, $depth + 1);
            }

            // a named function sees nothing outside it, and the top has nothing above it
            if ($scope === null || $scope instanceof Stmt\Function_) {
               return null;
            }
         }
      }

      return null;
   }

   /**
    * The function or closure `$node` is in, or null at the top of the example
    */
   private static function scopeOf(Node $node): ?FunctionLike {
      /** @var mixed $parent ParentConnectingVisitor set it, and an attribute can hold anything */
      $parent = $node->getAttribute('parent');

      while ($parent instanceof Node && !$parent instanceof FunctionLike) {
         /** @var mixed $parent */
         $parent = $parent->getAttribute('parent');
      }

      return $parent instanceof FunctionLike ? $parent : null;
   }

   private static function scopeId(Node $node): int {
      $scope = self::scopeOf($node);

      return $scope === null ? 0 : spl_object_id($scope);
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

   public function testProviderSeesTheWholeApi(): void {
      $labels = array_keys(iterator_to_array(self::publicMethodProvider()));

      $this->assertGreaterThan(40, count($labels), 'Reflection found suspiciously few public methods');
      $this->assertContains('Option::map()', $labels);
      $this->assertContains('Result::mapErr()', $labels);
   }
}
