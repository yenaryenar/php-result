<?php

declare(strict_types=1);

namespace PhpResult;

use PhpOption\Option;
use PhpOption\Some;
use PhpOption\None;
use Throwable;

/**
 * Run a callable and return a Result, catching any thrown exceptions
 * 
 * @template T
 * @param callable(): T $callable
 * @return Result<T, Throwable>
 */
function runCatching(callable $callable): Result
{
    try {
        return Result::ok($callable());
    } catch (Throwable $e) {
        return Result::err($e);
    }
}

/**
 * Run a callable with an argument and return a Result, catching any exceptions
 * 
 * @template T
 * @template U
 * @param callable(T): U $callable
 * @param T $argument
 * @return Result<U, Throwable>
 */
function runCatchingWith(callable $callable, mixed $argument): Result
{
    try {
        return Result::ok($callable($argument));
    } catch (Throwable $e) {
        return Result::err($e);
    }
}

/**
 * Combine multiple Results into a single Result containing an array of values
 * Returns Err if any of the Results is Err
 * Uses Option internally for enhanced processing
 * 
 * @param Result ...$results
 * @return Result<array, mixed>
 */
function combine(Result ...$results): Result
{
    $values = [];
    
    foreach ($results as $result) {
        $option = $result->toOption();
        if ($option->isEmpty()) {
            return $result; // Return the first error
        }
        $values[] = $option->get();
    }
    
    return Result::ok($values);
}

/**
 * Zip multiple Results into a single Result containing an array of values
 * Same as combine() but with a more functional name
 * 
 * @param Result ...$results
 * @return Result<array, mixed>
 */
function zip(Result ...$results): Result
{
    return combine(...$results);
}

/**
 * Apply a function to the values of multiple Results using Option
 * 
 * @template T
 * @template U
 * @template V
 * @param callable(T, U): V $fn
 * @param Result<T, mixed> $result1
 * @param Result<U, mixed> $result2
 * @return Result<V, mixed>
 */
function map2(callable $fn, Result $result1, Result $result2): Result
{
    $option1 = $result1->toOption();
    $option2 = $result2->toOption();
    
    if ($option1->isEmpty()) {
        return $result1;
    }
    if ($option2->isEmpty()) {
        return $result2;
    }
    
    return Result::ok($fn($option1->get(), $option2->get()));
}

/**
 * Apply a function to the values of three Results using Option
 * 
 * @template T
 * @template U
 * @template V
 * @template W
 * @param callable(T, U, V): W $fn
 * @param Result<T, mixed> $result1
 * @param Result<U, mixed> $result2
 * @param Result<V, mixed> $result3
 * @return Result<W, mixed>
 */
function map3(callable $fn, Result $result1, Result $result2, Result $result3): Result
{
    return combine($result1, $result2, $result3)
        ->map(fn($values) => $fn($values[0], $values[1], $values[2]));
}

/**
 * Fold over an array of Results, accumulating values using Option
 * 
 * @template T
 * @template U
 * @param array<Result<T, mixed>> $results
 * @param U $initial
 * @param callable(U, T): U $fn
 * @return Result<U, mixed>
 */
function fold(array $results, mixed $initial, callable $fn): Result
{
    $accumulator = $initial;
    
    foreach ($results as $result) {
        $option = $result->toOption();
        if ($option->isEmpty()) {
            return $result;
        }
        $accumulator = $fn($accumulator, $option->get());
    }
    
    return Result::ok($accumulator);
}

/**
 * Transform an array of Results into a Result of array using Option
 * 
 * @template T
 * @param array<Result<T, mixed>> $results
 * @return Result<array<T>, mixed>
 */
function sequence(array $results): Result
{
    return fold($results, [], fn($acc, $value) => [...$acc, $value]);
}

/**
 * Apply a function to each element and collect the results
 * 
 * @template T
 * @template U
 * @param array<T> $items
 * @param callable(T): Result<U, mixed> $fn
 * @return Result<array<U>, mixed>
 */
function traverse(array $items, callable $fn): Result
{
    $results = array_map($fn, $items);
    return sequence($results);
}

/**
 * Get the first Ok result from an array of Results using Option
 * 
 * @template T
 * @param array<Result<T, mixed>> $results
 * @param mixed $defaultError Error to return if all Results are Err
 * @return Result<T, mixed>
 */
