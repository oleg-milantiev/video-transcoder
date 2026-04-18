<?php

namespace App\Tests\Domain\Entity;

use App\Domain\Video\Entity\Preset;
use App\Domain\Video\ValueObject\AudioCodec;
use App\Domain\Video\ValueObject\Bitrate;
use App\Domain\Video\ValueObject\Format;
use App\Domain\Video\ValueObject\PresetTitle;
use App\Domain\Video\ValueObject\Resolution;
use App\Domain\Video\ValueObject\VideoCodec;
use Faker\Factory;
use App\Domain\Shared\ValueObject\Uuid;

class PresetFake extends Preset
{
    public function __construct()
    {
        $faker = Factory::create();
        $title = new PresetTitle($faker->sentence(3));
        $resolution = new Resolution($faker->numberBetween(240, 2160), $faker->numberBetween(240, 2160));
        $videoCodec = new VideoCodec($faker->randomElement(['h264', 'h265', 'vp9', 'av1']));
        $bitrate = new Bitrate($faker->randomFloat(2, 10, 180));
        $audioCodec = new AudioCodec($faker->randomElement(['aac', 'opus']));
        $format = new Format($faker->randomElement(['mp4', 'webm']));
        $id = Uuid::generate();
        parent::__construct($title, $resolution, $videoCodec, $bitrate, $audioCodec, $format, $id);
    }
}
