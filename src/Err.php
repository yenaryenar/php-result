<?php

declare(strict_types=1);

namespace PhpResult;

use PhpOption\Option;
use PhpOption\Some;
use PhpOption\None;
use Throwable;
use RuntimeException;

/**
 * Represents a failed Result
 * Uses PhpOption internally for enhanced functionality
 * 
 * @template E
 * @extends Result<never, E>
 */
readonly class Err extends Result
{
    private Option $errorOption;

    /**
     * @param E $error
     */
    public function __construct(public mixed $error)
    {
        $this->errorOption = new Some($error);
    }

    public function isOk(): bool
    {
        return false;
    }

    public function isErr(): bool
    {
        return true;
    }

    public function toOption(): Option
    {
        return None::create();
    }

    public function toErrorOption(): Option
    {
        return $this->errorOption;
    }

    public function getOrNull(): mixed
    {
        return null;
    }

    public function getErrorOrNull(): mixed
    {
        return $this->errorOption->getOrElse(null);
    }

    public function getOrElse(mixed $default): mixed
    {
        return $default;
    }

    public function getOrElseGet(callable $callback): mixed
    {
        return $callback();
    }

    public function getOrThrow(): mixed
    {
        if ($this->error instanceof Throwable) {
            throw $this->error;
        }
        
        throw new RuntimeException('Result contains error: ' . (string) $this->error);
    }

    public function map(callable $transform): Result
    {
        return $this;
    }

    public function mapError(callable $transform): Result
    {
        return Result::err($this->errorOption->map($transform)->get());
    }

    public function flatMap(callable $transform): Result
    {
        return $this;
    }

    public function filter(callable $predicate, mixed $errorValue = 'Filter predicate failed'): Result
    {
        return $this;
    }

    public function onSuccess(callable $callback): Result
    {
        return $this;
    }

    public function onFailure(callable $callback): Result
    {
        $this->errorOption->map(function($error) use ($callback) {
            $callback($error);
            return $error;
        });
        return $this;
    }

    public function recover(callable $recovery): Result
    {
        return Result::ok($this->errorOption->map($recovery)->get());
    }

    public function recoverWith(callable $recovery): Result
    {
        return $recovery($this->error);
    }

    public function fold(callable $onError, callable $onSuccess): mixed
    {
        return $this->errorOption->map($onError)->get();
    }

    /**
     * Convert Err to Some option containing the error
     * 
     * @return Some<E>
     */
    public function toErrorSome(): Some
    {
        return new Some($this->error);
    }

    /**
     * Transform error and return Some
     * 
     * @template F
     * @param callable(E): F $transform
     * @return Some<F>
     */
    public function mapErrorToSome(callable $transform): Some
    {
        return new Some($transform($this->error));
    }

    /**
     * Try to recover using Option's getOrCall
     * 
     * @template T
     * @param callable(E): T $recovery
     * @return Option<T>
     */
    public function recoverToOption(callable $recovery): Option
    {
        return $this->errorOption->map($recovery);
    }
}