<?php

namespace App\Infrastructure\Logging;

use App\Application\Logging\LogServiceInterface;
use App\Infrastructure\Persistence\Doctrine\Log\LogEntity;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineLogService implements LogServiceInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    public function log(string $name, Uuid $objectId, string $level, string $text, array $context = []): void
    {
        $this->logger->log($level, $text, $context);

        $entity = new LogEntity();
        $entity->name = $name;
        $entity->objectId = $objectId;
        $entity->level = $level;
        $entity->text = $text;
        $entity->context = $context;

        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }
}
