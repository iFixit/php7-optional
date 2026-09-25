<?php

declare(strict_types = 1);

namespace Optional\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Runs the scripts in examples/ and checks they exit clean and print what
 * PRINTS records.
 *
 * README points readers at this directory, so these are documentation too.
 * They are run in a subprocess, since that is the only way to see the
 * warnings and deprecations a script raises while its file is being
 * compiled.
 */
final class ExampleScriptTest extends TestCase {
   /**
    * What each script prints. The before and after scripts do the same work,
    * with and without the box, so they print the same line.
    */
   private const PRINTS = [
      'optionExampleAfter.php' => "The user is First Last\n",
      'optionExampleBefore.php' => "The user is First Last\n",
      'optionExampleComplex.php' => "Will connect to Sample App with: Server=10.0.0.120;Port=3128;Uid=root;Pwd=toor;\n"
         . "Cache: no cache configured\n",
   ];

   /**
    * @return iterable<string, array{string}>
    */
   public static function exampleProvider(): iterable {
      $dir = dirname(__DIR__) . '/examples';
      $paths = glob("{$dir}/*.php");

      foreach ($paths === false ? [] : $paths as $path) {
         yield basename($path) => [$path];
      }
   }

   #[DataProvider('exampleProvider')]
   public function testExampleRunsClean(string $path): void {
      $command = sprintf(
         '%s -d display_errors=1 -d error_reporting=E_ALL %s 2>&1',
         escapeshellarg(PHP_BINARY),
         escapeshellarg($path)
      );

      $output = [];
      $status = 0;
      exec($command, $output, $status);

      /** @var list<string> $output exec() fills this with one line per entry */
      $text = implode("\n", $output);

      $this->assertSame(0, $status, sprintf("%s exited %d:\n%s", basename($path), $status, $text));
      $this->assertArrayHasKey(basename($path), self::PRINTS, 'every script needs its output recorded above');
      $this->assertSame(rtrim(self::PRINTS[basename($path)], "\n"), $text, basename($path) . ' printed something else');

      foreach (['Fatal error', 'Warning:', 'Notice:', 'Deprecated:'] as $bad) {
         $this->assertStringNotContainsString(
            $bad,
            $text,
            sprintf("%s raised a %s:\n%s", basename($path), trim($bad, ':'), $text)
         );
      }
   }

   public function testThereAreExamplesToRun(): void {
      $this->assertNotEmpty(
         iterator_to_array(self::exampleProvider()),
         'examples/ is empty or unreadable, so this suite is passing on nothing'
      );
   }
}
