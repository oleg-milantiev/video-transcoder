<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Admin\EventListener;

use App\Application\Logging\LogServiceInterface;
use App\Infrastructure\Admin\EventListener\AdminCrudAuditListener;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityUpdatedEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV4;

final class AdminCrudAuditListenerTest extends TestCase
{
    public function testLogsAdminAndEntityViewsForCrudUpdate(): void
    {
        $user = new UserEntity();
        $user->id = UuidV4::fromString('11111111-1111-4111-8111-111111111111');
        $user->email = 'admin@example.com';
        $user->roles = ['ROLE_ADMIN'];

        $entity = new AuditTestEntity(UuidV4::fromString('22222222-2222-4222-8222-222222222222'));

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
            ->willReturnCallback(static function (string $name, Uuid $objectId, string $level, string $text, array $context) use (&$calls): void {
                $calls[] = [$name, $objectId->toRfc4122(), $level, $text, $context];
            });

        $listener = new AdminCrudAuditListener($logService, $security, $requestStack);
        $listener->onEntityUpdated(new AfterEntityUpdatedEvent($entity));

        self::assertSame('admin', $calls[0][0]);
        self::assertSame('11111111-1111-4111-8111-111111111111', $calls[0][1]);
        self::assertSame('audittest', $calls[1][0]);
        self::assertSame('22222222-2222-4222-8222-222222222222', $calls[1][1]);
        self::assertSame('Admin updated AuditTestEntity', $calls[0][3]);
        self::assertSame('updated', $calls[0][4]['action']);
        self::assertSame('admin', $calls[0][4]['route']);
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
        $listener->onEntityUpdated(new AfterEntityUpdatedEvent(new AuditTestEntity(UuidV4::fromString('33333333-3333-4333-8333-333333333333'))));
    }
}

final class AuditTestEntity
{
    public function __construct(public UuidV4 $id)
    {
    }
}


