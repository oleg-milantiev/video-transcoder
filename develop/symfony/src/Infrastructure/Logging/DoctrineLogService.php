<?php
declare(strict_types=1);

namespace App\Infrastructure\Logging;

use App\Application\Logging\LogServiceInterface;
use App\Domain\Shared\ValueObject\Uuid;
use App\Infrastructure\Persistence\Doctrine\Log\LogEntity;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\UuidV4 as SymfonyUuid;

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
        $entity->objectId = SymfonyUuid::fromString($objectId->toRfc4122());
        $entity->level = $level;
        $entity->text = $text;
        $entity->context = $context;

        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }
}
