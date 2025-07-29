# PHP Result

A Kotlin-style Result library for PHP 8.4+ that provides a type-safe way to handle operations that can succeed or fail. Built on top of the powerful `phpoption/phpoption` library for enhanced functionality.

## Features

- **Type-safe error handling**: No more `null` checks or exception catching everywhere
- **Functional programming style**: Chain operations with `map`, `flatMap`, `filter`, etc.
- **Comprehensive API**: Includes `runCatching`, `combine`, `sequence`, `retry`, and more
- **Kotlin-inspired**: Familiar API for developers coming from Kotlin
- **PHP 8.4+ ready**: Uses modern PHP features like readonly classes
- **Built on PhpOption**: Leverages the battle-tested `phpoption/phpoption` library for robust Option handling
- **Seamless Option integration**: Convert between Result and Option types effortlessly

## Installation

```bash
composer require php-result/result
```

## Quick Start

### Basic Usage

```php
use PhpResult\Result;
use PhpOption\Option;
use PhpOption\Some;
use PhpOption\None;
use function PhpResult\runCatching;
use function PhpResult\fromOption;

// Create successful result
$success = Result::ok(42);
echo $success->getOrNull(); // 42

// Create failed result
$failure = Result::err('Something went wrong');
echo $failure->getErrorOrNull(); // 'Something went wrong'

// Create Result from Option
$someOption = new Some(42);
$result = Result::fromOption($someOption);
echo $result->getOrNull(); // 42

$noneOption = None::create();
$errorResult = Result::fromOption($noneOption, 'Option was empty');
echo $errorResult->getErrorOrNull(); // 'Option was empty'

// Safe execution with runCatching
$result = runCatching(fn() => riskyOperation());

if ($result->isOk()) {
    echo "Success: " . $result->getOrNull();
} else {
    echo "Error: " . $result->getErrorOrNull();
}
```

### Chaining Operations

```php
$result = Result::ok(10)
    ->map(fn($x) => $x * 2)           // Transform value: 20
    ->filter(fn($x) => $x > 15)       // Keep if > 15: still 20
    ->flatMap(fn($x) => Result::ok($x + 5)) // Chain: 25
    ->getOrElse(0);                   // Get value or default

echo $result; // 25
```

### Error Handling

```php
$result = Result::err('initial error')
    ->recover(fn($error) => 'recovered value')  // Recover from error
    ->map(fn($value) => strtoupper($value));    // Transform: 'RECOVERED VALUE'

echo $result->getOrNull(); // 'RECOVERED VALUE'
```

## API Reference

### Core Methods

- `Result::ok($value)` - Create successful result
- `Result::err($error)` - Create failed result
- `Result::fromOption($option, $errorValue)` - Create Result from Option
- `Result::fromNullable($value, $errorValue)` - Create Result from nullable value
- `isOk()` - Check if result is successful
- `isErr()` - Check if result is failed
- `getOrNull()` - Get value or null
- `getOrElse($default)` - Get value or default
- `getOrThrow()` - Get value or throw exception
- `toOption()` - Convert success value to Option
- `toErrorOption()` - Convert error value to Option

### Transformation Methods

- `map($transform)` - Transform success value
- `mapError($transform)` - Transform error value
- `flatMap($transform)` - Chain results (monadic bind)
- `filter($predicate, $errorValue)` - Filter value with predicate
- `mapToOption($transform)` - Transform and return Option
- `flatMapToOption($transform)` - FlatMap and return Option
- `filterToOption($predicate)` - Filter and return Option

### Side Effects

- `onSuccess($callback)` - Execute callback on success
- `onFailure($callback)` - Execute callback on failure

### Recovery

- `recover($recovery)` - Recover from error with value
- `recoverWith($recovery)` - Recover from error with Result

### Folding

- `fold($onError, $onSuccess)` - Transform both cases to single value

## Utility Functions

### runCatching

Execute code safely and return a Result:

```php
use function PhpResult\runCatching;

$result = runCatching(fn() => json_decode($json, flags: JSON_THROW_ON_ERROR));

$data = $result
    ->map(fn($decoded) => $decoded->data)
    ->getOrElse([]);
```

### Combining Results

