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
use Psalm\Type\Atomic\TTemplateParam;

/**
 * Finds a call whose `Result` is dropped on its own line
 *
 * Only a statement that is nothing but a call counts, so assigning the result,
 * returning it or passing it on are all uses. Assigning it to `$_` is how to
 * drop one on purpose. A chain that ends in `tapErr()` has looked at the error,
 * so it is not reported. A call that can return something else as well, such
 * as `Result|false`, is reported too, as long as it can return a `Result`.
 */
final class UnusedResultHook implements AfterStatementAnalysisInterface {
   /**
    * A `match`, a ternary or a `??` used as a statement drops whichever value it
    * picks, and that can be a call's Result
    */
   private static function choosesAValue(Expr $expr): bool {
      return $expr instanceof Expr\Match_ || $expr instanceof Expr\Ternary || $expr instanceof Expr\BinaryOp\Coalesce;
   }

   #[\Override]
   public static function afterStatementAnalysis(AfterStatementAnalysisEvent $event): ?bool {
      $statement = $event->getStmt();

      if (!$statement instanceof Stmt\Expression) {
         return null;
      }

      $call = $statement->expr;

      // `$ok && $repo->save()` and `@$repo->save()` on their own line drop the call's
      // Result too. psalm also analyses the call of a bare `&&` as a statement of its
      // own, and keeps one report per issue and place, so that call is reported once.
      while ($call instanceof Expr\BinaryOp\BooleanAnd || $call instanceof Expr\BinaryOp\BooleanOr
         || $call instanceof Expr\BinaryOp\LogicalAnd || $call instanceof Expr\BinaryOp\LogicalOr
         || $call instanceof Expr\ErrorSuppress) {
         $call = $call instanceof Expr\ErrorSuppress ? $call->expr : $call->right;
      }

      if ($call instanceof Expr\MethodCall || $call instanceof Expr\NullsafeMethodCall || $call instanceof Expr\StaticCall) {
         // PHP matches a method name in any case
         if ($call->name instanceof Identifier && $call->name->toLowerString() === 'taperr') {
            return null;
         }
      } elseif (!$call instanceof Expr\FuncCall && !self::choosesAValue($call)) {
         return null;
      }

      $source = $event->getStatementsSource();
      $type = $source->getNodeTypeProvider()->getType($call);

      $atomics = array_values($type?->getAtomicTypes() ?? []);

      while (($atomic = array_shift($atomics)) !== null) {
         // a template such as `R of Result<int, string>|null` can be a Result too, as PHPStan reports
         if ($atomic instanceof TTemplateParam) {
            array_push($atomics, ...array_values($atomic->as->getAtomicTypes()));
            continue;
         }

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
