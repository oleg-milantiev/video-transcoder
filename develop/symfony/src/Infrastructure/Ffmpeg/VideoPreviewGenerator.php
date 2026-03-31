<?php
declare(strict_types=1);

namespace App\Infrastructure\Ffmpeg;

final readonly class VideoPreviewGenerator
{
    public function __construct(
        private ProcessRunnerInterface $processRunner,
    ) {
    }

    public function generate(string $inputPath, string $outputPath, float $time): void
    {
        $this->processRunner->mustRun(self::buildCommand($inputPath, $outputPath, $time));
    }

    /**
     * @return list<string>
     */
    public static function buildCommand(string $inputPath, string $outputPath, float $time): array
    {
        return [
            'ffmpeg',
            '-y',
            '-ss', (string) $time,
            '-i', $inputPath,
            '-frames:v', '1',
            '-q:v', '2',
            $outputPath,
        ];
    }
}

