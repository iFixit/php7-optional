<?php
declare(strict_types = 1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Optional\Option;

// A config reader where any key along the path may be missing. Each step of
// the path is a flatMap(), so a miss anywhere ends in the default. A decoded
// JSON value could be anything, so getText() also turns a value that is not a
// string or a number into a None.

final class Config {
   /** @var Option<array<array-key, mixed>> */
   private Option $config;

   /** @param array<array-key, mixed> $configFileContents */
   public function __construct(array $configFileContents) {
      $this->config = Option::some($configFileContents);
   }

   /** @return Option<mixed> */
   public function get(string ...$jsonKeys): Option {
      return array_reduce(
         $jsonKeys,
         fn(Option $config, string $jsonKey) => $config->flatMap(
            fn(mixed $configData) => is_array($configData) ? Option::fromArray($configData, $jsonKey) : Option::none()
         ),
         $this->config
      );
   }

   /** @return Option<string> */
   public function getText(string ...$jsonKeys): Option {
      return $this->get(...$jsonKeys)->flatMap(
         fn(mixed $value) => is_string($value) || is_int($value) ? Option::some((string) $value) : Option::none()
      );
   }
}

$configFileContents = '
   {
      "environment_config": {
        "app": {
          "name": "Sample App",
          "url": "http://10.0.0.120:8080/app/"
        },
        "database": {
          "name": "mysql database",
          "host": "10.0.0.120",
          "port": 3128,
          "username": "root",
          "password": "toor"
        },
        "rest_api": "http://10.0.0.120:8080/v2/api/"
      }
    }
';

/** @var array<array-key, mixed> $decoded the document above is an object */
$decoded = json_decode($configFileContents, true, flags: JSON_THROW_ON_ERROR);
$config = new Config($decoded);

$name = $config
   ->getText('environment_config', 'app', 'name')
   ->valueOr('My Default Name');

// every part of the connection string, or the default if any one is missing
$dbConnectionStr = Option::do(function() use ($config): string {
   $host = $config->getText('environment_config', 'database', 'host')->valueOrReturn();
   $port = $config->getText('environment_config', 'database', 'port')->valueOrReturn();
   $user = $config->getText('environment_config', 'database', 'username')->valueOrReturn();
   $password = $config->getText('environment_config', 'database', 'password')->valueOrReturn();

   return "Server={$host};Port={$port};Uid={$user};Pwd={$password};";
})->valueOr('Server=myServerAddress;Port=1234;Database=myDataBase;Uid=myUsername;Pwd=myPassword;');

$missing = $config
   ->getText('environment_config', 'cache', 'host')
   ->valueOr('no cache configured');

echo "Will connect to {$name} with: {$dbConnectionStr}\n";
echo "Cache: {$missing}\n";
