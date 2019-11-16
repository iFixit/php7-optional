<?php
declare(strict_types = 1);

require_once dirname(__FILE__) . '/../src/Option.php';
use Optional\Option;

class SomeComplexThing {
   public static function doWork(string $thing): string {
      return $thing;
   }
};

class User {
public $name;
   public function __construct(string $name) {
      $this->name = $name;
   }
};

$somePerson = [
   'name' => [
      'first' => 'First',
      'last' => 'Last'
   ]
];

$person = Option::fromArray($somePerson, 'name');

$nameOption = $person->mapSafely(function($person): string {
   $fullName = $person['first'] . $person['last'];
   return SomeComplexThing::doWork($fullName);
});

$userOption = $nameOption->map(function($name) {
   return new User($name);
});

$userOption->match(
   function ($user) { echo "The user is {$user->name}\n"; },
   function () { echo "Oh no! The user is missing!\n"; }
);