<?php
declare(strict_types = 1);

require_once dirname(__FILE__) . '/../src/Option.php';
use Optional\Option;

class Config {
   private $config;

   public function __construct(array $configFileContents) {
      $this->config = Option::some($configFileContents);
   }

   public function get(... $jsonKeys): Option {
      return array_reduce($jsonKeys, function (Option $config, string $jsonKey) {
         return $this->getFrom($config, $jsonKey);
      }, $this->config);
   }

   private function getFrom(Option $config, string $jsonKey): Option {
      return $config->andThen(function(array $configData) use ($jsonKey) {
         return Option::fromArray($configData, $jsonKey);
      });
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

$configData = json_decode($configFileContents, true);
$config = new Config($configData);

$name = $config
   ->get('environment_config', 'app', 'name')
   ->valueOr('My Default Name');

$dbOption = $config
   ->get('environment_config', 'database');

$dbConnectionStr = $dbOption->map(function($dbData) {
   $host = $dbData['host'];
   $port = $dbData['port'];
   $username = $dbData['username'];
   $password = $dbData['password'];
   return "Server=$host;Port=$port;Uid=$username;Pwd=$password;";
})
->valueOr('Server=myServerAddress;Port=1234;Database=myDataBase;Uid=myUsername;Pwd=myPassword;');

echo "Will connect to $name with: $dbConnectionStr \n";