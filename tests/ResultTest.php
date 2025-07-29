<?php

declare(strict_types=1);

namespace PhpResult\Tests;

use PHPUnit\Framework\TestCase;
use PhpResult\Result;
use PhpResult\Ok;
use PhpResult\Err;
use PhpOption\Option;
use PhpOption\Some;
use PhpOption\None;
use RuntimeException;
use Exception;

class ResultTest extends TestCase
{
    public function testOkCreation(): void
    {
        $result = Result::ok(42);
        
        $this->assertInstanceOf(Ok::class, $result);
        $this->assertTrue($result->isOk());
        $this->assertFalse($result->isErr());
        $this->assertEquals(42, $result->getOrNull());
        $this->assertNull($result->getErrorOrNull());
    }

    public function testErrCreation(): void
    {
        $result = Result::err('error');
        
        $this->assertInstanceOf(Err::class, $result);
        $this->assertFalse($result->isOk());
        $this->assertTrue($result->isErr());
        $this->assertNull($result->getOrNull());
        $this->assertEquals('error', $result->getErrorOrNull());
    }

    public function testFromOption(): void
    {
        $someOption = new Some(42);
        $result = Result::fromOption($someOption);
        
        $this->assertTrue($result->isOk());
        $this->assertEquals(42, $result->getOrNull());
        
        $noneOption = None::create();
        $errorResult = Result::fromOption($noneOption, 'option was none');
        
        $this->assertTrue($errorResult->isErr());
        $this->assertEquals('option was none', $errorResult->getErrorOrNull());
    }

    public function testFromNullable(): void
    {
        $result = Result::fromNullable(42);
        $this->assertTrue($result->isOk());
        $this->assertEquals(42, $result->getOrNull());
        
        $nullResult = Result::fromNullable(null, 'was null');
        $this->assertTrue($nullResult->isErr());
        $this->assertEquals('was null', $nullResult->getErrorOrNull());
    }

    public function testToOption(): void
    {
        $ok = Result::ok(42);
        $option = $ok->toOption();
        
        $this->assertInstanceOf(Some::class, $option);
        $this->assertTrue($option->isDefined());
        $this->assertEquals(42, $option->get());
        
        $err = Result::err('error');
        $errorOption = $err->toOption();
        
        $this->assertInstanceOf(None::class, $errorOption);
        $this->assertFalse($errorOption->isDefined());
    }

    public function testToErrorOption(): void
    {
        $err = Result::err('error');
        $errorOption = $err->toErrorOption();
        
        $this->assertInstanceOf(Some::class, $errorOption);
        $this->assertTrue($errorOption->isDefined());
        $this->assertEquals('error', $errorOption->get());
        
        $ok = Result::ok(42);
        $okErrorOption = $ok->toErrorOption();
        
        $this->assertInstanceOf(None::class, $okErrorOption);
        $this->assertFalse($okErrorOption->isDefined());
    }

    public function testGetOrElse(): void
    {
        $ok = Result::ok(42);
        $err = Result::err('error');
        
        $this->assertEquals(42, $ok->getOrElse(99));
        $this->assertEquals(99, $err->getOrElse(99));
    }

    public function testGetOrElseGet(): void
    {
        $ok = Result::ok(42);
        $err = Result::err('error');
        
        $this->assertEquals(42, $ok->getOrElseGet(fn() => 99));
        $this->assertEquals(99, $err->getOrElseGet(fn() => 99));
    }

    public function testGetOrThrow(): void
    {
        $ok = Result::ok(42);
        $this->assertEquals(42, $ok->getOrThrow());
        
        $err = Result::err(new RuntimeException('test error'));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('test error');
        $err->getOrThrow();
    }

    public function testGetOrThrowWithStringError(): void
    {
        $err = Result::err('string error');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Result contains error: string error');
        $err->getOrThrow();
    }

    public function testMap(): void
    {
        $ok = Result::ok(42);
        $mapped = $ok->map(fn($x) => $x * 2);
        
        $this->assertTrue($mapped->isOk());
        $this->assertEquals(84, $mapped->getOrNull());
        
        $err = Result::err('error');
        $mappedErr = $err->map(fn($x) => $x * 2);
        
        $this->assertTrue($mappedErr->isErr());
        $this->assertEquals('error', $mappedErr->getErrorOrNull());
    }

    public function testMapError(): void
    {
        $err = Result::err('error');
        $mapped = $err->mapError(fn($e) => strtoupper($e));
        
        $this->assertTrue($mapped->isErr());
        $this->assertEquals('ERROR', $mapped->getErrorOrNull());
        
        $ok = Result::ok(42);
        $mappedOk = $ok->mapError(fn($e) => strtoupper($e));
        
        $this->assertTrue($mappedOk->isOk());
        $this->assertEquals(42, $mappedOk->getOrNull());
    }

    public function testFlatMap(): void
    {
        $ok = Result::ok(42);
        $flatMapped = $ok->flatMap(fn($x) => Result::ok($x * 2));
        
        $this->assertTrue($flatMapped->isOk());
        $this->assertEquals(84, $flatMapped->getOrNull());
        
        $flatMappedToErr = $ok->flatMap(fn($x) => Result::err('new error'));
        $this->assertTrue($flatMappedToErr->isErr());
        $this->assertEquals('new error', $flatMappedToErr->getErrorOrNull());
        
        $err = Result::err('error');
        $flatMappedErr = $err->flatMap(fn($x) => Result::ok($x * 2));
        
        $this->assertTrue($flatMappedErr->isErr());
        $this->assertEquals('error', $flatMappedErr->getErrorOrNull());
    }

