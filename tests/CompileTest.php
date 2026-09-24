<?php

declare(strict_types = 1);

namespace Optional\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Compiles every PHP file in the repo in a subprocess, and fails on anything
 * PHP raises while compiling one.
 *
 * PHPUnit cannot see those: they fire while a file is being compiled, before
 * a test has a handler attached. An implicitly nullable parameter, deprecated
 * in 8.4, once sat in the suite unnoticed that way. ExampleScriptTest runs the
 * example scripts, which only compiles the files they load.
 */
final class CompileTest extends TestCase {
   public function testEveryFileCompilesWithoutAWarningOrDeprecation(): void {
      $root = dirname(__DIR__);
      $files = [];

      foreach (['src', 'static-analysis', 'tests', 'bin', 'examples'] as $dir) {
         /** @var \SplFileInfo $file */
         foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator("{$root}/{$dir}")) as $file) {
            if ($file->getExtension() === 'php') {
               $files[] = escapeshellarg($file->getPathname());
            }
         }
      }

      $this->assertGreaterThan(30, count($files), 'suspiciously few PHP files found');

      $output = [];
      $status = 0;
      exec(sprintf('%s -d display_errors=1 -d error_reporting=-1 -l %s 2>&1', escapeshellarg(PHP_BINARY), implode(' ', $files)), $output, $status);

      /** @var list<string> $output exec() fills this with one line per entry */
      $problems = array_values(array_filter(
         $output,
         fn(string $line): bool => preg_match('/Fatal error|Parse error|Warning:|Notice:|Deprecated:/', $line) === 1
      ));

      $this->assertSame([], $problems, 'PHP raised these while compiling');
      $this->assertSame(0, $status, implode("\n", $output));
   }
}
