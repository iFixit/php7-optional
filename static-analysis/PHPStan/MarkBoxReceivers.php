<?php

declare(strict_types = 1);

namespace Optional\StaticAnalysis\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeVisitorAbstract;

/**
 * Marks the expression a method is called on, the one place MergeBoxUnions
 * merges. Everywhere else a union of boxes stays a union, so a property,
 * parameter or return declared as one still accepts it.
 */
final class MarkBoxReceivers extends NodeVisitorAbstract {
   public const ATTRIBUTE = 'optionalBoxReceiver';

   #[\Override]
   public function enterNode(Node $node): ?Node {
      if ($node instanceof Expr\MethodCall || $node instanceof Expr\NullsafeMethodCall) {
         $node->var->setAttribute(self::ATTRIBUTE, true);
      }

      return null;
   }
}