    public function testFilter(): void
    {
        $ok = Result::ok(42);
        $filtered = $ok->filter(fn($x) => $x > 40);
        
        $this->assertTrue($filtered->isOk());
        $this->assertEquals(42, $filtered->getOrNull());
        
        $filteredOut = $ok->filter(fn($x) => $x > 50, 'too small');
        $this->assertTrue($filteredOut->isErr());
        $this->assertEquals('too small', $filteredOut->getErrorOrNull());
        
        $err = Result::err('error');
        $filteredErr = $err->filter(fn($x) => $x > 40);
        
        $this->assertTrue($filteredErr->isErr());
        $this->assertEquals('error', $filteredErr->getErrorOrNull());
    }

    public function testOnSuccess(): void
    {
        $called = false;
        
        $ok = Result::ok(42);
        $result = $ok->onSuccess(function($value) use (&$called) {
            $called = true;
            $this->assertEquals(42, $value);
        });
        
        $this->assertTrue($called);
        $this->assertSame($ok, $result);
        
        $called = false;
        $err = Result::err('error');
        $err->onSuccess(function() use (&$called) {
            $called = true;
        });
        
        $this->assertFalse($called);
    }

    public function testOnFailure(): void
    {
        $called = false;
        
        $err = Result::err('error');
        $result = $err->onFailure(function($error) use (&$called) {
            $called = true;
            $this->assertEquals('error', $error);
        });
        
        $this->assertTrue($called);
        $this->assertSame($err, $result);
        
        $called = false;
        $ok = Result::ok(42);
        $ok->onFailure(function() use (&$called) {
            $called = true;
        });
        
        $this->assertFalse($called);
    }

    public function testRecover(): void
    {
        $err = Result::err('error');
        $recovered = $err->recover(fn($e) => 'recovered');
        
        $this->assertTrue($recovered->isOk());
        $this->assertEquals('recovered', $recovered->getOrNull());
        
        $ok = Result::ok(42);
        $recoveredOk = $ok->recover(fn($e) => 'recovered');
        
        $this->assertTrue($recoveredOk->isOk());
        $this->assertEquals(42, $recoveredOk->getOrNull());
    }

    public function testRecoverWith(): void
    {
        $err = Result::err('error');
        $recovered = $err->recoverWith(fn($e) => Result::ok('recovered'));
        
        $this->assertTrue($recovered->isOk());
        $this->assertEquals('recovered', $recovered->getOrNull());
        
        $recoveredToErr = $err->recoverWith(fn($e) => Result::err('new error'));
        $this->assertTrue($recoveredToErr->isErr());
        $this->assertEquals('new error', $recoveredToErr->getErrorOrNull());
        
        $ok = Result::ok(42);
        $recoveredOk = $ok->recoverWith(fn($e) => Result::ok('recovered'));
        
        $this->assertTrue($recoveredOk->isOk());
        $this->assertEquals(42, $recoveredOk->getOrNull());
    }

    public function testFold(): void
    {
        $ok = Result::ok(42);
        $folded = $ok->fold(
            fn($error) => 'error: ' . $error,
            fn($value) => 'value: ' . $value
        );
        
        $this->assertEquals('value: 42', $folded);
        
        $err = Result::err('test error');
        $foldedErr = $err->fold(
            fn($error) => 'error: ' . $error,
            fn($value) => 'value: ' . $value
        );
        
        $this->assertEquals('error: test error', $foldedErr);
    }

    public function testMapToOption(): void
    {
        $ok = Result::ok(42);
        $option = $ok->mapToOption(fn($x) => $x * 2);
        
        $this->assertInstanceOf(Some::class, $option);
        $this->assertEquals(84, $option->get());
        
        $err = Result::err('error');
        $errorOption = $err->mapToOption(fn($x) => $x * 2);
        
        $this->assertInstanceOf(None::class, $errorOption);
        $this->assertFalse($errorOption->isDefined());
    }

    public function testFlatMapToOption(): void
    {
        $ok = Result::ok(42);
        $option = $ok->flatMapToOption(fn($x) => new Some($x * 2));
        
        $this->assertInstanceOf(Some::class, $option);
        $this->assertEquals(84, $option->get());
        
        $err = Result::err('error');
        $errorOption = $err->flatMapToOption(fn($x) => new Some($x * 2));
        
        $this->assertInstanceOf(None::class, $errorOption);
        $this->assertFalse($errorOption->isDefined());
    }

    public function testFilterToOption(): void
    {
        $ok = Result::ok(42);
        $option = $ok->filterToOption(fn($x) => $x > 40);
        
        $this->assertInstanceOf(Some::class, $option);
        $this->assertEquals(42, $option->get());
        
        $filteredOut = $ok->filterToOption(fn($x) => $x > 50);
        $this->assertInstanceOf(None::class, $filteredOut);
        $this->assertFalse($filteredOut->isDefined());
        
        $err = Result::err('error');
        $errorOption = $err->filterToOption(fn($x) => $x > 40);
        
        $this->assertInstanceOf(None::class, $errorOption);
        $this->assertFalse($errorOption->isDefined());
    }
}