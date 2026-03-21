<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Controller;

use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use App\Tests\Presentation\Controller\Api\InMemoryTestUserProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\UuidV4;

final class HomeControllerTest extends WebTestCase
{
    public function testGuestSeesLoginCtaAndNoSpaRoot(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/login"]');
        self::assertCount(0, $crawler->filter('#home-spa'));
    }

    public function testAuthenticatedUserSeesSpaMountWithApiConfig(): void
    {
        $client = static::createClient();

        $user = new UserEntity();
        $user->id = UuidV4::fromString('00000000-0000-4000-8000-000000000101');
        $user->email = 'home-user@example.com';
        $user->roles = ['ROLE_USER'];

        static::getContainer()->set('security.user.provider.concrete.app_user_provider', new InMemoryTestUserProvider($user));

        $client->loginUser($user);
        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('#home-spa'));

        $spaRoot = $crawler->filter('#home-spa')->first();

        self::assertSame('home-user@example.com', $spaRoot->attr('data-user-identifier'));
        self::assertNotSame('', (string) $spaRoot->attr('data-api-bearer-token'));
        self::assertSame('/api/video/', $spaRoot->attr('data-api-video-list-url'));
        self::assertSame('/api/task/', $spaRoot->attr('data-api-task-list-url'));
        self::assertSame('/api/video/__UUID__', $spaRoot->attr('data-api-video-details-url-template'));
        self::assertSame('/api/video/__UUID__/transcode/__PRESET_ID__', $spaRoot->attr('data-api-video-transcode-url-template'));
        self::assertSame('/api/task/__TASK_ID__/cancel', $spaRoot->attr('data-api-task-cancel-url-template'));
        self::assertSame('/api/upload', $spaRoot->attr('data-api-upload-url'));
        self::assertSame('/video/__UUID__', $spaRoot->attr('data-video-details-url-template'));
        self::assertSame('/task/__TASK_ID__/download', $spaRoot->attr('data-task-download-url-template'));
        self::assertSame('/', $spaRoot->attr('data-home-url'));
    }
}



