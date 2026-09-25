<?php

declare(strict_types = 1);

/**
 * The library's own classes, for StaticAnalysisTest's PHPStan run. CI also
 * runs it with a PHPStan 1.12 installed apart from vendor/, and loading
 * vendor/autoload.php would bring PHPStan 2 into that process.
 */
spl_autoload_register(static function(string $class): void {
   $dirs = ['Optional\\StaticAnalysis\\' => '/static-analysis/', 'Optional\\' => '/src/'];

   foreach ($dirs as $prefix => $dir) {
      if (str_starts_with($class, $prefix)) {
         $file = dirname(__DIR__, 2) . $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

         if (is_file($file)) {
            require $file;
         }

         return;
      }
   }
});
