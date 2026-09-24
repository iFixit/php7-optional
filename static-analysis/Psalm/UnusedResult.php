<?php

declare(strict_types = 1);

namespace Optional\StaticAnalysis\Psalm;

use Psalm\Issue\PluginIssue;

/**
 * A call's `Result` is dropped on its own line, so if it is an Err the failure is lost
 */
final class UnusedResult extends PluginIssue {
}
