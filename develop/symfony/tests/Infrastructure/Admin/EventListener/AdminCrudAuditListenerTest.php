<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Admin\EventListener;

use App\Application\Logging\LogServiceInterface;
use App\Domain\Shared\ValueObject\Uuid;
use App\Infrastructure\Admin\EventListener\AdminCrudAuditListener;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityUpdatedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityDeletedEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\UuidV4 as SymfonyUuid;

final class AdminCrudAuditListenerTest extends TestCase
{
    public function testLogsAdminAndEntityViewsForCrudUpdate(): void
    {
        $user = new UserEntity();
        $user->id = SymfonyUuid::fromString('11111111-1111-4111-8111-111111111111');
        $user->email = 'admin@example.com';
        $user->roles = ['ROLE_ADMIN'];

        $entity = new AuditTestEntity(SymfonyUuid::fromString('22222222-2222-4222-8222-222222222222'));

        $security = $this->createMock(Security::class);
        $security->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $request = Request::create('/admin');
        $request->attributes->set('_route', 'admin');
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $calls = [];
        $logService = $this->createMock(LogServiceInterface::class);
        $logService->expects($this->exactly(2))
            ->method('log')
            ->willReturnCallback(static function (string $name, string $action, Uuid $objectId, string $level, string $text, array $context) use (&$calls): void {
                $calls[] = [$name, $action, $objectId->toRfc4122(), $level, $text, $context];
            });

        $listener = new AdminCrudAuditListener($logService, $security, $requestStack);
        $listener->onEntityUpdated(new AfterEntityUpdatedEvent($entity));

        self::assertSame('admin', $calls[0][0]);
        self::assertSame('updated', $calls[0][1]);
        self::assertSame('11111111-1111-4111-8111-111111111111', $calls[0][2]);
        self::assertSame('audittest', $calls[1][0]);
        self::assertSame('updated', $calls[1][1]);
        self::assertSame('22222222-2222-4222-8222-222222222222', $calls[1][2]);
        self::assertSame('Admin updated AuditTestEntity', $calls[0][4]);
        self::assertSame('updated', $calls[0][5]['action']);
        self::assertSame('admin', $calls[0][5]['route']);
    }

    public function testSkipsLoggingWhenNoAuthenticatedAdmin(): void
    {
        $security = $this->createMock(Security::class);
        $security->expects($this->once())
            ->method('getUser')
            ->willReturn(null);

        $logService = $this->createMock(LogServiceInterface::class);
        $logService->expects($this->never())->method('log');

        $listener = new AdminCrudAuditListener($logService, $security, new RequestStack());
        $listener->onEntityUpdated(new AfterEntityUpdatedEvent(new AuditTestEntity(SymfonyUuid::fromString('33333333-3333-4333-8333-333333333333'))));
    }

    public function testLogsAdminAndEntityViewsForCrudCreate(): void
    {
        $user = new UserEntity();
        $user->id = SymfonyUuid::fromString('11111111-1111-4111-8111-111111111111');
        $user->email = 'admin@example.com';
        $user->roles = ['ROLE_ADMIN'];

        $entity = new AuditTestEntity(SymfonyUuid::fromString('44444444-4444-4444-8444-444444444444'));

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        $calls = [];
        $logService = $this->createMock(LogServiceInterface::class);
        $logService->expects($this->exactly(2))
            ->method('log')
            ->willReturnCallback(static function (string $name, string $action, Uuid $objectId, string $level, string $text, array $context) use (&$calls): void {
                $calls[] = [$name, $action];
            });

        $listener = new AdminCrudAuditListener($logService, $security, new RequestStack());
        $listener->onEntityPersisted(new AfterEntityPersistedEvent($entity));

        self::assertSame('admin', $calls[0][0]);
        self::assertSame('created', $calls[0][1]);
        self::assertSame('audittest', $calls[1][0]);
        self::assertSame('created', $calls[1][1]);
    }

    public function testLogsAdminAndEntityViewsForCrudDelete(): void
    {
        $user = new UserEntity();
        $user->id = SymfonyUuid::fromString('11111111-1111-4111-8111-111111111111');
        $user->email = 'admin@example.com';
        $user->roles = ['ROLE_ADMIN'];

        $entity = new AuditTestEntity(SymfonyUuid::fromString('55555555-5555-4555-8555-555555555555'));

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        $calls = [];
        $logService = $this->createMock(LogServiceInterface::class);
        $logService->expects($this->exactly(2))
            ->method('log')
            ->willReturnCallback(static function (string $name, string $action, Uuid $objectId, string $level, string $text, array $context) use (&$calls): void {
                $calls[] = [$name, $action];
            });

        $listener = new AdminCrudAuditListener($logService, $security, new RequestStack());
        $listener->onEntityDeleted(new AfterEntityDeletedEvent($entity));

        self::assertSame('deleted', $calls[0][1]);
        self::assertSame('deleted', $calls[1][1]);
    }

    public function testLogsOnlyAdminWhenEntityHasNoId(): void
    {
        $user = new UserEntity();
        $user->id = SymfonyUuid::fromString('11111111-1111-4111-8111-111111111111');
        $user->email = 'admin@example.com';
        $user->roles = ['ROLE_ADMIN'];

        $entity = new AuditTestEntityNoId();

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        $calls = [];
        $logService = $this->createMock(LogServiceInterface::class);
        $logService->expects($this->exactly(1))
            ->method('log')
            ->willReturnCallback(static function (string $name, string $action, Uuid $objectId, string $level, string $text, array $context) use (&$calls): void {
                $calls[] = [$name, $action];
            });

        $listener = new AdminCrudAuditListener($logService, $security, new RequestStack());
        $listener->onEntityUpdated(new AfterEntityUpdatedEvent($entity));

        self::assertSame('admin', $calls[0][0]);
        self::assertSame('updated', $calls[0][1]);
    }
}

final class AuditTestEntity
{
    public function __construct(public SymfonyUuid $id)
    {
    }
}

final class AuditTestEntityNoId
{
    public ?SymfonyUuid $id = null;
}
