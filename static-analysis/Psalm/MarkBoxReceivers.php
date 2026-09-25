<?php

declare(strict_types = 1);

namespace Optional\StaticAnalysis\Psalm;

use PhpParser\Node\Expr;
use Psalm\Plugin\EventHandler\BeforeExpressionAnalysisInterface;
use Psalm\Plugin\EventHandler\Event\BeforeExpressionAnalysisEvent;

/**
 * Marks the expression a method is called on, the one place MergeBoxUnions
 * merges. Everywhere else a union of boxes stays a union, so a property,
 * parameter or return declared as one still accepts it.
 */
final class MarkBoxReceivers implements BeforeExpressionAnalysisInterface {
   public const ATTRIBUTE = 'optionalBoxReceiver';

   #[\Override]
   public static function beforeExpressionAnalysis(BeforeExpressionAnalysisEvent $event): ?bool {
      $expr = $event->getExpr();

      if ($expr instanceof Expr\MethodCall || $expr instanceof Expr\NullsafeMethodCall) {
         $expr->var->setAttribute(self::ATTRIBUTE, true);
      }

      return null;
   }
}
