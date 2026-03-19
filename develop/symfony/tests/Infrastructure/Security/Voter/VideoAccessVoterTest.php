<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Security\Voter;

use App\Domain\Video\Entity\Video;
use App\Domain\Video\ValueObject\FileExtension;
use App\Domain\Video\ValueObject\VideoDates;
use App\Domain\Video\ValueObject\VideoStatus;
use App\Domain\Video\ValueObject\VideoTitle;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use App\Infrastructure\Security\Voter\VideoAccessVoter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Uid\UuidV4;

final class VideoAccessVoterTest extends TestCase
{
    #[DataProvider('attributeProvider')]
    public function testGrantsAccessForVideoOwner(string $attribute): void
    {
        $video = $this->createVideo(77);
        $user = new UserEntity();
        $user->id = 77;
        $user->roles = ['ROLE_USER'];

        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $voter = new VideoAccessVoter();

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, $video, [$attribute]));
    }

    #[DataProvider('attributeProvider')]
    public function testGrantsAccessForAdmin(string $attribute): void
    {
        $video = $this->createVideo(77);
        $user = new UserEntity();
        $user->id = 12;
        $user->roles = ['ROLE_ADMIN'];

        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $voter = new VideoAccessVoter();

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, $video, [$attribute]));
    }

    #[DataProvider('attributeProvider')]
    public function testDeniesAccessForForeignUser(string $attribute): void
    {
        $video = $this->createVideo(77);
        $user = new UserEntity();
        $user->id = 88;
        $user->roles = ['ROLE_USER'];

        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $voter = new VideoAccessVoter();

        $this->assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($token, $video, [$attribute]));
    }

    #[DataProvider('attributeProvider')]
    public function testDeniesAccessForAnonymous(string $attribute): void
    {
        $video = $this->createVideo(77);

        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn(null);

        $voter = new VideoAccessVoter();

        $this->assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($token, $video, [$attribute]));
    }

    public static function attributeProvider(): array
    {
        return [
            [VideoAccessVoter::CAN_VIEW_DETAILS],
            [VideoAccessVoter::CAN_START_TRANSCODE],
        ];
    }

    private function createVideo(int $ownerId): Video
    {
        return Video::create(
            new VideoTitle('Owner video'),
            new FileExtension('mp4'),
            VideoStatus::UPLOADED,
            $ownerId,
            [],
            VideoDates::create(new \DateTimeImmutable('2026-03-19 10:00:00')),
            UuidV4::fromString('11111111-1111-4111-8111-111111111111'),
        );
    }
}