```php
use function PhpResult\combine;
use function PhpResult\map2;

// Combine multiple results
$combined = combine(
    Result::ok(1),
    Result::ok(2),
    Result::ok(3)
); // Result::ok([1, 2, 3])

// Apply function to two results
$sum = map2(
    fn($a, $b) => $a + $b,
    Result::ok(10),
    Result::ok(5)
); // Result::ok(15)
```

### Working with Arrays

```php
use function PhpResult\sequence;
use function PhpResult\traverse;

// Convert array of Results to Result of array
$results = [Result::ok(1), Result::ok(2), Result::ok(3)];
$sequenced = sequence($results); // Result::ok([1, 2, 3])

// Apply function to array and collect results
$numbers = [1, 2, 3, 4, 5];
$doubled = traverse($numbers, fn($x) => Result::ok($x * 2));
// Result::ok([2, 4, 6, 8, 10])
```

### Retry Logic

```php
use function PhpResult\retry;

$result = retry(
    fn() => unreliableApiCall(),
    maxAttempts: 3,
    delayMs: 1000
);
```

### Conversion Utilities

```php
use function PhpResult\fromNullable;
use function PhpResult\fromOption;
use function PhpResult\fromBoolean;
use PhpOption\Some;
use PhpOption\None;

// Convert nullable to Result 
$result = fromNullable($maybeNull, 'Value was null');

// Convert Option to Result
$result = fromOption(new Some(42)); // Result::ok(42)
$result = fromOption(None::create(), 'No value'); // Result::err('No value')

// Convert boolean to Result
$result = fromBoolean($condition, 'success', 'failed');

// Additional PhpOption integration functions
use function PhpResult\lift;
use function PhpResult\lift2;
use function PhpResult\allOk;
use function PhpResult\anyOk;
use function PhpResult\partition;

// Lift regular functions to work with Results
$multiply = fn($x) => $x * 2;
$liftedMultiply = lift($multiply);
$result = $liftedMultiply(Result::ok(21)); // Result::ok(42)

// Check if all Results are Ok
$allSuccessful = allOk([Result::ok(1), Result::ok(2), Result::ok(3)]); // true

// Partition Results into Ok and Err arrays
[$oks, $errs] = partition([Result::ok(1), Result::err('error'), Result::ok(2)]);
// $oks = [1, 2], $errs = ['error']
```

## Real-World Examples

### API Client

```php
class ApiClient
{
    public function fetchUser(int $id): Result
    {
        return runCatching(fn() => $this->httpClient->get("/users/{$id}"))
            ->flatMap(fn($response) => $this->parseJson($response->getBody()))
            ->map(fn($data) => new User($data))
            ->onFailure(fn($error) => $this->logger->error('Failed to fetch user', ['error' => $error]));
    }
    
    private function parseJson(string $json): Result
    {
        return runCatching(fn() => json_decode($json, true, flags: JSON_THROW_ON_ERROR))
            ->flatMap(fn($data) => fromNullable($data, 'Invalid JSON response'));
    }
}

// Usage
$userResult = $apiClient->fetchUser(123);

$userName = $userResult
    ->map(fn($user) => $user->name)
    ->getOrElse('Unknown User');
```

### Database Operations

```php
class UserRepository
{
    public function findById(int $id): Result
    {
        return runCatching(fn() => $this->db->query('SELECT * FROM users WHERE id = ?', [$id]))
            ->flatMap(fn($stmt) => fromNullable($stmt->fetch(), 'User not found'))
            ->map(fn($row) => User::fromArray($row));
    }
    
    public function createUser(array $data): Result
    {
        return $this->validateUserData($data)
            ->flatMap(fn($validData) => $this->insertUser($validData))
            ->onSuccess(fn($user) => $this->logger->info('User created', ['id' => $user->id]));
    }
}
```

### Configuration Loading

```php
$config = sequence([
        fromNullable($_ENV['DB_HOST'], 'Missing DB_HOST'),
        fromNullable($_ENV['DB_USER'], 'Missing DB_USER'), 
        fromNullable($_ENV['DB_PASS'], 'Missing DB_PASS'),
    ])
    ->map(fn($values) => new DatabaseConfig(...$values))
    ->getOrThrow();
```

## Testing

```bash
./vendor/bin/phpunit
```

## License

MIT License. See LICENSE file for details.
