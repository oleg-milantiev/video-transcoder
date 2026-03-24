<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Security\Voter;

use App\Domain\Video\Entity\Video;
use App\Domain\Video\ValueObject\FileExtension;
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
        $ownerId = UuidV4::fromString('77777777-7777-4777-8777-777777777777');
        $video = $this->createVideo($ownerId);
        $user = new UserEntity();
        $user->id = $ownerId;
        $user->roles = ['ROLE_USER'];

        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $voter = new VideoAccessVoter();

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, $video, [$attribute]));
    }

    #[DataProvider('attributeProvider')]
    public function testGrantsAccessForAdmin(string $attribute): void
    {
        $video = $this->createVideo(UuidV4::fromString('77777777-7777-4777-8777-777777777777'));
        $user = new UserEntity();
        $user->id = UuidV4::fromString('12121212-1212-4212-8212-121212121212');
        $user->roles = ['ROLE_ADMIN'];

        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $voter = new VideoAccessVoter();

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, $video, [$attribute]));
    }

    #[DataProvider('attributeProvider')]
    public function testDeniesAccessForForeignUser(string $attribute): void
    {
        $video = $this->createVideo(UuidV4::fromString('77777777-7777-4777-8777-777777777777'));
        $user = new UserEntity();
        $user->id = UuidV4::fromString('88888888-8888-4888-8888-888888888888');
        $user->roles = ['ROLE_USER'];

        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $voter = new VideoAccessVoter();

        $this->assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($token, $video, [$attribute]));
    }

    #[DataProvider('attributeProvider')]
    public function testDeniesAccessForAnonymous(string $attribute): void
    {
        $video = $this->createVideo(UuidV4::fromString('77777777-7777-4777-8777-777777777777'));

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
            [VideoAccessVoter::CAN_DELETE],
        ];
    }

    private function createVideo(UuidV4 $ownerId): Video
    {
        return Video::create(
            new VideoTitle('Owner video'),
            new FileExtension('mp4'),
            $ownerId,
            [],
        );
    }
}


