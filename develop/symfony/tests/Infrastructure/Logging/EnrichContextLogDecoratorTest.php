<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Logging;

use App\Application\Logging\LogServiceInterface;
use App\Infrastructure\Logging\EnrichContextLogDecorator;
use App\Infrastructure\Persistence\Doctrine\Preset\PresetEntity;
use App\Infrastructure\Persistence\Doctrine\Preset\PresetRepository;
use App\Infrastructure\Persistence\Doctrine\Task\TaskRepository;
use App\Infrastructure\Persistence\Doctrine\User\TariffEntity;
use App\Infrastructure\Persistence\Doctrine\User\TariffRepository;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use App\Infrastructure\Persistence\Doctrine\User\UserRepository;
use App\Infrastructure\Persistence\Doctrine\Video\VideoEntity;
use App\Infrastructure\Persistence\Doctrine\Video\VideoRepository;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

final class EnrichContextLogDecoratorTest extends TestCase
{
    private LogServiceInterface $inner;
    private AdminUrlGeneratorInterface $adminUrlGenerator;
    private VideoRepository $videoRepository;
    private UserRepository $userRepository;
    private PresetRepository $presetRepository;
    private TaskRepository $taskRepository;
    private TariffRepository $tariffRepository;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $urlGen = $this->createStub(AdminUrlGeneratorInterface::class);
        $urlGen->method('unsetAll')->willReturnSelf();
        $urlGen->method('setController')->willReturnSelf();
        $urlGen->method('setAction')->willReturnSelf();
        $urlGen->method('setEntityId')->willReturnSelf();
        $urlGen->method('generateUrl')->willReturn('/admin/entity/1');
        $this->adminUrlGenerator = $urlGen;

        $this->inner = $this->createMock(LogServiceInterface::class);
        $this->logger = $this->createStub(LoggerInterface::class);

