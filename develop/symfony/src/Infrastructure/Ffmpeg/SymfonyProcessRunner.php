<?php

namespace App\Infrastructure\Ffmpeg;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

final readonly class SymfonyProcessRunner implements ProcessRunnerInterface
{
    public function mustRun(array $command): void
    {
        $process = new Process($command);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    public function mustRunAndGetOutput(array $command): string
    {
        $process = new Process($command);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $process->getOutput();
    }
}

