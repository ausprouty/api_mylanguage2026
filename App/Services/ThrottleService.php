<?php

namespace App\Services;

class ThrottleService
{
    private string $lockFile;

    public function __construct(string $key = 'translation_queue')
    {
        $this->lockFile = sys_get_temp_dir() . "/{$key}_last_run.txt";
    }

    public function tooSoon(float $milliseconds = 200.0): bool
    {
        if (!file_exists($this->lockFile)) {
            return false;
        }

        $lastRun = (float)file_get_contents($this->lockFile);
        $elapsed = (microtime(true) - $lastRun) * 1000;

        return $elapsed < $milliseconds;
    }

    public function updateTimestamp(): void
    {
        file_put_contents($this->lockFile, microtime(true));
    }
}