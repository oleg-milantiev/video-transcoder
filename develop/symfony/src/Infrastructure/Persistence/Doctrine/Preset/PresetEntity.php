<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Preset;

use App\Infrastructure\Persistence\Doctrine\Task\TaskEntity;
use App\Infrastructure\Persistence\Doctrine\User\TariffEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\UuidV4 as SymfonyUuid;

#[ORM\Entity(repositoryClass: PresetRepository::class)]
#[ORM\Table(name: 'preset')]
class PresetEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    public ?SymfonyUuid $id = null;

    #[ORM\Column(length: 50)]
    public string $videoCodec;

    #[ORM\Column(length: 50)]
    public string $audioCodec;

    #[ORM\Column(length: 10)]
    public string $format;

    #[ORM\Column(type: 'json', nullable: true, options: ['jsonb' => true])]
    public ?array $log = null;

    /** @var Collection<int, TaskEntity> */
    #[ORM\OneToMany(targetEntity: TaskEntity::class, mappedBy: 'preset', cascade: ['remove'])]
    public Collection $tasks;

    /** @var Collection<int, TariffEntity> */
    #[ORM\ManyToMany(targetEntity: TariffEntity::class, mappedBy: 'presets')]
    public Collection $tariffs;

    public function __construct()
    {
        $this->tasks = new ArrayCollection();
        $this->tariffs = new ArrayCollection();
    }

    public function __toString(): string
    {
        return sprintf('%s/%s/%s', $this->videoCodec, $this->audioCodec, $this->format);
    }
}
