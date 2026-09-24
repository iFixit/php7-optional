<?php
declare(strict_types = 1);

namespace OptionExample\After;

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Optional\Option;

// optionExampleBefore.php with the box. The missing key, a stored null and
// the throw are all handled by the chain, so there is no null to check and no
// try to write.

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

echo Option::fromArray($somePerson, 'name')
   ->notNull()
   ->mapSafely(fn(array $person) => SomeComplexThing::doWork("{$person['first']} {$person['last']}"))
   ->map(fn(string $name) => new User($name))
   ->match(
      some: fn(User $user) => "The user is {$user->name}\n",
      none: fn() => "Oh no! The user is missing!\n",
   );
