<?php

namespace Wems\LoopRunner;

use React\EventLoop\LoopInterface;

class LoopedProcessRunner
{
    /** @var LoopInterface */
    private $loop;

    /** @var callable */
    private $process;

    /** @var int */
    private $elapsedTime = 0;

    /** @var int */
    private $startTime;

    /** @var int|null */
    private $timeLimit;

    /** @var int */
    private $interval = 1;

    /**
     * @param LoopInterface $loop
     * @param callable      $process function that returns a boolean to decide whether to go again immediately
     */
    public function __construct(LoopInterface $loop, callable $process)
    {
        $this->loop = $loop;
        $this->process = $process;
    }

    /**
     * @param callable $process function that returns a boolean to decide whether to go again immediately
     *
     * @return self
     */
    public function setProcess(callable $process): self
    {
        $this->process = $process;

        return $this;
    }

    /**
     * @param int $timeLimit in seconds
     *
     * @return self
     */
    public function setTimeLimit(int $timeLimit): self
    {
        $this->timeLimit = $timeLimit;

        return $this;
    }

    /**
     * @param int $interval seconds to wait between runs that didn't process anything
     *
     * @return self
     */
    public function setInterval(int $interval): self
    {
        $this->interval = $interval;

        return $this;
    }

    /**
     * run the callable process repeatedly, every second unless it returns true in which case more than once per second
     * in the context of pulling from a Redis queue, we return true if we processed an item because that suggests that
     * there may be more items to process, so we should try again immediately instead of waiting for the next call
     * this should allow us to process many items very quickly whilst retaining low overheads
     * it will keep the loop active until the given time limit, and then expire
     */
    public function run()
    {
        $this->startTime = time();
        $this->elapsedTime = 0;

        $this->loop->addPeriodicTimer($this->interval, function () {
            $process = $this->process;

            do {
                $didSomethingThisRun = $process();
                $this->updateElapsedTime();
            } while ($didSomethingThisRun && $this->notExceededTimeLimit());

            if ($this->hasExceededTimeLimit()) {
                $this->loop->stop();
            }
        });

        $this->loop->run();
    }

    private function updateElapsedTime()
    {
        $this->elapsedTime = time() - $this->startTime;
    }

    private function hasExceededTimeLimit(): bool
    {
        // if there is no time limit then we have nothing to exceed
        if (is_null($this->timeLimit)) {
            return false;
        }

        return $this->elapsedTime >= $this->timeLimit;
    }

    private function notExceededTimeLimit(): bool
    {
        return !$this->hasExceededTimeLimit();
    }
}
