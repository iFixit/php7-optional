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
      yield 'above the floor' => ['<coverage><project><metrics statements="100" coveredstatements="97"/></project></coverage>', 0];
      yield 'exactly the floor' => ['<coverage><project><metrics statements="100" coveredstatements="90"/></project></coverage>', 0];
      yield 'below the floor' => ['<coverage><project><metrics statements="100" coveredstatements="72"/></project></coverage>', 1];
      yield 'nothing measured' => ['<coverage><project><metrics statements="0" coveredstatements="0"/></project></coverage>', 2];
      yield 'no metrics element' => ['<coverage><project/></coverage>', 2];
      yield 'not xml' => ['this is not a coverage report', 2];
   }

   #[DataProvider('reportProvider')]
   public function testGateExitCode(string $report, int $expected): void {
      $path = (string) tempnam(sys_get_temp_dir(), 'clover-');
      file_put_contents($path, '<?xml version="1.0"?>' . $report);

      try {
         $this->assertSame($expected, self::runGate($path), "report: {$report}");
      } finally {
         unlink($path);
      }
   }

   public function testMissingReportFails(): void {
      $this->assertSame(
         2,
         self::runGate(sys_get_temp_dir() . '/definitely-not-a-report-' . uniqid() . '.xml'),
         'a report that was never written must fail, not pass quietly'
      );
   }

   private static function runGate(string $reportPath): int {
      $command = sprintf(
         '%s %s %s 90 2>&1',
         escapeshellarg(PHP_BINARY),
         escapeshellarg(dirname(__DIR__) . '/bin/check-coverage.php'),
         escapeshellarg($reportPath)
      );

      $output = [];
      $status = 0;
      exec($command, $output, $status);

      return $status;
   }
}
