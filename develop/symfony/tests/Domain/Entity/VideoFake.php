<?php

namespace App\Tests\Domain\Entity;

use App\Domain\Video\Entity\Video;
use App\Domain\Video\ValueObject\FileExtension;
use App\Domain\Video\ValueObject\VideoDates;
use App\Domain\Video\ValueObject\VideoTitle;
use Faker\Factory;
use App\Domain\Shared\ValueObject\Uuid;

final class VideoFake
{
    public static function create(): Video
    {
        $faker = Factory::create();
        $title = new VideoTitle($faker->sentence(3));
        $extension = new FileExtension($faker->randomElement(['mp4', 'mkv', 'avi', 'mov']));
        $userId = Uuid::generate();
        $createdAt = $faker->dateTimeBetween('-1 year', 'now');
        $id = Uuid::generate();
        return Video::reconstitute(
            title: $title,
            extension: $extension,
            userId: $userId,
            meta: [],
            dates: VideoDates::create(\DateTimeImmutable::createFromMutable($createdAt)),
            id: $id,
        );
    }
}
