<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use PhpResult\Result;
use PhpOption\Some;
use PhpOption\None;
use function PhpResult\combine;
use function PhpResult\sequence;
use function PhpResult\traverse;
use function PhpResult\map2;
use function PhpResult\retry;
use function PhpResult\fromNullable;
use function PhpResult\fromOption;
use function PhpResult\lift;
use function PhpResult\partition;

// Combining multiple results
echo "=== Combining Results ===\n";

$user = Result::ok(['name' => 'John', 'age' => 30]);
$permissions = Result::ok(['read', 'write']);
$settings = Result::ok(['theme' => 'dark']);

$combined = combine($user, $permissions, $settings);
if ($combined->isOk()) {
    [$userData, $userPerms, $userSettings] = $combined->getOrNull();
    echo "User: " . $userData['name'] . ", Permissions: " . implode(', ', $userPerms) . "\n";
}

// map2 example - adding two successful results
$sum = map2(
    fn($a, $b) => $a + $b,
    Result::ok(10),
    Result::ok(5)
);
echo "Sum: " . $sum->getOrNull() . "\n";

// Working with arrays of results
echo "\n=== Array Operations ===\n";

$numbers = [1, 2, 3, 4, 5];
$doubled = traverse($numbers, fn($x) => Result::ok($x * 2));
echo "Doubled: " . json_encode($doubled->getOrNull()) . "\n";

// Sequence: convert array of Results to Result of array
$results = [Result::ok(1), Result::ok(2), Result::ok(3)];
$sequenced = sequence($results);
echo "Sequenced: " . json_encode($sequenced->getOrNull()) . "\n";

// With one error
$resultsWithError = [Result::ok(1), Result::err('error'), Result::ok(3)];
$sequencedError = sequence($resultsWithError);
echo "Sequenced with error: " . ($sequencedError->isErr() ? $sequencedError->getErrorOrNull() : 'success') . "\n";

// Retry mechanism
echo "\n=== Retry Mechanism ===\n";

$attempts = 0;
$eventualSuccess = retry(function() use (&$attempts) {
    $attempts++;
    echo "Attempt $attempts\n";
    
    if ($attempts < 3) {
        throw new Exception("Not ready yet (attempt $attempts)");
    }
    
    return "Success after $attempts attempts!";
}, maxAttempts: 5, delayMs: 100);

echo $eventualSuccess->getOrElse('Failed') . "\n";

// Complex example: User validation pipeline
echo "\n=== User Validation Pipeline ===\n";

function validateUser(array $data): Result
{
    return fromNullable($data['email'] ?? null, 'Email is required')
        ->flatMap(fn($email) => validateEmail($email))
        ->flatMap(fn($email) => fromNullable($data['name'] ?? null, 'Name is required')
            ->map(fn($name) => ['email' => $email, 'name' => $name])
        )
        ->flatMap(fn($user) => validateAge($data['age'] ?? null, $user))
        ->onSuccess(fn($user) => echo "Valid user: {$user['name']} ({$user['email']})\n")
        ->onFailure(fn($error) => echo "Validation error: $error\n");
}

function validateEmail(string $email): Result
{
    return filter_var($email, FILTER_VALIDATE_EMAIL)
        ? Result::ok($email)
        : Result::err('Invalid email format');
}

function validateAge(?int $age, array $user): Result
{
    if ($age === null) {
        return Result::err('Age is required');
    }
    
    if ($age < 13) {
        return Result::err('Must be at least 13 years old');
    }
    
    if ($age > 120) {
        return Result::err('Invalid age');
    }
    
    return Result::ok([...$user, 'age' => $age]);
}

// Test the validation
$validUser = ['email' => 'john@example.com', 'name' => 'John Doe', 'age' => 30];
$invalidUser = ['email' => 'invalid-email', 'name' => 'Jane'];

validateUser($validUser);
validateUser($invalidUser);

// Configuration loading example
echo "\n=== Configuration Loading ===\n";

class DatabaseConfig
{
    public function __construct(
        public readonly string $host,
        public readonly string $username,
        public readonly string $password,
        public readonly string $database,
        public readonly int $port = 3306
    ) {}
}

function loadDatabaseConfig(): Result
{
    // Simulating environment variables
    $env = [
        'DB_HOST' => 'localhost',
        'DB_USER' => 'myuser',
        'DB_PASS' => 'mypass',
        'DB_NAME' => 'mydb',
        'DB_PORT' => '3306'
    ];
    
    return combine(
        fromNullable($env['DB_HOST'] ?? null, 'DB_HOST not set'),
        fromNullable($env['DB_USER'] ?? null, 'DB_USER not set'),
        fromNullable($env['DB_PASS'] ?? null, 'DB_PASS not set'),
        fromNullable($env['DB_NAME'] ?? null, 'DB_NAME not set')
    )
    ->map(fn($values) => new DatabaseConfig(
        $values[0], $values[1], $values[2], $values[3],
        isset($env['DB_PORT']) ? (int) $env['DB_PORT'] : 3306
    ));
}

$configResult = loadDatabaseConfig();
if ($configResult->isOk()) {
    $config = $configResult->getOrNull();
    echo "Database config loaded: {$config->host}:{$config->port}\n";
} else {
    echo "Failed to load config: " . $configResult->getErrorOrNull() . "\n";
}

// PhpOption integration examples
echo "\n=== PhpOption Integration ===\n";

// Using lift to apply regular functions to Results
$multiply = fn($x) => $x * 3;
$liftedMultiply = lift($multiply);

$liftedResult = $liftedMultiply(Result::ok(14));
echo "Lifted multiply: " . $liftedResult->getOrNull() . "\n";

// Working with Options and Results together
$maybeValue = new Some(42);
$resultFromOption = fromOption($maybeValue);
$processed = $resultFromOption
    ->map(fn($x) => $x * 2)
    ->filter(fn($x) => $x > 50, 'Value too small')
    ->mapToOption(fn($x) => "Processed: $x");

echo "Option processing result: " . $processed->getOrElse('failed') . "\n";

// Partitioning mixed results
$mixedResults = [
    Result::ok(1),
    Result::err('first error'),
    Result::ok(2),
    Result::err('second error'),
    Result::ok(3)
];

[$successes, $errors] = partition($mixedResults);
echo "Partitioned successes: " . json_encode($successes) . "\n";
echo "Partitioned errors: " . json_encode($errors) . "\n";

// Converting between Result and Option in a pipeline
function processWithOption(int $value): string
{
    return Result::ok($value)
        ->filter(fn($x) => $x > 0, 'Must be positive')
        ->map(fn($x) => $x * 2)
        ->toOption()
        ->filter(fn($x) => $x < 100)
        ->map(fn($x) => "Final result: $x")
        ->getOrElse('Processing failed');
}

echo "Process 25: " . processWithOption(25) . "\n";
echo "Process 60: " . processWithOption(60) . "\n";
echo "Process -5: " . processWithOption(-5) . "\n";