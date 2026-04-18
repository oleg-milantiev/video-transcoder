<?php

namespace App\Tests\Domain\Entity;

use App\Domain\Video\Entity\Preset;
use App\Domain\Video\ValueObject\AudioCodec;
use App\Domain\Video\ValueObject\Format;
use App\Domain\Video\ValueObject\VideoCodec;
use Faker\Factory;
use App\Domain\Shared\ValueObject\Uuid;

class PresetFake extends Preset
{
    public function __construct()
    {
        $faker = Factory::create();
        $videoCodec = new VideoCodec($faker->randomElement(['h264', 'h265', 'vp9', 'av1']));
        $audioCodec = new AudioCodec($faker->randomElement(['aac', 'opus']));
        $format = new Format($faker->randomElement(['mp4', 'webm']));
        $id = Uuid::generate();
        parent::__construct($videoCodec, $audioCodec, $format, $id);
    }
}
