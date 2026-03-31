<?php
declare(strict_types=1);

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

    public function runStreaming(array $command, callable $onData): Process
    {
        $process = new Process($command);
        $process->setTimeout(null);

        $process->run(static function (string $type, string $data) use ($onData, $process): void {
            $onData($type, $data, $process);
        });

        return $process;
    }
}

