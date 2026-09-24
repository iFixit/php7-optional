<?php

declare(strict_types = 1);

namespace Optional\StaticAnalysis\Psalm;

use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt;
use Psalm\CodeLocation;
use Psalm\IssueBuffer;
use Psalm\Plugin\EventHandler\AfterStatementAnalysisInterface;
use Psalm\Plugin\EventHandler\Event\AfterStatementAnalysisEvent;
use Psalm\Type\Atomic\TNamedObject;

/**
 * Finds a call whose `Result` is dropped on its own line
 *
 * Only a statement that is nothing but a call counts, so assigning the result,
 * returning it or passing it on are all uses. Assigning it to `$_`, or on PHP
 * 8.5 casting it to `(void)`, is how to drop one on purpose. A chain that ends
 * in `tapErr()` has looked at the error, so it is not reported.
 */
final class UnusedResultHook implements AfterStatementAnalysisInterface {
   #[\Override]
   public static function afterStatementAnalysis(AfterStatementAnalysisEvent $event): ?bool {
      $statement = $event->getStmt();

      if (!$statement instanceof Stmt\Expression) {
         return null;
      }

      $call = $statement->expr;

      if ($call instanceof Expr\MethodCall || $call instanceof Expr\NullsafeMethodCall || $call instanceof Expr\StaticCall) {
         if ($call->name instanceof Identifier && $call->name->name === 'tapErr') {
            return null;
         }
      } elseif (!$call instanceof Expr\FuncCall) {
         return null;
      }

      $source = $event->getStatementsSource();
      $type = $source->getNodeTypeProvider()->getType($call);

      foreach ($type?->getAtomicTypes() ?? [] as $atomic) {
         if ($atomic instanceof TNamedObject && strtolower($atomic->value) === 'optional\result') {
            IssueBuffer::maybeAdd(
               new UnusedResult(
                  'This Result is never looked at, so if it is an Err the failure is lost. '
                  . 'Handle it, or assign it to $_ to drop it on purpose',
                  new CodeLocation($source, $call)
               ),
               $source->getSuppressedIssues()
            );

            break;
         }
      }

      return null;
   }
}
