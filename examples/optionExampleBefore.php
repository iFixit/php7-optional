<?php
declare(strict_types = 1);

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

$person = isset($somePerson['name']) ? $somePerson['name'] : null;

if (!$person) {
   return;
}

$fullName = "{$person['first']} {$person['last']}";

try {
   $name = SomeComplexThing::doWork($fullName);
} catch (\Exception $e) {
   $name = null;
}

$user = new User($name);

if ($user) {
   echo "The user is {$user->name}\n";
} else {
   echo "Oh no! The user is missing!\n";
}