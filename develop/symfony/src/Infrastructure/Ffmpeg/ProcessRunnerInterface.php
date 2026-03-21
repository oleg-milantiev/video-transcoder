<?php

namespace App\Infrastructure\Ffmpeg;

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
}

