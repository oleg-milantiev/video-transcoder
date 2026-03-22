<?php

namespace App\Infrastructure\Admin\EventListener;

use App\Application\Logging\LogServiceInterface;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityDeletedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityUpdatedEvent;
use Psr\Log\LogLevel;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;

final readonly class AdminCrudAuditListener
{
	public function __construct(
		private LogServiceInterface $logService,
		private Security $security,
		private RequestStack $requestStack,
	) {
	}

	#[AsEventListener(event: AfterEntityPersistedEvent::class)]
	public function onEntityPersisted(AfterEntityPersistedEvent $event): void
	{
		$this->audit('created', $event->getEntityInstance());
	}

	#[AsEventListener(event: AfterEntityUpdatedEvent::class)]
	public function onEntityUpdated(AfterEntityUpdatedEvent $event): void
	{
		$this->audit('updated', $event->getEntityInstance());
	}

	#[AsEventListener(event: AfterEntityDeletedEvent::class)]
	public function onEntityDeleted(AfterEntityDeletedEvent $event): void
	{
		$this->audit('deleted', $event->getEntityInstance());
	}

	private function audit(string $action, object $entity): void
	{
		$actor = $this->security->getUser();
		if (!$actor instanceof UserEntity || $actor->id === null) {
			return;
		}

		$request = $this->requestStack->getCurrentRequest();
		$shortClass = new \ReflectionClass($entity)->getShortName();
		$entityName = strtolower((string) preg_replace('/Entity$/', '', $shortClass));
		$entityId = $this->extractUuidId($entity);

		$context = [
			'action' => $action,
			'entityClass' => $entity::class,
			'entityId' => $entityId?->toRfc4122(),
			'actorUserId' => $actor->id->toRfc4122(),
			'actorEmail' => $actor->email,
			'roles' => implode(', ', $actor->roles),
			'route' => $request?->attributes->get('_route'),
			'path' => $request?->getPathInfo(),
			'ip' => $request?->getClientIp(),
			'userAgent' => $request?->headers->get('User-Agent'),
		];

		$message = sprintf('Admin %s %s', $action, $shortClass);

		$this->logService->log('admin', $actor->id, LogLevel::INFO, $message, $context);

		if ($entityId !== null) {
			$this->logService->log($entityName, $entityId, LogLevel::INFO, $message, $context);
		}
	}

	private function extractUuidId(object $entity): ?Uuid
	{
		if (property_exists($entity, 'id')) {
			$property = new \ReflectionProperty($entity, 'id');
			if ($property->isPublic()) {
				$id = $property->getValue($entity);
				if ($id instanceof Uuid) {
					return $id;
				}
			}
		}

		if (method_exists($entity, 'getId')) {
			$id = $entity->getId();
			if ($id instanceof Uuid) {
				return $id;
			}

			if (is_string($id) && Uuid::isValid($id)) {
				return Uuid::fromString($id);
			}
		}

		return null;
	}
}
