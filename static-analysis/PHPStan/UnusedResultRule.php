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
use PHPStan\Type\UnionType;

/**
 * Reports a `Result` that is returned and never looked at, as Rust's
 * `#[must_use]` does. Turn it on by including this package's extension.neon.
 *
 * Only a statement that is nothing but a call counts, so assigning the result,
 * returning it or passing it on are all uses. Assigning it to `$_` is how to
 * drop one on purpose. A chain that ends in `tapErr()` has looked at the error,
 * so it is not reported. A call that can return something else as well, such
 * as `Result|false`, is reported too, as long as it can return a `Result`.
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

      $type = $scope->getType($call);
      $result = new ObjectType('Optional\Result');
      $dropsAResult = false;

      // a null-safe call is Result|null, and the Result half still counts
      foreach ($type instanceof UnionType ? $type->getTypes() : [$type] as $member) {
         // never is a subtype of every type, Result included, and PHPStan gives
         // it to a call that returns nothing, such as parent::__construct()
         if (!$member instanceof NeverType && $result->isSuperTypeOf($member)->yes()) {
            $dropsAResult = true;
         }
      }

      if (!$dropsAResult) {
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
