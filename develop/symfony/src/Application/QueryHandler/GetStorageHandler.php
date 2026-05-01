<?php
declare(strict_types=1);

namespace App\Application\QueryHandler;

use App\Application\Query\GetStorageQuery;
use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Infrastructure\Security\Voter\VideoAccessVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\MimeTypes;

#[AsMessageHandler(bus: 'messenger.bus.command')]
final readonly class GetStorageHandler
{
    public function __construct(
        private VideoRepositoryInterface $videoRepository,
        private Security $security,
        private AdapterInterface $cache,
    ) {
    }

    public function __invoke(GetStorageQuery $query): Response
    {
        // The key format is "{videoUuid}/..." — extract the first segment as the video UUID.
        $videoUuid = Uuid::fromStringNullable(
            preg_split('#[/.]#', $query->key, 2)[0]
        );

        if ($videoUuid === null) {
            return new Response('Access denied', Response::HTTP_FORBIDDEN);
        }

        $userUuid = $this->security->getUser()->id->toRfc4122();
        $cacheKey = 'storage_access_'.(string)$videoUuid.'_'.$userUuid;

        // Check cache first
        $cacheItem = $this->cache->getItem($cacheKey);
        if ($cacheItem->isHit()) {
            $isAllowed = $cacheItem->get();
            if (!$isAllowed) {
                return new Response('Access denied', Response::HTTP_FORBIDDEN);
            }
        } else {
            $video = $this->videoRepository->findById($videoUuid);
            $cacheItem->expiresAfter(3600 * 24); // 1 day

            if ($video === null) {
                $cacheItem->set(false);
                $this->cache->save($cacheItem);

                return new Response('Access denied', Response::HTTP_FORBIDDEN);
            }

            if (!$this->security->isGranted(VideoAccessVoter::CAN_DOWNLOAD, $video)) {
                $cacheItem->set(false);
                $this->cache->save($cacheItem);

                return new Response('Access denied', Response::HTTP_FORBIDDEN);
            }

            $cacheItem->set(true);
            $this->cache->save($cacheItem);
        }

        return new Response(null, Response::HTTP_OK, [
            'X-Accel-Redirect' => '/storage/internal/'.$query->key,
            'Content-Type' => new MimeTypes()->getMimeTypes(
                    pathinfo($query->key, PATHINFO_EXTENSION)
                ) ?? 'application/octet-stream',
        ]);
    }
}
