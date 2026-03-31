<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\User;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\UuidV4 as SymfonyUuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TariffRepository::class)]
#[ORM\Table(name: 'tariff')]
class TariffEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    public ?SymfonyUuid $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 255)]
    public string $title;

    #[ORM\Column]
    #[Assert\GreaterThanOrEqual(0, message: 'Delay must be 0 or more seconds.')]
    public int $delay;

    #[ORM\Column]
    #[Assert\GreaterThanOrEqual(1, message: 'Instance count must be at least 1.')]
    public int $instance;

    #[ORM\Column]
    #[Assert\GreaterThanOrEqual(1, message: 'Video duration must be at least 1 second.')]
    public int $videoDuration;

    #[ORM\Column]
    #[Assert\Positive(message: 'Video size must be greater than 0 megabytes.')]
    public float $videoSize;

    #[ORM\Column]
    #[Assert\GreaterThanOrEqual(1, message: 'Max width must be at least 1 pixel.')]
    public int $maxWidth;

    #[ORM\Column]
    #[Assert\GreaterThanOrEqual(1, message: 'Max height must be at least 1 pixel.')]
    public int $maxHeight;

    #[ORM\Column]
    #[Assert\Positive(message: 'Storage must be greater than 0 gigabytes.')]
    public float $storageGb;

    #[ORM\Column]
    #[Assert\GreaterThanOrEqual(1, message: 'Storage hour must be at least 1 hour.')]
    public int $storageHour;

    public function __toString(): string
    {
        return $this->title;
    }
}
