<?php

namespace App\Tests\Domain\Entity;

use App\Domain\Video\Entity\Preset;
use App\Domain\Video\ValueObject\PresetName;
use App\Domain\Video\ValueObject\Resolution;
use App\Domain\Video\ValueObject\Codec;
use App\Domain\Video\ValueObject\Bitrate;

class PresetFake extends Preset
{
    public function __construct()
    {
        $faker = \Faker\Factory::create();
        $name = new PresetName($faker->sentence(3));
        $resolution = new Resolution($faker->numberBetween(240, 2160), $faker->numberBetween(240, 2160));
        $codec = new Codec($faker->randomElement(['h264', 'h265', 'vp9', 'av1']));
        $bitrate = new Bitrate($faker->randomFloat(2, 10, 180));
        $id = $faker->numberBetween(1, 1000);
        parent::__construct($name, $resolution, $codec, $bitrate, $id);
    }
}
