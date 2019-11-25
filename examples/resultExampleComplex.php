<?php
declare(strict_types = 1);

require_once dirname(__FILE__) . '/../src/SimpleResult.php';
require_once dirname(__FILE__) . '/../src/Either.php';
use Optional\Result;

class Curl {
   public static function request(string $url): array {
      return [
         'code' => 200,
         'url' => $url,
         'data' =>
            '{
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
             }'
      ];
   }
}

$responseToResult = function (array $response): Result {
   $wasGood = $response['code'] == 200;

   if ($wasGood) {
      return SimpleResult::okay($response);
   } else {
      $url = $response['url'];
      $code = $response['code'];
      return SimpleResult::error("The request to $url failed with code $code!");
   }
};

$response = Curl::request('http:://www.github.com');

$result = $responseToResult($response);

$dbConnectionStr = $result
   ->map(function ($result) {
      return json_decode($result['data'], true);
   })
   ->notFalsy("Json failed to decode!")
   ->map(function(array $json) {
      $dbData = $json['environment_config']['database'];

      $host = $dbData['host'];
      $port = $dbData['port'];
      $username = $dbData['username'];
      $password = $dbData['password'];

      return "Server=$host;Port=$port;Uid=$username;Pwd=$password;";
   })
   ->dataOrThrow();

echo "Connection str: $dbConnectionStr \n";


$dbConnectionResult = $result
   ->map(function ($result) {
      return false;
   })
   ->notFalsy("Json failed to decode!")
   ->map(function(array $json) {
      $dbData = $json['environment_config']['database'];

      $host = $dbData['host'];
      $port = $dbData['port'];
      $username = $dbData['username'];
      $password = $dbData['password'];

      return "Server=$host;Port=$port;Uid=$username;Pwd=$password;";
   });

   try {
      $dbConnectionResult->dataOrThrow();
   } catch (Throwable $ex) {
      // Don't want to kill the example
      echo "Example of a wrapped exception: {$ex->getMessage()}\n";
   }

   $defaultValue = $dbConnectionResult
   ->orSetDataTo('Server=myServerAddress;Port=1234;Database=myDataBase;Uid=myUsername;Pwd=myPassword;')
   ->dataOrThrow();

   echo "Example of setting to a default: $defaultValue\n";
