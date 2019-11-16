<?php
declare(strict_types = 1);

require_once dirname(__FILE__) . '/../src/Result.php';
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
      return Result::okay($response);
   } else {
      $url = $response['url'];
      $code = $response['code'];
      return Result::error("The request to $url failed with code $code!");
   }
};

$response = Curl::request('http:://www.github.com');

$result = $responseToResult($response);

$dbConnectionStr = $result
   ->mapData(function ($result) {
      try {
         return json_decode($result['data'], true);
      } catch(JsonDecodeException $ex) {
         return false;
      }
   })
   ->notFalsy("Json failed to decode!")
   ->mapData(function(array $json) {
      return $json['environment_config'];
   })
   ->mapData(function(array $environment_config) {
      return $environment_config['database'];
   })
   ->mapData(function(array $dbData) {
      $host = $dbData['host'];
      $port = $dbData['port'];
      $username = $dbData['username'];
      $password = $dbData['password'];
      return "Server=$host;Port=$port;Uid=$username;Pwd=$password;";
   })
   ->dataOr('Server=myServerAddress;Port=1234;Database=myDataBase;Uid=myUsername;Pwd=myPassword;');

echo "Connection str: $dbConnectionStr \n";
