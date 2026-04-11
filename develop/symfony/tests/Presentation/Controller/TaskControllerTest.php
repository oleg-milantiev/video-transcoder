<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Controller;

use App\Application\Exception\TaskDownloadAccessDeniedException;
use App\Application\Exception\TaskNotFoundException;
use App\Application\Exception\VideoNotFoundException;
use App\Application\Logging\LogServiceInterface;
use App\Application\QueryHandler\QueryBus;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use App\Tests\Presentation\Controller\Api\InMemoryTestUserProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\UuidV4 as SymfonyUuid;

final class TaskControllerTest extends WebTestCase
{
    private function createAuthenticatedClient(
        string $userId = '00000000-0000-4000-8000-000000000099',
    ): KernelBrowser {
        $client = static::createClient();

        $user = new UserEntity();
        $user->id = SymfonyUuid::fromString($userId);
        $user->email = 'task-controller-user@example.com';
        $user->roles = ['ROLE_USER'];

        static::getContainer()->set(
            'security.user.provider.concrete.app_user_provider',
            new InMemoryTestUserProvider($user),
        );

        $client->loginUser($user);

        return $client;
    }

    public function testGuestIsRedirectedToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/task/11111111-1111-4111-8111-111111111111/download');

        self::assertResponseRedirects('/login');
    }

    public function testDownloadRedirectsToPublicUrlOnSuccess(): void
    {
        $client = $this->createAuthenticatedClient();
        $taskId = '22222222-2222-4222-8222-222222222222';
        $url = 'https://cdn.example.com/output.mp4';

        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects($this->once())
            ->method('query')
            ->willReturn($url);
        static::getContainer()->set(QueryBus::class, $queryBus);

        $client->request('GET', "/task/{$taskId}/download");

        self::assertResponseRedirects($url);
    }

    public function testDownloadReturnsBadRequestForInvalidUuid(): void
    {
        $client = $this->createAuthenticatedClient();

        // Passes route regex [0-9a-fA-F-]{36} but fails UUID v4 validation
        // (3rd group must start with '4', 4th group with '8/9/a/b')
        $client->request('GET', '/task/ffffffff-ffff-ffff-ffff-ffffffffffff/download');

        self::assertResponseStatusCodeSame(400);
    }

    public function testDownloadReturnsNotFoundWhenTaskNotFound(): void
    {
        $client = $this->createAuthenticatedClient();
        $taskId = '33333333-3333-4333-8333-333333333333';

        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects($this->once())
            ->method('query')
            ->willThrowException(new TaskNotFoundException('Task not found'));
        static::getContainer()->set(QueryBus::class, $queryBus);

        $client->request('GET', "/task/{$taskId}/download");

        self::assertResponseStatusCodeSame(404);
    }

    public function testDownloadReturnsNotFoundWhenVideoNotFound(): void
    {
        $client = $this->createAuthenticatedClient();
        $taskId = '44444444-4444-4444-8444-444444444444';

        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects($this->once())
            ->method('query')
            ->willThrowException(new VideoNotFoundException('Video not found'));
        static::getContainer()->set(QueryBus::class, $queryBus);

        $client->request('GET', "/task/{$taskId}/download");

        self::assertResponseStatusCodeSame(404);
    }

    public function testDownloadReturnsForbiddenWhenAccessDenied(): void
    {
        $client = $this->createAuthenticatedClient();
        $taskId = '55555555-5555-4555-8555-555555555555';

        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects($this->once())
            ->method('query')
            ->willThrowException(new TaskDownloadAccessDeniedException('Access denied'));
        static::getContainer()->set(QueryBus::class, $queryBus);

        $client->request('GET', "/task/{$taskId}/download");

        self::assertResponseStatusCodeSame(403);
    }

    public function testDownloadReturnsInternalErrorOnUnexpectedException(): void
    {
        $client = $this->createAuthenticatedClient();
        $taskId = '66666666-6666-4666-8666-666666666666';

        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects($this->once())
            ->method('query')
            ->willThrowException(new \RuntimeException('Unexpected error'));
        static::getContainer()->set(QueryBus::class, $queryBus);

        $logService = $this->createMock(LogServiceInterface::class);
        $logService->expects($this->once())->method('log');
        static::getContainer()->set(LogServiceInterface::class, $logService);

        $client->request('GET', "/task/{$taskId}/download");

        self::assertResponseStatusCodeSame(500);
    }
}
