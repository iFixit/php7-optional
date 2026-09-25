<?php

declare(strict_types = 1);

namespace Optional\StaticAnalysis\Psalm;

use Psalm\Plugin\EventHandler\AfterExpressionAnalysisInterface;
use Psalm\Plugin\EventHandler\Event\AfterExpressionAnalysisEvent;
use Psalm\Type;
use Psalm\Type\Atomic\TGenericObject;
use Psalm\Type\Atomic\TTemplateParam;
use Psalm\Type\Union;

/**
 * Types a box built in two branches as one box
 *
 * `$flag ? Result::ok($n) : Result::err('no')` is a
 * `Result<int, never>|Result<never, string>` to psalm. The next typed callback
 * then fails, because psalm checks it against the `never` half too and
 * reports `ReservedWord`. A box holds one value and its type parameters are
 * covariant, so `Result<A, never>|Result<never, B>` holds exactly what
 * `Result<A, B>` holds, and this rewrites the one as the other. The same goes
 * for `Option<A>|Option<B>`.
 */
final class MergeBoxUnions implements AfterExpressionAnalysisInterface {
   private const BOXES = ['optional\option', 'optional\result'];

   #[\Override]
   public static function afterExpressionAnalysis(AfterExpressionAnalysisEvent $event): ?bool {
      if ($event->getExpr()->getAttribute(MarkBoxReceivers::ATTRIBUTE) !== true) {
         return null;
      }

      $types = $event->getStatementsSource()->getNodeTypeProvider();
      $type = $types->getType($event->getExpr());

      if ($type === null || count($type->getAtomicTypes()) < 2) {
         return null;
      }

      /** @var array<string, TGenericObject> $boxes */
      $boxes = [];
      $others = [];
      $merged = false;

      foreach ($type->getAtomicTypes() as $atomic) {
         // a template bounded by one box calls that box's methods, so it merges as one;
         // a bound such as Result|null is not one box
         $box = $atomic instanceof TTemplateParam && $atomic->as->isSingle() ? $atomic->as->getSingleAtomic() : $atomic;

         if (!$box instanceof TGenericObject || !in_array(strtolower($box->value), self::BOXES, true)) {
            $others[] = $atomic;
            continue;
         }

         $class = strtolower($box->value);

         if (!isset($boxes[$class])) {
            $boxes[$class] = $box;
            continue;
         }

         // a docblock with the wrong number of types, such as Result<User>, is psalm's to report
         if (count($box->type_params) !== count($boxes[$class]->type_params)) {
            $others[] = $atomic;
            continue;
         }

         $boxes[$class] = $boxes[$class]->setTypeParams(array_map(
            fn(Union $held, Union $more): Union => Type::combineUnionTypes($held, $more, $event->getCodebase()),
            $boxes[$class]->type_params,
            $box->type_params,
         ));
         $merged = true;
      }

      if ($merged && $boxes !== []) {
         $types->setType($event->getExpr(), $type->setTypes([...$others, ...array_values($boxes)]));
      }

      return null;
   }
}
