<?php

declare(strict_types=1);

namespace PhpResult\Tests;

use PHPUnit\Framework\TestCase;
use PhpResult\Result;
use PhpOption\Option;
use PhpOption\Some;
use PhpOption\None;
use Exception;
use RuntimeException;

use function PhpResult\runCatching;
use function PhpResult\runCatchingWith;
use function PhpResult\combine;
use function PhpResult\zip;
use function PhpResult\map2;
use function PhpResult\map3;
use function PhpResult\fold;
use function PhpResult\sequence;
use function PhpResult\traverse;
use function PhpResult\firstOk;
use function PhpResult\fromNullable;
use function PhpResult\fromOption;
use function PhpResult\fromBoolean;
use function PhpResult\retry;
use function PhpResult\lift;
use function PhpResult\lift2;
use function PhpResult\allOk;
use function PhpResult\anyOk;
use function PhpResult\partition;

class FunctionsTest extends TestCase
{
    public function testRunCatching(): void
    {
        $success = runCatching(fn() => 42);
        $this->assertTrue($success->isOk());
        $this->assertEquals(42, $success->getOrNull());
        
        $failure = runCatching(fn() => throw new Exception('test error'));
        $this->assertTrue($failure->isErr());
        $this->assertInstanceOf(Exception::class, $failure->getErrorOrNull());
        $this->assertEquals('test error', $failure->getErrorOrNull()->getMessage());
    }

    public function testRunCatchingWith(): void
    {
        $success = runCatchingWith(fn($x) => $x * 2, 21);
        $this->assertTrue($success->isOk());
        $this->assertEquals(42, $success->getOrNull());
        
        $failure = runCatchingWith(fn($x) => throw new Exception('test error'), 'arg');
        $this->assertTrue($failure->isErr());
        $this->assertInstanceOf(Exception::class, $failure->getErrorOrNull());
    }

    public function testCombine(): void
    {
        $result1 = Result::ok(1);
        $result2 = Result::ok(2);
        $result3 = Result::ok(3);
        
        $combined = combine($result1, $result2, $result3);
        $this->assertTrue($combined->isOk());
        $this->assertEquals([1, 2, 3], $combined->getOrNull());
        
        $withError = combine($result1, Result::err('error'), $result3);
        $this->assertTrue($withError->isErr());
        $this->assertEquals('error', $withError->getErrorOrNull());
    }

    public function testZip(): void
    {
        $result1 = Result::ok(1);
        $result2 = Result::ok(2);
        
        $zipped = zip($result1, $result2);
        $this->assertTrue($zipped->isOk());
        $this->assertEquals([1, 2], $zipped->getOrNull());
    }

    public function testMap2(): void
    {
        $result1 = Result::ok(10);
        $result2 = Result::ok(5);
        
        $mapped = map2(fn($a, $b) => $a + $b, $result1, $result2);
        $this->assertTrue($mapped->isOk());
        $this->assertEquals(15, $mapped->getOrNull());
        
        $withError = map2(fn($a, $b) => $a + $b, $result1, Result::err('error'));
        $this->assertTrue($withError->isErr());
        $this->assertEquals('error', $withError->getErrorOrNull());
    }

    public function testMap3(): void
    {
        $result1 = Result::ok(10);
        $result2 = Result::ok(5);
        $result3 = Result::ok(2);
        
        $mapped = map3(fn($a, $b, $c) => $a + $b + $c, $result1, $result2, $result3);
        $this->assertTrue($mapped->isOk());
        $this->assertEquals(17, $mapped->getOrNull());
    }

    public function testFold(): void
    {
        $results = [Result::ok(1), Result::ok(2), Result::ok(3)];
        
        $folded = fold($results, 0, fn($acc, $value) => $acc + $value);
        $this->assertTrue($folded->isOk());
        $this->assertEquals(6, $folded->getOrNull());
        
        $withError = [Result::ok(1), Result::err('error'), Result::ok(3)];
        $foldedError = fold($withError, 0, fn($acc, $value) => $acc + $value);
        $this->assertTrue($foldedError->isErr());
        $this->assertEquals('error', $foldedError->getErrorOrNull());
    }

    public function testSequence(): void
    {
        $results = [Result::ok(1), Result::ok(2), Result::ok(3)];
        
        $sequenced = sequence($results);
        $this->assertTrue($sequenced->isOk());
        $this->assertEquals([1, 2, 3], $sequenced->getOrNull());
        
        $withError = [Result::ok(1), Result::err('error'), Result::ok(3)];
        $sequencedError = sequence($withError);
        $this->assertTrue($sequencedError->isErr());
        $this->assertEquals('error', $sequencedError->getErrorOrNull());
    }

    public function testTraverse(): void
    {
        $items = [1, 2, 3];
        
        $traversed = traverse($items, fn($x) => Result::ok($x * 2));
        $this->assertTrue($traversed->isOk());
        $this->assertEquals([2, 4, 6], $traversed->getOrNull());
        
        $traversedWithError = traverse($items, fn($x) => $x === 2 ? Result::err('error') : Result::ok($x * 2));
        $this->assertTrue($traversedWithError->isErr());
        $this->assertEquals('error', $traversedWithError->getErrorOrNull());
    }

    public function testFirstOk(): void
    {
        $results = [Result::err('error1'), Result::ok(42), Result::err('error2')];
        
        $first = firstOk($results);
        $this->assertTrue($first->isOk());
        $this->assertEquals(42, $first->getOrNull());
        
        $allErrors = [Result::err('error1'), Result::err('error2')];
        $firstError = firstOk($allErrors, 'all failed');
        $this->assertTrue($firstError->isErr());
        $this->assertEquals('all failed', $firstError->getErrorOrNull());
    }

