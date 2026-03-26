<?php

namespace App\Tests\Domain\Entity;

use App\Domain\Video\Entity\Preset;
use App\Domain\Video\ValueObject\PresetTitle;
use App\Domain\Video\ValueObject\Resolution;
use App\Domain\Video\ValueObject\Codec;
use App\Domain\Video\ValueObject\Bitrate;
use Faker\Factory;
use App\Domain\Shared\ValueObject\Uuid;

class PresetFake extends Preset
{
    public function __construct()
    {
        $faker = Factory::create();
        $title = new PresetTitle($faker->sentence(3));
        $resolution = new Resolution($faker->numberBetween(240, 2160), $faker->numberBetween(240, 2160));
        $codec = new Codec($faker->randomElement(['h264', 'h265', 'vp9', 'av1']));
        $bitrate = new Bitrate($faker->randomFloat(2, 10, 180));
        $id = Uuid::generate();
        parent::__construct($title, $resolution, $codec, $bitrate, $id);
    }
}
