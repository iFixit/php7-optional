<?php
declare(strict_types = 1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Optional\Option;

// A config reader where any key along the path may be missing. Each step of
// the path is an andThen(), so a miss anywhere ends in the default.

final class Config {
   /** @var Option<array<array-key, mixed>> */
   private Option $config;

   /** @param array<array-key, mixed> $configFileContents */
   public function __construct(array $configFileContents) {
      $this->config = Option::some($configFileContents);
   }

   public function get(string ...$jsonKeys): Option {
      return array_reduce(
         $jsonKeys,
         fn(Option $config, string $jsonKey) => $config->andThen(
            fn(mixed $configData) => is_array($configData) ? Option::fromArray($configData, $jsonKey) : Option::none()
         ),
         $this->config
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

$config = new Config(json_decode($configFileContents, true, flags: JSON_THROW_ON_ERROR));

$name = $config
   ->get('environment_config', 'app', 'name')
   ->valueOr('My Default Name');

$dbConnectionStr = $config
   ->get('environment_config', 'database')
   ->map(fn(array $db) => "Server={$db['host']};Port={$db['port']};Uid={$db['username']};Pwd={$db['password']};")
   ->valueOr('Server=myServerAddress;Port=1234;Database=myDataBase;Uid=myUsername;Pwd=myPassword;');

$missing = $config
   ->get('environment_config', 'cache', 'host')
   ->valueOr('no cache configured');

echo "Will connect to {$name} with: {$dbConnectionStr}\n";
echo "Cache: {$missing}\n";