    public function testFromNullable(): void
    {
        $fromValue = fromNullable(42);
        $this->assertTrue($fromValue->isOk());
        $this->assertEquals(42, $fromValue->getOrNull());
        
        $fromNull = fromNullable(null, 'was null');
        $this->assertTrue($fromNull->isErr());
        $this->assertEquals('was null', $fromNull->getErrorOrNull());
    }

    public function testFromOption(): void
    {
        $someOption = new Some(42);
        $result = fromOption($someOption);
        $this->assertTrue($result->isOk());
        $this->assertEquals(42, $result->getOrNull());
        
        $noneOption = None::create();
        $errorResult = fromOption($noneOption, 'option was none');
        $this->assertTrue($errorResult->isErr());
        $this->assertEquals('option was none', $errorResult->getErrorOrNull());
    }

    public function testFromBoolean(): void
    {
        $fromTrue = fromBoolean(true, 'success');
        $this->assertTrue($fromTrue->isOk());
        $this->assertEquals('success', $fromTrue->getOrNull());
        
        $fromFalse = fromBoolean(false, 'success', 'failed');
        $this->assertTrue($fromFalse->isErr());
        $this->assertEquals('failed', $fromFalse->getErrorOrNull());
    }

    public function testRetry(): void
    {
        $attempts = 0;
        
        $eventualSuccess = retry(function() use (&$attempts) {
            $attempts++;
            if ($attempts < 3) {
                throw new Exception('not yet');
            }
            return 'success';
        }, 5);
        
        $this->assertTrue($eventualSuccess->isOk());
        $this->assertEquals('success', $eventualSuccess->getOrNull());
        $this->assertEquals(3, $attempts);
        
        $alwaysFails = retry(fn() => throw new Exception('always fails'), 2);
        $this->assertTrue($alwaysFails->isErr());
        $this->assertInstanceOf(Exception::class, $alwaysFails->getErrorOrNull());
        $this->assertEquals('always fails', $alwaysFails->getErrorOrNull()->getMessage());
    }

    public function testRetryWithDelay(): void
    {
        $start = microtime(true);
        $attempts = 0;
        
        retry(function() use (&$attempts) {
            $attempts++;
            throw new Exception('fail');
        }, 3, 10); // 10ms delay
        
        $duration = (microtime(true) - $start) * 1000; // Convert to milliseconds
        
        $this->assertEquals(3, $attempts);
        $this->assertGreaterThan(15, $duration); // Should have at least 2 delays of 10ms
    }

    public function testLift(): void
    {
        $multiply = fn($x) => $x * 2;
        $liftedMultiply = lift($multiply);
        
        $result = $liftedMultiply(Result::ok(21));
        $this->assertTrue($result->isOk());
        $this->assertEquals(42, $result->getOrNull());
        
        $errorResult = $liftedMultiply(Result::err('error'));
        $this->assertTrue($errorResult->isErr());
        $this->assertEquals('error', $errorResult->getErrorOrNull());
    }

    public function testLift2(): void
    {
        $add = fn($a, $b) => $a + $b;
        $liftedAdd = lift2($add);
        
        $result = $liftedAdd(Result::ok(10), Result::ok(5));
        $this->assertTrue($result->isOk());
        $this->assertEquals(15, $result->getOrNull());
        
        $errorResult = $liftedAdd(Result::ok(10), Result::err('error'));
        $this->assertTrue($errorResult->isErr());
        $this->assertEquals('error', $errorResult->getErrorOrNull());
    }

    public function testAllOk(): void
    {
        $allSuccessful = [Result::ok(1), Result::ok(2), Result::ok(3)];
        $this->assertTrue(allOk($allSuccessful));
        
        $withError = [Result::ok(1), Result::err('error'), Result::ok(3)];
        $this->assertFalse(allOk($withError));
        
        $withPredicate = [Result::ok(2), Result::ok(4), Result::ok(6)];
        $this->assertTrue(allOk($withPredicate, fn($x) => $x % 2 === 0));
        
        $failsPredicate = [Result::ok(2), Result::ok(3), Result::ok(6)];
        $this->assertFalse(allOk($failsPredicate, fn($x) => $x % 2 === 0));
    }

    public function testAnyOk(): void
    {
        $someSuccessful = [Result::err('error1'), Result::ok(42), Result::err('error2')];
        $this->assertTrue(anyOk($someSuccessful));
        
        $allErrors = [Result::err('error1'), Result::err('error2')];
        $this->assertFalse(anyOk($allErrors));
        
        $withPredicate = [Result::ok(1), Result::ok(4), Result::ok(5)];
        $this->assertTrue(anyOk($withPredicate, fn($x) => $x % 2 === 0));
        
        $failsPredicate = [Result::ok(1), Result::ok(3), Result::ok(5)];
        $this->assertFalse(anyOk($failsPredicate, fn($x) => $x % 2 === 0));
    }

    public function testPartition(): void
    {
        $mixed = [Result::ok(1), Result::err('error1'), Result::ok(2), Result::err('error2'), Result::ok(3)];
        [$oks, $errs] = partition($mixed);
        
        $this->assertEquals([1, 2, 3], $oks);
        $this->assertEquals(['error1', 'error2'], $errs);
        
        $allOks = [Result::ok(1), Result::ok(2), Result::ok(3)];
        [$allOksValues, $noErrs] = partition($allOks);
        
        $this->assertEquals([1, 2, 3], $allOksValues);
        $this->assertEquals([], $noErrs);
        
        $allErrs = [Result::err('error1'), Result::err('error2')];
        [$noOks, $allErrsValues] = partition($allErrs);
        
        $this->assertEquals([], $noOks);
        $this->assertEquals(['error1', 'error2'], $allErrsValues);
    }
}