<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Controller;

use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use App\Tests\Presentation\Controller\Api\InMemoryTestUserProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\UuidV4;

final class VideoControllerTest extends WebTestCase
{
    public function testGuestIsRedirectedFromDetails(): void
    {
        $client = static::createClient();
        $client->request('GET', '/video/11111111-1111-4111-8111-111111111111');

        self::assertResponseRedirects('/login');
    }

    public function testAuthenticatedUserSeesVideoDetailsSpaMount(): void
    {
        $client = static::createClient();

        $user = new UserEntity();
        $user->id = UuidV4::fromString('00000000-0000-4000-8000-000000000201');
        $user->email = 'video-user@example.com';
        $user->roles = ['ROLE_USER'];

        static::getContainer()->set('security.user.provider.concrete.app_user_provider', new InMemoryTestUserProvider($user));

        $client->loginUser($user);
        $crawler = $client->request('GET', '/video/11111111-1111-4111-8111-111111111111');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('#video-details-spa'));

        $spaRoot = $crawler->filter('#video-details-spa')->first();

        self::assertSame('video-user@example.com', $spaRoot->attr('data-user-identifier'));
        self::assertNotSame('', (string) $spaRoot->attr('data-api-bearer-token'));
        self::assertSame('/api/video/__UUID__', $spaRoot->attr('data-api-video-details-url-template'));
        self::assertSame('/api/video/__UUID__/transcode/__PRESET_ID__', $spaRoot->attr('data-api-video-transcode-url-template'));
        self::assertSame('/api/task/__TASK_ID__/cancel', $spaRoot->attr('data-api-task-cancel-url-template'));
        self::assertSame('/task/__TASK_ID__/download', $spaRoot->attr('data-task-download-url-template'));
        self::assertSame('/', $spaRoot->attr('data-home-url'));
        self::assertSame('11111111-1111-4111-8111-111111111111', $spaRoot->attr('data-video-uuid'));
    }
}

