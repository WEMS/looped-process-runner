<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use React\EventLoop\Factory;
use Wems\LoopRunner\LoopedProcessRunner;

class LoopedProcessRunnerTest extends TestCase
{
    private $testVar;

    /** @var callable */
    private $process;

    /** @var LoopedProcessRunner */
    private $runner;

    protected function setUp()
    {
        $this->process = function () {
            $this->testVar = 42;

            return true;
        };

        $this->runner = new LoopedProcessRunner(Factory::create(), $this->process);
    }

    public function testLoopedProcessRuns()
    {
        $this->assertNull($this->testVar);

        $this->runner
            ->setTimeLimit(0)
            ->setInterval(0)
            ->run();

        $this->assertEquals(42, $this->testVar);
    }

    public function testSetProcess()
    {
        $fn = function () {
            return 42;
        };

        $process = new \ReflectionProperty($this->runner, 'process');
        $process->setAccessible(true);

        $this->runner->setProcess($fn);

        $fn2 = $process->getValue($this->runner);
        $this->assertEquals(42, $fn2());
    }

    public function testTimeLimits()
    {
        $timeLimit = new \ReflectionProperty($this->runner, 'timeLimit');
        $timeLimit->setAccessible(true);

        $elapsedTime = new \ReflectionProperty($this->runner, 'elapsedTime');
        $elapsedTime->setAccessible(true);

        $this->assertNull($timeLimit->getValue($this->runner));
        $this->assertEquals(0, $elapsedTime->getValue($this->runner));

        $hasExceededTimeLimit = new \ReflectionMethod($this->runner, 'hasExceededTimeLimit');
        $hasExceededTimeLimit->setAccessible(true);

        $notExceededTimeLimit = new \ReflectionMethod($this->runner, 'notExceededTimeLimit');
        $notExceededTimeLimit->setAccessible(true);

        $this->assertTrue($notExceededTimeLimit->invoke($this->runner));
        $this->assertFalse($hasExceededTimeLimit->invoke($this->runner));

        $this->runner->setTimeLimit(2);
        $this->assertEquals(2, $timeLimit->getValue($this->runner));

        $elapsedTime->setValue($this->runner, 3);

        $this->assertFalse($notExceededTimeLimit->invoke($this->runner));
        $this->assertTrue($hasExceededTimeLimit->invoke($this->runner));
    }
}
