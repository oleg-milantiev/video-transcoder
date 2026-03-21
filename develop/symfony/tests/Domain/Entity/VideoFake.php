<?php

namespace App\Tests\Domain\Entity;

use App\Domain\Video\Entity\Video;
use App\Domain\Video\ValueObject\FileExtension;
use App\Domain\Video\ValueObject\VideoDates;
use App\Domain\Video\ValueObject\VideoStatus;
use App\Domain\Video\ValueObject\VideoTitle;
use Faker\Factory;
use Symfony\Component\Uid\UuidV4;

class VideoFake extends Video
{
    public function __construct()
    {
        $faker = Factory::create();
        $title = new VideoTitle($faker->sentence(3));
        $extension = new FileExtension($faker->randomElement(['mp4', 'mkv', 'avi', 'mov']));
        $status = VideoStatus::UPLOADED;
        $userId = UuidV4::v4();
        $createdAt = $faker->dateTimeBetween('-1 year', 'now');
        $id = UuidV4::v4();
        parent::__construct(
            $title,
            $extension,
            $status,
            $userId,
            [],
            VideoDates::create(\DateTimeImmutable::createFromMutable($createdAt)),
            $id
        );
    }
}
