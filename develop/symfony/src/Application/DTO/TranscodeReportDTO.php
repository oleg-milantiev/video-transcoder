<?php

namespace App\Application\DTO;

readonly class TranscodeReportDTO
{
    /**
     * @param array<string, string> $ffmpeg
     */
    public function __construct(
        public bool $cancelled,
        public array $ffmpeg,
        public TranscodeProcessReportDTO $process,
    ) {
    }

    /**
     * @return array{cancelled: bool, ffmpeg: array<string, string>, process: array{runtimeSec: float, exitCode: ?int, exitCodeText: string, command: string, stderrTail: string, stdoutTail: string}}
     */
    public function toArray(): array
    {
        return [
            'cancelled' => $this->cancelled,
            'ffmpeg' => $this->ffmpeg,
            'process' => $this->process->toArray(),
        ];
    }
}

