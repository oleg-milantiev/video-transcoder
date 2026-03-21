<?php

namespace App\Infrastructure\Ffmpeg;

use Symfony\Component\Process\Process;

interface ProcessRunnerInterface
{
    /**
     * @param list<string> $command
     */
    public function mustRun(array $command): void;

    /**
     * @param list<string> $command
     */
    public function mustRunAndGetOutput(array $command): string;

    /**
     * @param list<string> $command
     * @param callable(string, string, Process): void $onData
     */
    public function runStreaming(array $command, callable $onData): Process;
}

