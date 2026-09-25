<?php

declare(strict_types = 1);

namespace Optional\StaticAnalysis\Psalm;

use Psalm\Plugin\PluginEntryPointInterface;
use Psalm\PluginRegistrationSocket;
use SimpleXMLElement;

/**
 * Reports a `Result` that is returned and never looked at, as Rust's
 * `#[must_use]` does, and types a box built in two branches as one box. Turn
 * it on in psalm.xml:
 *
 *     <plugins>
 *         <pluginClass class="Optional\StaticAnalysis\Psalm\Plugin"/>
 *     </plugins>
 *
 * @api
 */
final class Plugin implements PluginEntryPointInterface {
   #[\Override]
   public function __invoke(PluginRegistrationSocket $registration, ?SimpleXMLElement $config = null): void {
      // psalm only registers a class that is already loaded, so load each first
      foreach ([UnusedResultHook::class, MarkBoxReceivers::class, MergeBoxUnions::class] as $hook) {
         class_exists($hook);
         $registration->registerHooksFromClass($hook);
      }
   }
}
