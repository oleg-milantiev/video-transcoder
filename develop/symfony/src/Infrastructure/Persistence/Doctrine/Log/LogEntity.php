<?php

namespace App\Infrastructure\Persistence\Doctrine\Log;

use Doctrine\ORM\Mapping as ORM;
use Psr\Log\LogLevel;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV4;

#[ORM\Entity]
#[ORM\Table(name: 'log')]
class LogEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    public ?UuidV4 $id = null;

    #[ORM\Column(length: 100)]
    public string $name;

    #[ORM\Column(type: 'uuid')]
    public ?Uuid $objectId = null;

    #[ORM\Column(length: 20)]
    public string $level;

    #[ORM\Column(type: 'text')]
    public string $text;

    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'json', options: ['jsonb' => true])]
    public array $context = [];

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->level = LogLevel::INFO;
        $this->name = '';
        $this->text = '';
    }
}
