<?php

declare(strict_types=1);

namespace PhpResult;

use PhpOption\Option;
use PhpOption\Some;
use PhpOption\None;
use Throwable;

/**
 * Result represents either a success (Ok) or failure (Err) value
 * Built on top of PhpOption for enhanced functionality
 * 
 * @template T
 * @template E
 */
abstract readonly class Result
{
    /**
     * Create a successful Result
     * 
     * @template U
     * @param U $value
     * @return Ok<U>
     */
    public static function ok(mixed $value): Ok
    {
        return new Ok($value);
    }

    /**
     * Create a failed Result
     * 
     * @template F
     * @param F $error
     * @return Err<F>
     */
    public static function err(mixed $error): Err
    {
        return new Err($error);
    }

    /**
     * Create Result from Option
     * 
     * @template U
     * @param Option<U> $option
     * @param mixed $errorValue Error to use if Option is None
     * @return Result<U, mixed>
     */
    public static function fromOption(Option $option, mixed $errorValue = 'Option was None'): Result
    {
        return $option->isDefined() 
            ? self::ok($option->get())
            : self::err($errorValue);
    }

    /**
     * Create Result from nullable value using Option internally
     * 
     * @template U
     * @param U|null $value
     * @param mixed $errorValue Error to use if value is null
     * @return Result<U, mixed>
     */
    public static function fromNullable(mixed $value, mixed $errorValue = 'Value is null'): Result
    {
        return self::fromOption(Option::fromValue($value), $errorValue);
    }

    /**
     * Check if this Result is Ok
     */
    abstract public function isOk(): bool;

    /**
     * Check if this Result is Err
     */
    abstract public function isErr(): bool;

    /**
     * Convert to Option containing the success value
     * 
     * @return Option<T>
     */
    abstract public function toOption(): Option;

    /**
     * Convert to Option containing the error value
     * 
     * @return Option<E>
     */
    abstract public function toErrorOption(): Option;

    /**
     * Get the value if Ok, null otherwise
     * 
     * @return T|null
     */
    abstract public function getOrNull(): mixed;

    /**
     * Get the error if Err, null otherwise
     * 
     * @return E|null
     */
    abstract public function getErrorOrNull(): mixed;

    /**
     * Get the value if Ok, or the default value if Err
     * 
     * @template U
     * @param U $default
     * @return T|U
     */
    abstract public function getOrElse(mixed $default): mixed;

    /**
     * Get the value if Ok, or call the callback if Err
     * 
     * @template U
     * @param callable(): U $callback
     * @return T|U
     */
    abstract public function getOrElseGet(callable $callback): mixed;

    /**
     * Get the value if Ok, or throw if Err
     * 
     * @return T
     * @throws Throwable
     */
    abstract public function getOrThrow(): mixed;

    /**
     * Transform the value using the given function
     * 
     * @template U
     * @param callable(T): U $transform
     * @return Result<U, E>
     */
    abstract public function map(callable $transform): Result;

    /**
     * Transform the error using the given function
     * 
     * @template F
     * @param callable(E): F $transform
     * @return Result<T, F>
     */
    abstract public function mapError(callable $transform): Result;

    /**
     * Chain Results together
     * 
     * @template U
     * @param callable(T): Result<U, E> $transform
     * @return Result<U, E>
     */
    abstract public function flatMap(callable $transform): Result;

    /**
     * Filter the value using a predicate
     * 
     * @param callable(T): bool $predicate
     * @param mixed $errorValue Value to use as error if predicate fails
     * @return Result<T, E|mixed>
     */
    abstract public function filter(callable $predicate, mixed $errorValue = 'Filter predicate failed'): Result;

    /**
     * Execute a callback if this is Ok
     * 
     * @param callable(T): void $callback
     * @return Result<T, E>
     */
    abstract public function onSuccess(callable $callback): Result;

    /**
     * Execute a callback if this is Err
     * 
     * @param callable(E): void $callback
     * @return Result<T, E>
     */
    abstract public function onFailure(callable $callback): Result;

    /**
     * Recover from an error using a function
     * 
     * @param callable(E): T $recovery
     * @return Result<T, E>
     */
    abstract public function recover(callable $recovery): Result;

    /**
     * Recover from an error using a function that returns a Result
     * 
     * @param callable(E): Result<T, E> $recovery
     * @return Result<T, E>
     */
    abstract public function recoverWith(callable $recovery): Result;

    /**
     * Fold the Result into a single value
     * 
     * @template U
     * @param callable(E): U $onError
     * @param callable(T): U $onSuccess
     * @return U
     */
    abstract public function fold(callable $onError, callable $onSuccess): mixed;

    /**
     * Apply a function if Ok, using Option's map functionality
     * 
     * @template U
     * @param callable(T): U $transform
     * @return Option<U>
     */
    public function mapToOption(callable $transform): Option
    {
        return $this->toOption()->map($transform);
    }

    /**
     * FlatMap with Option result
     * 
     * @template U
     * @param callable(T): Option<U> $transform
     * @return Option<U>
     */
    public function flatMapToOption(callable $transform): Option
    {
        return $this->toOption()->flatMap($transform);
    }

    /**
     * Filter using Option's filter
     * 
     * @param callable(T): bool $predicate
     * @return Option<T>
     */
    public function filterToOption(callable $predicate): Option
    {
        return $this->toOption()->filter($predicate);
    }
}