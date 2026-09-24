<?php
declare(strict_types = 1);

// Read a nested name, run it through something that can throw, and build a
// user from it, in plain PHP. optionExampleAfter.php does the same with the box.
// Each script has its own namespace, since both define the same two classes.

namespace OptionExample\Before;

use Exception;

final class SomeComplexThing {
   public static function doWork(string $thing): string {
      return $thing;
   }
}

final class User {
   public function __construct(public readonly string $name) {}
}

/** @var array<string, array{first: string, last: string}|null> $somePerson a row that may lack the name */
$somePerson = [
   'name' => [
      'first' => 'First',
      'last' => 'Last',
   ],
];

$person = $somePerson['name'] ?? null;
$user = null;

if ($person !== null) {
   try {
      $user = new User(SomeComplexThing::doWork("{$person['first']} {$person['last']}"));
   } catch (Exception) {
      $user = null;
   }
}

if ($user !== null) {
   echo "The user is {$user->name}\n";
} else {
   echo "Oh no! The user is missing!\n";
}
