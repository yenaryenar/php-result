<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use PhpResult\Result;
use PhpOption\Option;
use PhpOption\Some;
use PhpOption\None;
use function PhpResult\fromOption;
use function PhpResult\lift;
use function PhpResult\lift2;
use function PhpResult\allOk;
use function PhpResult\anyOk;
use function PhpResult\partition;

echo "=== PhpOption Integration Examples ===\n";

// Converting between Result and Option
echo "\n--- Result <-> Option Conversion ---\n";

$someOption = new Some(42);
$result = Result::fromOption($someOption);
echo "From Some(42): " . $result->getOrNull() . "\n";

$noneOption = None::create();
$errorResult = Result::fromOption($noneOption, 'Option was None');
echo "From None: " . $errorResult->getErrorOrNull() . "\n";

// Converting Result back to Option
$okResult = Result::ok(100);
$option = $okResult->toOption();
echo "Result::ok(100) to Option: " . $option->getOrElse('empty') . "\n";

$errResult = Result::err('failed');
$emptyOption = $errResult->toOption();
echo "Result::err to Option: " . ($emptyOption->isDefined() ? $emptyOption->get() : 'None') . "\n";

// Using Option methods on Result
echo "\n--- Using Option Methods ---\n";

$result = Result::ok(42);
$doubled = $result->mapToOption(fn($x) => $x * 2);
echo "mapToOption result: " . $doubled->getOrElse('empty') . "\n";

$filtered = $result->filterToOption(fn($x) => $x > 40);
echo "filterToOption (x > 40): " . $filtered->getOrElse('filtered out') . "\n";

$filteredOut = $result->filterToOption(fn($x) => $x > 50);
echo "filterToOption (x > 50): " . ($filteredOut->isDefined() ? $filteredOut->get() : 'filtered out') . "\n";

// Lifting functions
echo "\n--- Function Lifting ---\n";

$multiply = fn($x) => $x * 3;
$liftedMultiply = lift($multiply);

$liftedResult = $liftedMultiply(Result::ok(14));
echo "Lifted multiply: " . $liftedResult->getOrNull() . "\n";

$liftedError = $liftedMultiply(Result::err('error'));
echo "Lifted multiply on error: " . $liftedError->getErrorOrNull() . "\n";

// Lifting binary functions
$add = fn($a, $b) => $a + $b;
$liftedAdd = lift2($add);

$addResult = $liftedAdd(Result::ok(10), Result::ok(5));
echo "Lifted add: " . $addResult->getOrNull() . "\n";

// Checking multiple Results
echo "\n--- Result Array Operations ---\n";

$results1 = [Result::ok(1), Result::ok(2), Result::ok(3)];
echo "All Ok (all success): " . (allOk($results1) ? 'true' : 'false') . "\n";

$results2 = [Result::ok(1), Result::err('error'), Result::ok(3)];
echo "All Ok (with error): " . (allOk($results2) ? 'true' : 'false') . "\n";

echo "Any Ok (with error): " . (anyOk($results2) ? 'true' : 'false') . "\n";

$results3 = [Result::err('error1'), Result::err('error2')];
echo "Any Ok (all errors): " . (anyOk($results3) ? 'true' : 'false') . "\n";

// Predicate-based checking
$evenResults = [Result::ok(2), Result::ok(4), Result::ok(6)];
echo "All Ok and even: " . (allOk($evenResults, fn($x) => $x % 2 === 0) ? 'true' : 'false') . "\n";

$mixedResults = [Result::ok(2), Result::ok(3), Result::ok(6)];
echo "All Ok and even (mixed): " . (allOk($mixedResults, fn($x) => $x % 2 === 0) ? 'true' : 'false') . "\n";

// Partitioning Results
echo "\n--- Partitioning Results ---\n";

$mixed = [Result::ok(1), Result::err('error1'), Result::ok(2), Result::err('error2'), Result::ok(3)];
[$oks, $errs] = partition($mixed);

echo "Partitioned Oks: " . json_encode($oks) . "\n";
echo "Partitioned Errs: " . json_encode($errs) . "\n";

// Working with chained operations using Option methods
echo "\n--- Complex Chain with Option Integration ---\n";

function processData(int $value): Result
{
    return Result::fromNullable($value > 0 ? $value : null, 'Invalid value')
        ->map(fn($x) => $x * 2)
        ->filter(fn($x) => $x < 100, 'Value too large')
        ->onSuccess(fn($x) => echo "Processing value: $x\n");
}

$result1 = processData(20);
$option1 = $result1->toOption();
echo "Result 1 to Option: " . $option1->getOrElse('failed') . "\n";

$result2 = processData(60);
$option2 = $result2->toOption();
echo "Result 2 to Option: " . ($option2->isDefined() ? $option2->get() : 'failed - too large') . "\n";

$result3 = processData(-5);
$option3 = $result3->toOption();
echo "Result 3 to Option: " . ($option3->isDefined() ? $option3->get() : 'failed - invalid') . "\n";

// Demonstrating Option flatMap with Result
echo "\n--- Option flatMap with Result ---\n";

function safeParseInt(string $str): Result
{
    $trimmed = trim($str);
    if (!ctype_digit($trimmed)) {
        return Result::err('Not a valid integer');
    }
    return Result::ok((int) $trimmed);
}

$input = new Some("  42  ");
$parsed = $input->flatMap(function($str) {
    $result = safeParseInt($str);
    return $result->toOption();
});

echo "Parsed integer from Option: " . $parsed->getOrElse('failed to parse') . "\n";

$invalidInput = new Some("not-a-number");
$invalidParsed = $invalidInput->flatMap(function($str) {
    $result = safeParseInt($str);
    return $result->toOption();
});

echo "Parsed invalid from Option: " . ($invalidParsed->isDefined() ? $invalidParsed->get() : 'failed to parse') . "\n";