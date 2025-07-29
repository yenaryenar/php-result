<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use PhpResult\Result;
use PhpOption\Some;
use PhpOption\None;
use function PhpResult\runCatching;
use function PhpResult\fromNullable;
use function PhpResult\fromOption;

// Basic Result creation
echo "=== Basic Result Usage ===\n";

$success = Result::ok(42);
echo "Success value: " . $success->getOrNull() . "\n";
echo "Is success: " . ($success->isOk() ? 'true' : 'false') . "\n";

$failure = Result::err('Something went wrong');
echo "Error value: " . $failure->getErrorOrNull() . "\n";
echo "Is error: " . ($failure->isErr() ? 'true' : 'false') . "\n";

// Chaining operations
echo "\n=== Chaining Operations ===\n";

$result = Result::ok(10)
    ->map(fn($x) => $x * 2)
    ->filter(fn($x) => $x > 15, 'Value too small')
    ->map(fn($x) => "Result: $x");

echo $result->getOrElse('No result') . "\n";

// Error handling chain
$errorResult = Result::ok(5)
    ->filter(fn($x) => $x > 10, 'Value too small')
    ->recover(fn($error) => "Recovered from: $error");

echo $errorResult->getOrNull() . "\n";

// runCatching example
echo "\n=== runCatching ===\n";

$safeResult = runCatching(fn() => json_decode('{"name": "John"}', flags: JSON_THROW_ON_ERROR));
echo "JSON decode result: " . json_encode($safeResult->getOrNull()) . "\n";

$errorResult = runCatching(fn() => json_decode('invalid json', flags: JSON_THROW_ON_ERROR));
echo "JSON error: " . $errorResult->getErrorOrNull()?->getMessage() . "\n";

// Nullable conversion
echo "\n=== Nullable Conversion ===\n";

$maybeValue = $_GET['value'] ?? null;
$valueResult = fromNullable($maybeValue, 'No value provided');
echo "Value result: " . $valueResult->getOrElse('default') . "\n";

// Option conversion
echo "\n=== Option Conversion ===\n";

$someOption = new Some('Hello from Option!');
$optionResult = fromOption($someOption);
echo "From Some: " . $optionResult->getOrNull() . "\n";

$noneOption = None::create();
$noneResult = fromOption($noneOption, 'Option was empty');
echo "From None: " . $noneResult->getErrorOrNull() . "\n";

// Converting Result back to Option
$okResult = Result::ok('Success!');
$successOption = $okResult->toOption();
echo "Result to Option: " . $successOption->getOrElse('empty') . "\n";

// Real-world example: Safe division
echo "\n=== Safe Division ===\n";

function safeDivide(float $a, float $b): Result
{
    return $b !== 0.0 
        ? Result::ok($a / $b)
        : Result::err('Division by zero');
}

$division1 = safeDivide(10, 2);
echo "10 / 2 = " . $division1->getOrElse('error') . "\n";

$division2 = safeDivide(10, 0);
echo "10 / 0 = " . $division2->getOrElse('error') . " (error: " . $division2->getErrorOrNull() . ")\n";