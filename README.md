# php7-optional

## Usage

### Using the library

To use Optional simply import the following namespace:

```php
use Optional\Option;
```

### Creating optional values

#### Option::some($thing);
Creates a option with a boxed value

```php
$someThing = Option::some(1);
$someClass = Option::some(new SomeObject());

$someNullThing = Option::some(null); // Valid
```

#### Option::none();
Creates a option which represents an empty box

```php
$none = Option::none();
```

#### Option::someWhen($thing, $filterFunc);
Take a value, turn it a `Option::some($thing)` iff the `$filterFunc` returns true

```php
$positiveThing = Option::someWhen(1, function($x) { return $x > 0; });
$negativeThing = Option::someWhen(1, function($x) { return $x < 0; });
```

#### Option::someWhen($thing, $filterFunc);
Take a value, turn it a `Option::none()` iff the `$filterFunc` returns true

```php
$positiveThing = Option::noneWhen(1, function($x) { return $x < 0; });
$negativeThing = Option::noneWhen(1, function($x) { return $x > 0; });
```

### Retrieving values

Note: Since php does not have generic types it is not possible to type check the input / output match.

#### $option->valueOr($otherValue);
Returns the options value or returns `$otherValue`

```php
$someThing = Option::some(1);
$someClass = Option::some(new SomeObject());

$none = Option::none();

$myVar = $someThing->valueOr("Some other value!"); // 1
$myVar = $someClass->valueOr("Some other value!"); // instance of SomeObject

$myVar = $none->valueOr("Some other value!"); // "Some other value!"

$none = Option::some(null)->valueOr("Some other value!"); // null, See option->notNull()
```

#### $option->valueOrCreate($valueFactoryFunc);
Returns the options value or calls `$valueFactoryFunc` and returns the value of that function

```php
$someThing = Option::some(1);
$someClass = Option::some(new SomeObject());

$none = Option::none();

$myVar = $someThing->valueOrCreate(function() { return new NewObject(); }); // 1
$myVar = $someClass->valueOrCreate(function() { return new NewObject(); }); // instance of SomeObject

$myVar = $none->valueOrCreate(function() { return new NewObject(); }); // instance of NewObject
```


### Run a function instead of retriving the value

#### $option->match($someFunc, $noneFunc);
Runs only 1 function:

* `$someFunc` iff the option is `Option::some`
* `$noneFunc` iff the option is `Option::some`

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

#### $option->matchSome($someFunc);
Side effect function: Runs the function iff the option is `Option::some`

```php
$configOption = Option::some($config)->notNull();

$configOption->matchSome(
   function($x) { var_dump("Your config: {$x}"); }
);
```

#### $option->matchNone($noneFunc);
Side effect function: Runs the function iff the option is `Option::none`

```php
$configOption = Option::some($config)->notNull();

$configOption->matchNone(
   function() { var_dump("Config was missing!"); }
);
```

### Transforming and filtering values

#### $option->notNull();
Turn a `Option::some(null)` into an `Option::none()`

```php
$someThing = Option::some(null); // Valid
$noneThing = $someThing->notNull(); // Turn null into an none Option
```

#### $option->or($otherValue);
Returns a `Option::some($value)` iff the the option orginally was `Option::none`

```php
$none = Option::none();
$myVar = $none->or(10); // A some instance, with value 10
```

#### $option->orCreate($valueFactoryFunc);
Returns a `Option::some($value)` iff the the option orginally was `Option::none`

The `$valueFactoryFunc` is called lazily - iff the option orginally was `Option::none`

```php
$none = Option::none();
$myVar = $none->orCreate(function() { return 10; }); // A some instance, with value 10, but lazy
```

#### $option->else($otherOption);
iff `Option::none` return `$otherOption`, otherwise return the orginal `$option`

```php
$none = Option::none();
$myVar = $none->else(Option::some(10)); // A some instance, with value 10
$myVar = $none->else(Option::none()); // A new none instance
```

#### $option->elseCreate($otherOptionFactoryFunc);
iff `Option::none` return the `Option` returned by `$otherOptionFactoryFunc`, otherwise return the orginal `$option`

`$otherOptionFactoryFunc` is run lazily

```php
$none = Option::none();

$myVar = $none->elseCreate(function() { return Option::some(10); }); // A some instance, with value 10, but lazy
```

#### $option->map($mapValueFunc);
Maps the `$value` of a `Option::some($value)`

The map function runs iff the options is a `Option::some`
Otherwise the `Option:none` is propigated

```php
$none = Option::none();
$stillNone = $none->map(function($x) { return $x * $x; });

$some = Option::some(5);
$someSquared = $some->map(function($x) { return $x * $x; });
```

#### $option->filter($filterFunc);
```php
$none = Option::none();
$stillNone = $none->filter(function($x) { return $x > 10; });

$some = Option::some(10);
$stillSome = $some->filter(function($x) { return $x == 10; });
$none = $some->filter(function($x) { return $x != 10; });
```


# Licence
 MIT


# Special Thanks
Heavily inspired by https://github.com/nlkl/Optional. In fact this is essentially a port of this library.
