<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Controller;

use App\Domain\Shared\ValueObject\Uuid;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use App\Infrastructure\Security\ApiTokenService;
use App\Presentation\Controller\UploadController;
use App\Tests\Presentation\Controller\Api\InMemoryTestUserProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\UuidV4 as SymfonyUuid;
use TusPhp\Tus\Server as TusServer;

#[CoversClass(UploadController::class)]
class UploadControllerTest extends WebTestCase
{
    public function testUploadHandlerReturnsResponseFromTusServer(): void
    {
        $user = $this->createTestUser();
        $client = $this->authenticateClient($user);

        /** @var TusServer|MockObject $tusServer */
        $tusServer = $this->createMock(TusServer::class);
        $tusServer->expects($this->once())
            ->method('getUploadDir')
            ->willReturn(sys_get_temp_dir()); // sys_get_temp_dir() always exists → mkdir() won't be called

        $tusServer->expects($this->once())
            ->method('setDispatcher');

        $tusServer->expects($this->once())
            ->method('serve')
            ->willReturn(new Response('Tus server response', 200));

        static::getContainer()->set(TusServer::class, $tusServer);

        $client->request('POST', '/api/upload');

        self::assertResponseStatusCodeSame(200);
        self::assertSame('Tus server response', $client->getResponse()->getContent());
    }

    public function testUploadHandlerCreatesUploadDirectoryIfNotExists(): void
    {
        $user = $this->createTestUser();
        $client = $this->authenticateClient($user);

        /** @var TusServer|MockObject $tusServer */
        $tusServer = $this->createMock(TusServer::class);
        $uploadDir = sys_get_temp_dir() . '/tus-upload-test';

        if (is_dir($uploadDir)) {
            rmdir($uploadDir);
        }

        $tusServer->expects($this->exactly(2))
            ->method('getUploadDir')
            ->willReturn($uploadDir);

        $tusServer->expects($this->once())
            ->method('setDispatcher');

        $tusServer->expects($this->once())
            ->method('serve')
            ->willReturn(new Response('Tus server response', 200));

        static::getContainer()->set(TusServer::class, $tusServer);

        $client->request('POST', '/api/upload');

        self::assertResponseStatusCodeSame(200);
        self::assertTrue(is_dir($uploadDir));

        if (is_dir($uploadDir)) {
            rmdir($uploadDir);
        }
    }

    private function createTestUser(): UserEntity
    {
        $user = new UserEntity();
        $user->id = SymfonyUuid::fromString('11111111-1111-4111-8111-111111111111');
        $user->email = 'test@example.com';
        $user->roles = ['ROLE_USER'];
        $user->setPassword('hashed-password');

        return $user;
    }

    private function authenticateClient(UserEntity $user): \Symfony\Bundle\FrameworkBundle\KernelBrowser
    {
        $client = static::createClient();

        static::getContainer()->set(
            'security.user.provider.concrete.app_user_provider',
            new InMemoryTestUserProvider($user),
        );

        /** @var ApiTokenService $tokenService */
        $tokenService = static::getContainer()->get(ApiTokenService::class);
        $token = $tokenService->createToken(
            Uuid::fromString($user->id->toRfc4122()),
            $user->getUserIdentifier(),
        );

        $client->setServerParameter('HTTP_AUTHORIZATION', sprintf('Bearer %s', $token));

        return $client;
    }
}
