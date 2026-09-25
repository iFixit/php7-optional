<?php

declare(strict_types = 1);

namespace Optional\StaticAnalysis\PHPStan;

use PhpParser\Node\Expr;
use PHPStan\Analyser\Scope;
use PHPStan\Type\ExpressionTypeResolverExtension;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\Generic\TemplateType;
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
 * `Result<A, B>` holds, and this rewrites the one as the other on every
 * expression: a variable, a ternary, a `match`, a `??`, a call that returns
 * one from a closure's two `return`s, a property.
 *
 * It resolves each expression itself, so it always hands that type back,
 * merged or not. Returning null would make PHPStan resolve it a second time.
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
      if ($expr->getAttribute(MarkBoxReceivers::ATTRIBUTE) !== true) {
         return null;
      }

      $id = spl_object_id($expr);

      if (isset($this->resolving[$id])) {
         return null;
      }

      $this->resolving[$id] = true;

      try {
         $type = $scope->getType($expr);
      } finally {
         unset($this->resolving[$id]);
      }

      if (!$type instanceof UnionType) {
         return $type;
      }

      /** @var array<string, list<Type>> $params */
      $params = [];
      $others = [];
      $merged = false;

      foreach ($type->getTypes() as $member) {
         // a template bounded by a box stands for one exact box, and merging it would lose that
         if ($member instanceof TemplateType) {
            $others[] = $member;
            continue;
         }

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
