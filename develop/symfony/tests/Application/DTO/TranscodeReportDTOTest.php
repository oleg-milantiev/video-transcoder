<?php

declare(strict_types=1);

namespace App\Tests\Application\DTO;

use App\Application\DTO\TranscodeProcessReportDTO;
use App\Application\DTO\TranscodeReportDTO;
use PHPUnit\Framework\TestCase;

class TranscodeReportDTOTest extends TestCase
{
    public function testToArrayKeepsPersistedReportShape(): void
    {
        $dto = new TranscodeReportDTO(
            cancelled: true,
            ffmpeg: [
                'progress' => 'continue',
                'out_time_ms' => '15234000',
            ],
            process: new TranscodeProcessReportDTO(
                runtimeSec: 12.345,
                exitCode: 255,
                exitCodeText: 'Unknown error',
                command: 'ffmpeg -i input.mp4 output.mp4',
                stderrTail: 'stderr-tail',
                stdoutTail: 'stdout-tail',
            ),
        );

        $this->assertSame([
            'cancelled' => true,
            'ffmpeg' => [
                'progress' => 'continue',
                'out_time_ms' => '15234000',
            ],
            'process' => [
                'runtimeSec' => 12.345,
                'exitCode' => 255,
                'exitCodeText' => 'Unknown error',
                'command' => 'ffmpeg -i input.mp4 output.mp4',
                'stderrTail' => 'stderr-tail',
                'stdoutTail' => 'stdout-tail',
            ],
        ], $dto->toArray());
    }

    public function testProcessExitCodeCanBeNull(): void
    {
        $dto = new TranscodeReportDTO(
            cancelled: false,
            ffmpeg: [],
            process: new TranscodeProcessReportDTO(
                runtimeSec: 0.0,
                exitCode: null,
                exitCodeText: '',
                command: '',
                stderrTail: '',
                stdoutTail: '',
            ),
        );

        $this->assertNull($dto->toArray()['process']['exitCode']);
    }
}

