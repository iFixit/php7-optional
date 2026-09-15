<?php

declare(strict_types = 1);

namespace Optional\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests the coverage gate itself.
 *
 * A gate nobody tests is a gate that passes when it should not. The failure
 * this is most concerned with is the third case: phpunit not writing the
 * report at all, which would otherwise look exactly like success.
 */
final class CoverageGateTest extends TestCase {
   /**
    * @return iterable<string, array{string, int}>
    */
   public static function reportProvider(): iterable {
      $metrics = static fn(string $statements, string $covered): string =>
         "<coverage><project><metrics statements=\"{$statements}\" coveredstatements=\"{$covered}\"/></project></coverage>";

      yield 'above the floor' => [$metrics('100', '97'), 0];
      yield 'exactly the floor' => [$metrics('100', '90'), 0];
      yield 'a hair below the floor' => [$metrics('1000000', '899999'), 1];
      yield 'below the floor' => [$metrics('100', '72'), 1];
      yield 'nothing measured' => [$metrics('0', '0'), 2];
      yield 'negative statements' => [$metrics('-100', '-97'), 2];
      yield 'more covered than exist' => [$metrics('100', '150'), 2];
      yield 'no metrics element' => ['<coverage><project/></coverage>', 2];
      yield 'two project elements' => [
         '<coverage><project><metrics statements="1" coveredstatements="1"/></project>'
         . '<project><metrics statements="1000" coveredstatements="0"/></project></coverage>',
         2,
      ];
      yield 'not xml' => ['this is not a coverage report', 2];
   }

   /**
    * @return iterable<string, array{string}>
    */
   public static function badFloorProvider(): iterable {
      yield 'not a number' => ['ninety'];
      yield 'empty, which is what an unset shell variable expands to' => [''];
      yield 'nearly a number' => ['9o'];
      yield 'above 100' => ['101'];
      yield 'below zero' => ['-1'];
   }

   /**
    * A floor that does not parse used to become 0.0, which passes anything and
    * prints a success line while doing it.
    */
   #[DataProvider('badFloorProvider')]
   public function testUnusableFloorFails(string $floor): void {
      $path = (string) tempnam(sys_get_temp_dir(), 'clover-');
      file_put_contents($path, '<?xml version="1.0"?><coverage><project><metrics statements="100" coveredstatements="10"/></project></coverage>');

      try {
         $this->assertSame(2, self::runGate($path, $floor), "floor '{$floor}' must be rejected, not silently treated as zero");
      } finally {
         unlink($path);
      }
   }

   public function testDirectoryInsteadOfReportFails(): void {
      $this->assertSame(2, self::runGate(sys_get_temp_dir(), '90'));
   }

   #[DataProvider('reportProvider')]
   public function testGateExitCode(string $report, int $expected): void {
      $path = (string) tempnam(sys_get_temp_dir(), 'clover-');
      file_put_contents($path, '<?xml version="1.0"?>' . $report);

      try {
         $this->assertSame($expected, self::runGate($path, '90'), "report: {$report}");
      } finally {
         unlink($path);
      }
   }

   public function testMissingReportFails(): void {
      $this->assertSame(
         2,
         self::runGate(sys_get_temp_dir() . '/definitely-not-a-report-' . uniqid() . '.xml', '90'),
         'a report that was never written must fail, not pass quietly'
      );
   }

   private static function runGate(string $reportPath, string $floor): int {
      $command = sprintf(
         '%s %s %s %s 2>&1',
         escapeshellarg(PHP_BINARY),
         escapeshellarg(dirname(__DIR__) . '/bin/check-coverage.php'),
         escapeshellarg($reportPath),
         escapeshellarg($floor)
      );

      $output = [];
      $status = 0;
      exec($command, $output, $status);

      return $status;
   }
}
