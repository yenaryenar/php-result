<?php

declare(strict_types=1);

namespace PhpResult;

use PhpOption\Option;
use PhpOption\Some;
use PhpOption\None;
use Throwable;

/**
 * Represents a successful Result
 * Uses PhpOption internally for enhanced functionality
 * 
 * @template T
 * @extends Result<T, never>
 */
readonly class Ok extends Result
{
    private Option $valueOption;

    /**
     * @param T $value
     */
    public function __construct(public mixed $value)
    {
        $this->valueOption = new Some($value);
    }

    public function isOk(): bool
    {
        return true;
    }

    public function isErr(): bool
    {
        return false;
    }

    public function toOption(): Option
    {
        return $this->valueOption;
    }

    public function toErrorOption(): Option
    {
        return None::create();
    }

    public function getOrNull(): mixed
    {
        return $this->valueOption->getOrElse(null);
    }

    public function getErrorOrNull(): mixed
    {
        return null;
    }

    public function getOrElse(mixed $default): mixed
    {
        return $this->valueOption->getOrElse($default);
    }

    public function getOrElseGet(callable $callback): mixed
    {
        return $this->valueOption->getOrCall($callback);
    }

    public function getOrThrow(): mixed
    {
        return $this->valueOption->get();
    }

    public function map(callable $transform): Result
    {
        return Result::ok($this->valueOption->map($transform)->get());
    }

    public function mapError(callable $transform): Result
    {
        return $this;
    }

    public function flatMap(callable $transform): Result
    {
        return $transform($this->value);
    }

    public function filter(callable $predicate, mixed $errorValue = 'Filter predicate failed'): Result
    {
        return $this->valueOption->filter($predicate)->isDefined()
            ? $this
            : Result::err($errorValue);
    }

    public function onSuccess(callable $callback): Result
    {
        $this->valueOption->map(function($value) use ($callback) {
            $callback($value);
            return $value;
        });
        return $this;
    }

    public function onFailure(callable $callback): Result
    {
        return $this;
    }

    public function recover(callable $recovery): Result
    {
        return $this;
    }

    public function recoverWith(callable $recovery): Result
    {
        return $this;
    }

    public function fold(callable $onError, callable $onSuccess): mixed
    {
        return $this->valueOption->map($onSuccess)->get();
    }

    /**
     * Convert Ok to Some option
     * 
     * @return Some<T>
     */
    public function toSome(): Some
    {
        return new Some($this->value);
    }

    /**
     * Apply function and return Option
     * 
     * @template U
     * @param callable(T): U $transform
     * @return Some<U>
     */
    public function mapToSome(callable $transform): Some
    {
        return new Some($transform($this->value));
    }
}