        $this->videoRepository = $this->createStub(VideoRepository::class);
        $this->userRepository = $this->createStub(UserRepository::class);
        $this->presetRepository = $this->createStub(PresetRepository::class);
        $this->taskRepository = $this->createStub(TaskRepository::class);
        $this->tariffRepository = $this->createStub(TariffRepository::class);
    }

    private function makeDecorator(): EnrichContextLogDecorator
    {
        return new EnrichContextLogDecorator(
            $this->inner,
            $this->adminUrlGenerator,
            $this->videoRepository,
            $this->userRepository,
            $this->presetRepository,
            $this->taskRepository,
            $this->tariffRepository,
            $this->logger,
        );
    }

    public function testEnrichesVideoContext(): void
    {
        $videoEntity = new VideoEntity();
        $videoEntity->title = 'My Video';
        $this->videoRepository->method('find')->willReturn($videoEntity);

        $capturedContext = null;
        $this->inner->expects($this->once())
            ->method('log')
            ->willReturnCallback(static function ($n, $a, $id, $l, $t, $ctx) use (&$capturedContext): void {
                $capturedContext = $ctx;
            });

        $this->makeDecorator()->log('video', 'create', null, LogLevel::INFO, 'test', ['videoId' => 'abc-123']);

        self::assertArrayHasKey('videoTitle', $capturedContext);
        self::assertSame('My Video', $capturedContext['videoTitle']);
        self::assertArrayHasKey('videoAdminUrl', $capturedContext);
    }

    public function testEnrichesUserContext(): void
    {
        $userEntity = new UserEntity();
        $userEntity->email = 'test@example.com';
        $this->userRepository->method('find')->willReturn($userEntity);

        $capturedContext = null;
        $this->inner->expects($this->once())
            ->method('log')
            ->willReturnCallback(static function ($n, $a, $id, $l, $t, $ctx) use (&$capturedContext): void {
                $capturedContext = $ctx;
            });

        $this->makeDecorator()->log('user', 'create', null, LogLevel::INFO, 'test', ['userId' => 'user-1']);

        self::assertArrayHasKey('userEmail', $capturedContext);
        self::assertSame('test@example.com', $capturedContext['userEmail']);
    }

    public function testEnrichesPresetContext(): void
    {
        $presetEntity = new PresetEntity();
        $presetEntity->title = 'HD 1080p';
        $this->presetRepository->method('find')->willReturn($presetEntity);

        $capturedContext = null;
        $this->inner->expects($this->once())
            ->method('log')
            ->willReturnCallback(static function ($n, $a, $id, $l, $t, $ctx) use (&$capturedContext): void {
                $capturedContext = $ctx;
            });

        $this->makeDecorator()->log('preset', 'update', null, LogLevel::INFO, 'test', ['presetId' => 'p1']);

        self::assertArrayHasKey('presetTitle', $capturedContext);
        self::assertSame('HD 1080p', $capturedContext['presetTitle']);
    }

    public function testEnrichesTaskContext(): void
    {
        $this->taskRepository->method('find')->willReturn(new \stdClass());

        $capturedContext = null;
        $this->inner->expects($this->once())
            ->method('log')
            ->willReturnCallback(static function ($n, $a, $id, $l, $t, $ctx) use (&$capturedContext): void {
                $capturedContext = $ctx;
            });

        $this->makeDecorator()->log('task', 'transcode', null, LogLevel::INFO, 'test', ['taskId' => 't1']);

        self::assertArrayHasKey('taskAdminUrl', $capturedContext);
    }

    public function testEnrichesTariffContext(): void
    {
        $tariff = new TariffEntity();
        $tariff->title = 'Pro Plan';
        $this->tariffRepository->method('find')->willReturn($tariff);

        $capturedContext = null;
        $this->inner->expects($this->once())
            ->method('log')
            ->willReturnCallback(static function ($n, $a, $id, $l, $t, $ctx) use (&$capturedContext): void {
                $capturedContext = $ctx;
            });

        $this->makeDecorator()->log('tariff', 'create', null, LogLevel::INFO, 'test', ['tariffId' => 'tar1']);

        self::assertArrayHasKey('tariffTitle', $capturedContext);
        self::assertSame('Pro Plan', $capturedContext['tariffTitle']);
    }

    public function testSkipsEnrichmentWhenEntityNotFound(): void
    {
        $this->videoRepository->method('find')->willReturn(null);

        $capturedContext = null;
        $this->inner->expects($this->once())
            ->method('log')
            ->willReturnCallback(static function ($n, $a, $id, $l, $t, $ctx) use (&$capturedContext): void {
                $capturedContext = $ctx;
            });

        $this->makeDecorator()->log('video', 'create', null, LogLevel::INFO, 'test', ['videoId' => 'missing']);

        self::assertArrayNotHasKey('videoTitle', $capturedContext);
        self::assertArrayNotHasKey('videoAdminUrl', $capturedContext);
    }

    public function testPassesThroughContextWithNoKnownKeys(): void
    {
        $capturedContext = null;
        $this->inner->expects($this->once())
            ->method('log')
            ->willReturnCallback(static function ($n, $a, $id, $l, $t, $ctx) use (&$capturedContext): void {
                $capturedContext = $ctx;
            });

        $this->makeDecorator()->log('unknown', 'action', null, LogLevel::DEBUG, 'msg', ['foo' => 'bar']);

        self::assertSame(['foo' => 'bar'], $capturedContext);
    }

    public function testSilentlyIgnoresRepositoryException(): void
    {
        $this->videoRepository->method('find')->willThrowException(new \RuntimeException('DB down'));

        $capturedContext = null;
        $this->inner->expects($this->once())
            ->method('log')
            ->willReturnCallback(static function ($n, $a, $id, $l, $t, $ctx) use (&$capturedContext): void {
                $capturedContext = $ctx;
            });

        // Should not throw, just skip enrichment
        $this->makeDecorator()->log('video', 'create', null, LogLevel::INFO, 'test', ['videoId' => 'abc']);

        self::assertArrayNotHasKey('videoTitle', $capturedContext);
    }
}
