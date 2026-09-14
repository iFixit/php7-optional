<?php

declare(strict_types = 1);

namespace Optional\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Runs the scripts in examples/ and checks they exit clean.
 *
 * README points readers at this directory, so these are documentation too.
 * They are run in a subprocess for two reasons: several of them declare a
 * class of the same name, so loading two in one process is a fatal, and a
 * subprocess is the only way to see the warnings and deprecations a script
 * raises while its file is being compiled.
 */
final class ExampleScriptTest extends TestCase {
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
      $text = implode("\n", $output);

      $this->assertSame(0, $status, sprintf("%s exited %d:\n%s", basename($path), $status, $text));
      $this->assertNotSame('', trim($text), basename($path) . ' printed nothing, so it demonstrates nothing');

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
