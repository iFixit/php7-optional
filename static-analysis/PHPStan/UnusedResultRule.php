<?php

declare(strict_types = 1);

namespace Optional\StaticAnalysis\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\NeverType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\TypeCombinator;

/**
 * Reports a `Result` that is returned and never looked at, as Rust's
 * `#[must_use]` does. Turn it on by including this package's extension.neon.
 *
 * Only a statement that is nothing but a call counts, so assigning the result,
 * returning it or passing it on are all uses. Assigning it to `$_`, or on PHP
 * 8.5 casting it to `(void)`, is how to drop one on purpose. A chain that ends
 * in `tapErr()` has looked at the error, so it is not reported.
 *
 * @implements Rule<Node\Stmt\Expression>
 */
final class UnusedResultRule implements Rule {
   public function getNodeType(): string {
      return Node\Stmt\Expression::class;
   }

   public function processNode(Node $node, Scope $scope): array {
      $call = $node->expr;

      if ($call instanceof Expr\MethodCall || $call instanceof Expr\NullsafeMethodCall || $call instanceof Expr\StaticCall) {
         if ($call->name instanceof Identifier && $call->name->name === 'tapErr') {
            return [];
         }
      } elseif (!$call instanceof Expr\FuncCall) {
         return [];
      }

      // a null-safe call is Result|null, and the Result half still counts
      $type = TypeCombinator::removeNull($scope->getType($call));

      // never is a subtype of every type, Result included, and PHPStan gives it
      // to a call that returns nothing, such as parent::__construct()
      if ($type instanceof NeverType || !(new ObjectType('Optional\Result'))->isSuperTypeOf($type)->yes()) {
         return [];
      }

      return [
         RuleErrorBuilder::message(
            'This Result is never looked at, so if it is an Err the failure is lost. '
            . 'Handle it, or assign it to $_ to drop it on purpose.'
         )->identifier('optional.unusedResult')->build(),
      ];
   }
}
