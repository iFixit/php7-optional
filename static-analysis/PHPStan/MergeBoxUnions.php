<?php

declare(strict_types = 1);

namespace Optional\StaticAnalysis\PHPStan;

use PhpParser\Node\Expr;
use PHPStan\Analyser\Scope;
use PHPStan\Node\Expr\TypeExpr;
use PHPStan\Type\ExpressionTypeResolverExtension;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\UnionType;

/**
 * Types a box built in two branches as one box
 *
 * `$flag ? Result::ok($n) : Result::err('no')` is a
 * `Result<int, never>|Result<never, string>` to PHPStan, and a typed callback
 * on it loses the value type: `->map(fn(int $x): int => $x * 2)` comes out as
 * `Result<never, string>`. A box holds one value and its type parameters are
 * covariant, so `Result<A, never>|Result<never, B>` holds exactly what
 * `Result<A, B>` holds, and this rewrites the one as the other where a method
 * is called on it: a variable, a ternary, a `match`, a `??`, a call that
 * returns one from a closure's two `return`s, a property. MarkBoxReceivers
 * marks those expressions. Anywhere else a box keeps its union, so a
 * parameter or return declared as one still accepts it.
 *
 * PHPStan keeps one type per expression text, such as `$row`, not per node,
 * so a receiver read plainly first, as in an `echo` just before the call,
 * comes back unmerged. The call is typed on one box then too. The README
 * lists what that leaves.
 *
 * It resolves a marked expression itself, so it hands that type back, merged
 * or not. Returning null would make PHPStan resolve it a second time.
 */
final class MergeBoxUnions implements ExpressionTypeResolverExtension {
   /**
    * The expressions being resolved right now. Asking the scope for their type
    * calls this extension again, and that inner call has to step aside.
    *
    * @var array<int, true>
    */
   private array $resolving = [];

   #[\Override]
   public function getType(Expr $expr, Scope $scope): ?Type {
      $id = spl_object_id($expr);

      if (isset($this->resolving[$id])) {
         return null;
      }

      $marked = $expr->getAttribute(MarkBoxReceivers::ATTRIBUTE) === true;
      $onOneBox = null;

      // the receiver may have the type PHPStan kept from a plain read of it, so
      // the call is typed on one box as well
      if ($expr instanceof Expr\MethodCall || $expr instanceof Expr\NullsafeMethodCall) {
         $receiver = $scope->getType($expr->var);
         $merged = self::merge($receiver);

         if ($merged !== $receiver) {
            $onOneBox = clone $expr;
            $onOneBox->var = new TypeExpr($merged);
         }
      }

      if (!$marked && $onOneBox === null) {
         return null;
      }

      $this->resolving[$id] = true;

      try {
         $type = $scope->getType($onOneBox ?? $expr);
      } finally {
         unset($this->resolving[$id]);
      }

      return $marked ? self::merge($type) : $type;
   }

   private static function merge(Type $type): Type {
      if (!$type instanceof UnionType) {
         return $type;
      }

      /** @var array<string, list<Type>> $params */
      $params = [];
      $others = [];
      $merged = false;

      foreach ($type->getTypes() as $member) {
         // a template bounded by one box names that box's class; one that may be null names none
         $classes = $member->getObjectClassNames();
         $held = match ($classes) {
            ['Optional\Option'] => [$member->getTemplateType('Optional\Option', 'T')],
            ['Optional\Result'] => [$member->getTemplateType('Optional\Result', 'T'), $member->getTemplateType('Optional\Result', 'E')],
            default => null,
         };

         if ($held === null) {
            $others[] = $member;
            continue;
         }

         $class = $classes[0];

         if (isset($params[$class])) {
            $params[$class] = array_map(TypeCombinator::union(...), $params[$class], $held);
            $merged = true;
         } else {
            $params[$class] = $held;
         }
      }

      if (!$merged) {
         return $type;
      }

      foreach ($params as $class => $types) {
         $others[] = new GenericObjectType($class, $types);
      }

      return TypeCombinator::union(...$others);
   }
}
