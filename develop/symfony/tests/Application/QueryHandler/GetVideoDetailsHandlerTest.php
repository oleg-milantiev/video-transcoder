<?php
namespace App\Tests\Application\QueryHandler;
use App\Application\Exception\QueryException;
use App\Application\Query\GetVideoDetailsQuery;
use App\Application\QueryHandler\GetVideoDetailsHandler;
use App\Domain\Video\Repository\PresetRepositoryInterface;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
use App\Tests\Domain\Entity\VideoFake;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class GetVideoDetailsHandlerTest extends TestCase
{
    private function makeHandler(
        ?VideoRepositoryInterface $videoRepo = null,
        ?PresetRepositoryInterface $presetRepo = null,
        ?TaskRepositoryInterface $taskRepo = null,
        ?Security $security = null,
    ): GetVideoDetailsHandler {
        return new GetVideoDetailsHandler(
            $videoRepo ?? $this->createStub(VideoRepositoryInterface::class),
            $presetRepo ?? $this->createStub(PresetRepositoryInterface::class),
            $taskRepo ?? $this->createStub(TaskRepositoryInterface::class),
            $this->createStub(StorageInterface::class),
            $security ?? $this->createStub(Security::class),
        );
    }
    public function testThrowsWhenVideoNotFound(): void
    {
        $videoRepo = $this->createStub(VideoRepositoryInterface::class);
        $videoRepo->method('findById')->willReturn(null);
        $handler = $this->makeHandler($videoRepo);
        $this->expectException(QueryException::class);
        $handler(new GetVideoDetailsQuery('00000000-0000-4000-8000-000000000101'));
    }
    public function testThrowsWhenAccessDenied(): void
    {
        $video = VideoFake::create();
        $videoRepo = $this->createStub(VideoRepositoryInterface::class);
        $videoRepo->method('findById')->willReturn($video);
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn(false);
        $handler = $this->makeHandler($videoRepo, security: $security);
        $this->expectException(QueryException::class);
        $handler(new GetVideoDetailsQuery($video->id()->toRfc4122()));
    }
}
