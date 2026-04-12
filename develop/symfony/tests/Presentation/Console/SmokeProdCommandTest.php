<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Console;

use App\Application\Exception\QueryException;
use App\Application\Logging\LogServiceInterface;
use App\Application\QueryHandler\QueryBus;
use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\UserEmail;
use App\Domain\User\ValueObject\UserRoles;
use App\Presentation\Console\SmokeProdCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SmokeProdCommandTest extends TestCase
{
    private const string ADMIN_UUID = '123e4567-e89b-42d3-a456-426614174000';

    private function makeAdminUser(): User
    {
        return new User(
            email: new UserEmail('admin@example.com'),
            roles: new UserRoles(['ROLE_ADMIN']),
            id: Uuid::fromString(self::ADMIN_UUID),
        );
    }

    public function testReturnsFailureWhenAdminUserNotFound(): void
    {
        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $userRepository->method('findById')->willReturn(null);

        $command = new SmokeProdCommand(
            $this->createStub(QueryBus::class),
            $userRepository,
            $this->createStub(LogServiceInterface::class),
        );

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Admin user not found', $tester->getDisplay());
    }

    public function testReturnsSuccessWhenAllTestsPass(): void
    {
        $videoListResult = (object) [
            'items' => [
                (object) ['uuid' => '11111111-1111-4111-8111-111111111111', 'title' => 'Test Video', 'createdAt' => '2026-01-01'],
            ],
            'total' => 1,
            'totalPages' => 1,
        ];

        $taskListResult = (object) [
            'items' => [],
            'total' => 0,
        ];

        $queryBus = $this->createStub(QueryBus::class);
        $queryBus->method('query')->willReturnOnConsecutiveCalls(
            $videoListResult, // testVideoList
            $videoListResult, // testVideoItemsStructure
            $taskListResult,  // testTaskList
        );

        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $userRepository->method('findById')->willReturn($this->makeAdminUser());

        $logService = $this->createMock(LogServiceInterface::class);
        $logService->expects($this->once())->method('log');

        $command = new SmokeProdCommand($queryBus, $userRepository, $logService);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Passed:', $tester->getDisplay());
    }

    public function testReturnsFailureWhenVideoListQueryFails(): void
    {
        $queryBus = $this->createStub(QueryBus::class);
        $queryBus->method('query')->willThrowException(new QueryException('DB unavailable'));

        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $userRepository->method('findById')->willReturn($this->makeAdminUser());

        $command = new SmokeProdCommand(
            $queryBus,
            $userRepository,
            $this->createStub(LogServiceInterface::class),
        );

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Failed:', $tester->getDisplay());
    }

    public function testReturnsFailureOnUnexpectedException(): void
    {
        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $userRepository->method('findById')->willThrowException(new \RuntimeException('Connection lost'));

        $command = new SmokeProdCommand(
            $this->createStub(QueryBus::class),
            $userRepository,
            $this->createStub(LogServiceInterface::class),
        );

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Smoke tests failed:', $tester->getDisplay());
    }

    public function testVideoListReturnsFailureWhenItemsIsNotArray(): void
    {
        // items is not an array → testVideoList returns false; provide valid results for other tests
        $invalidListResult = (object) ['items' => 'not-an-array', 'total' => 0, 'totalPages' => 0];
        $emptyListResult = (object) ['items' => [], 'total' => 0, 'totalPages' => 0];
        $taskListResult = (object) ['items' => [], 'total' => 0];

        $queryBus = $this->createStub(QueryBus::class);
        $queryBus->method('query')->willReturnOnConsecutiveCalls(
            $invalidListResult, // testVideoList → items is not array → returns false
            $emptyListResult,   // testVideoItemsStructure → empty → returns true
            $taskListResult,    // testTaskList
        );

        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $userRepository->method('findById')->willReturn($this->makeAdminUser());

        $command = new SmokeProdCommand(
            $queryBus,
            $userRepository,
            $this->createStub(LogServiceInterface::class),
        );

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        $this->assertSame(Command::FAILURE, $exitCode);
    }
}