function firstOk(array $results, mixed $defaultError = 'All results failed'): Result
{
    foreach ($results as $result) {
        $option = $result->toOption();
        if ($option->isDefined()) {
            return $result;
        }
    }
    
    return Result::err($defaultError);
}

/**
 * Convert a nullable value to a Result using PhpOption
 * 
 * @template T
 * @param T|null $value
 * @param mixed $errorValue Error to use if value is null
 * @return Result<T, mixed>
 */
function fromNullable(mixed $value, mixed $errorValue = 'Value is null'): Result
{
    return Result::fromNullable($value, $errorValue);
}

/**
 * Convert an Option to a Result
 * 
 * @template T
 * @param Option<T> $option
 * @param mixed $errorValue Error to use if Option is None
 * @return Result<T, mixed>
 */
function fromOption(Option $option, mixed $errorValue = 'Option was None'): Result
{
    return Result::fromOption($option, $errorValue);
}

/**
 * Convert a boolean to a Result
 * 
 * @param bool $condition
 * @param mixed $successValue Value to use if condition is true
 * @param mixed $errorValue Error to use if condition is false
 * @return Result<mixed, mixed>
 */
function fromBoolean(bool $condition, mixed $successValue = true, mixed $errorValue = 'Condition failed'): Result
{
    return $condition ? Result::ok($successValue) : Result::err($errorValue);
}

/**
 * Retry a callable up to a specified number of times using Option for enhanced error handling
 * 
 * @template T
 * @param callable(): T $callable
 * @param int $maxAttempts Maximum number of attempts
 * @param int $delayMs Delay between attempts in milliseconds
 * @return Result<T, Throwable>
 */
function retry(callable $callable, int $maxAttempts = 3, int $delayMs = 0): Result
{
    $lastException = null;
    
    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        $result = runCatching($callable);
        $option = $result->toOption();
        
        if ($option->isDefined()) {
            return $result;
        }
        
        $lastException = $result->getErrorOrNull();
        
        if ($attempt < $maxAttempts && $delayMs > 0) {
            usleep($delayMs * 1000);
        }
    }
    
    return Result::err($lastException);
}

/**
 * Lift a regular function to work with Results using Option
 * 
 * @template T
 * @template U
 * @param callable(T): U $fn
 * @return callable(Result<T, mixed>): Result<U, mixed>
 */
function lift(callable $fn): callable
{
    return fn(Result $result) => $result->map($fn);
}

/**
 * Lift a binary function to work with Results using Option
 * 
 * @template T
 * @template U
 * @template V
 * @param callable(T, U): V $fn
 * @return callable(Result<T, mixed>, Result<U, mixed>): Result<V, mixed>
 */
function lift2(callable $fn): callable
{
    return fn(Result $result1, Result $result2) => map2($fn, $result1, $result2);
}

/**
 * Apply a predicate to all Results and return true if all are Ok and satisfy the predicate
 * 
 * @template T
 * @param array<Result<T, mixed>> $results
 * @param callable(T): bool $predicate
 * @return bool
 */
function allOk(array $results, callable $predicate = null): bool
{
    foreach ($results as $result) {
        $option = $result->toOption();
        if ($option->isEmpty()) {
            return false;
        }
        if ($predicate !== null && !$predicate($option->get())) {
            return false;
        }
    }
    return true;
}

/**
 * Check if any Result is Ok and optionally satisfies a predicate
 * 
 * @template T
 * @param array<Result<T, mixed>> $results
 * @param callable(T): bool $predicate
 * @return bool
 */
function anyOk(array $results, callable $predicate = null): bool
{
    foreach ($results as $result) {
        $option = $result->toOption();
        if ($option->isDefined()) {
            if ($predicate === null || $predicate($option->get())) {
                return true;
            }
        }
    }
    return false;
}

/**
 * Partition Results into Ok and Err arrays using Option
 * 
 * @template T
 * @template E
 * @param array<Result<T, E>> $results
 * @return array{array<T>, array<E>}
 */
function partition(array $results): array
{
    $oks = [];
    $errs = [];
    
    foreach ($results as $result) {
        $option = $result->toOption();
        if ($option->isDefined()) {
            $oks[] = $option->get();
        } else {
            $errorOption = $result->toErrorOption();
            if ($errorOption->isDefined()) {
                $errs[] = $errorOption->get();
            }
        }
    }
    
    return [$oks, $errs];
}