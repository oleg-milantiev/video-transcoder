<?php
declare(strict_types=1);

namespace App\Application\DTO;

readonly class TranscodeProcessReportDTO
{
    public function __construct(
        public float $runtimeSec,
        public ?int $exitCode,
        public string $exitCodeText,
        public string $command,
        public string $stderrTail,
        public string $stdoutTail,
    ) {
    }

    /**
     * @return array{runtimeSec: float, exitCode: ?int, exitCodeText: string, command: string, stderrTail: string, stdoutTail: string}
     */
    public function toArray(): array
    {
        return [
            'runtimeSec' => $this->runtimeSec,
            'exitCode' => $this->exitCode,
            'exitCodeText' => $this->exitCodeText,
            'command' => $this->command,
            'stderrTail' => $this->stderrTail,
            'stdoutTail' => $this->stdoutTail,
        ];
    }
}

