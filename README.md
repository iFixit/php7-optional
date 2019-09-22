# php7-optional

## Usage

### Using the library

To use Optional simply import the following namespace:

```php
use Optional\Option;
```

### Creating optional values


```php
$someThing = Option::some(1);
$someClass = Option::some(new SomeObject());

$none = Option::none();

// Take a value, turn it a Option::Some iff the filter function returns true
$positiveThing = Option::someWhen(1, function($x) { return $x > 0; });
$negativeThing = Option::someWhen(1, function($x) { return $x < 0; });

// Take a value, turn it a Option::None iff the filter function returns true
$positiveThing = Option::noneWhen(1, function($x) { return $x < 0; });
$negativeThing = Option::noneWhen(1, function($x) { return $x > 0; });


$someNullThing = Option::some(null); // Valid
```

### Retrieving values

```php
$someThing = Option::some(1);
$someClass = Option::some(new SomeObject());

$none = Option::none();

$myVar = $someThing->valueOr("Some other value!"); // 1
$myVar = $someClass->valueOr("Some other value!"); // instance of SomeObject

$myVar = $none->valueOr("Some other value!"); // "Some other value!"

$none = Option::some(null)->notNull(); // Turn null into an none Option
```

Note: Since php does not have generic types it is not possible to type check the input / outpuf match.

### Retrieving values lazily

```php
$someThing = Option::some(1);
$someClass = Option::some(new SomeObject());

$none = Option::none();

$myVar = $someThing->valueOrCreate(function() { return new NewObject(); }); // 1
$myVar = $someClass->valueOrCreate(function() { return new NewObject(); }); // instance of SomeObject

$myVar = $none->valueOrCreate(function() { return new NewObject(); }); // instance of NewObject
```


### Run a function instead of retriving the value

```php
$someThing = Option::some(1);

$someThingSquared = $someThing->match(
   function($x) { return $x * $x; },    // runs iff $someThing == Option::some
   function() { return 0; }             // runs iff $someThing == Option::none
);


$configOption = Option::some($config)->notNull();

$configOption->match(
   function($x) { var_dump("Your config: {$x}"); },
   function() { var_dump("Config was missing!"); }
);
```

Also side-effect matching is allowed

```php
$configOption = Option::some($config)->notNull();


$configOption->matchSome(
   function($x) { var_dump("Your config: {$x}"); }
);


$configOption->matchNone(
   function() { var_dump("Config was missing!"); }
);
```

### Transforming and filtering values

```php
$none = Option::none();

$myVar = $none->or(10); // A some instance, with value 10
$myVar = $none->or(function() { return 10; }); // A some instance, with value 10, but lazy

$myVar = $none->else(Option::some(10)); // A some instance, with value 10
$myVar = $none->else(Option::none()); // A new none instance

$myVar = $none->else(function() { return Option::some(10); }); // A some instance, with value 10, but lazy
```

```php
$none = Option::none();
$stillNone = $none->map(function($x) { return $x * $x; });

$some = Option::some(5);
$someSquared = $some->map(function($x) { return $x * $x; });
```

```php
$none = Option::none();
$stillNone = $none->filter(function($x) { return $x > 10; });

$some = Option::some(10);
$stillSome = $some->filter(function($x) { return $x == 10; });
$none = $some->filter(function($x) { return $x != 10; });


$some = Option::some(null); // A valid some
$none = $some->notNull();
```


# Licence
 MIT


# Special Thanks
Heavily inspired by https://github.com/nlkl/Optional. In fact this is essentially a port of this library.
