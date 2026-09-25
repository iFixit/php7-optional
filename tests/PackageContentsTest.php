<?php

declare(strict_types = 1);

namespace Optional\Tests;

use PHPUnit\Framework\TestCase;

/**
 * What composer installs, which is `git archive` of a tag, since that is what
 * Packagist serves. .gitattributes keeps the tests and tooling out, so a new
 * file at the top of the repo ships unless it is listed there. This fails
 * until it is, or until it is added below on purpose.
 */
final class PackageContentsTest extends TestCase {
   public function testThePackageHoldsOnlyWhatUsersNeed(): void {
      $root = dirname(__DIR__);

      $status = 0;
      $output = [];
      exec(sprintf('git -C %s rev-parse --git-dir 2>/dev/null', escapeshellarg($root)), $output, $status);

      if ($status !== 0) {
         $this->markTestSkipped('needs git and a checkout to build the archive');
      }

      $output = [];
      exec(sprintf('git -C %s archive --worktree-attributes HEAD | tar -t', escapeshellarg($root)), $output, $status);
      $this->assertSame(0, $status, 'git archive or tar failed');

      /** @var list<string> $output exec() fills this with one line per entry */
      $top = array_values(array_unique(array_map(fn(string $path): string => explode('/', $path)[0], $output)));
      sort($top);

      $this->assertSame(
         ['CHANGELOG.md', 'LICENSE', 'README.md', 'composer.json', 'extension.neon', 'src', 'static-analysis'],
         $top
      );
   }
}
