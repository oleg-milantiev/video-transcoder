<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Controller;

use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use App\Tests\Presentation\Controller\Api\InMemoryTestUserProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\UuidV4 as SymfonyUuid;

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
        $user->id = SymfonyUuid::fromString('00000000-0000-4000-8000-000000000101');
        $user->email = 'home-user@example.com';
        $user->roles = ['ROLE_USER'];

        static::getContainer()->set('security.user.provider.concrete.app_user_provider', new InMemoryTestUserProvider($user));

        $client->loginUser($user);
        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('#home-spa'));

        // Expect the SPA config to be injected as a JSON object in an inline script
        self::assertCount(1, $crawler->filter('#home-spa'));

        $scriptNode = null;
        foreach ($crawler->filter('script') as $node) {
            $text = $node->textContent;
            if (strpos($text, 'var config =') !== false) {
                $scriptNode = $text;
                break;
            }
        }

        self::assertNotNull($scriptNode, 'Expected an inline script with `var config =` declaration');

        $pos = strpos($scriptNode, 'var config =');
        self::assertNotFalse($pos, 'Could not find `var config =` in script content');
        $start = strpos($scriptNode, '{', $pos);
        self::assertNotFalse($start, 'Could not find opening brace for config JSON');

        $depth = 0;
        $len = strlen($scriptNode);
        $end = null;
        for ($i = $start; $i < $len; $i++) {
            $ch = $scriptNode[$i];
            if ($ch === '{') {
                $depth++;
            } elseif ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    $end = $i;
                    break;
                }
            }
        }
        self::assertNotNull($end, 'Could not find matching closing brace for config JSON');

        $json = substr($scriptNode, $start, $end - $start + 1);
        $config = json_decode($json, true);
        self::assertIsArray($config);

        // user
        self::assertArrayHasKey('user', $config);
        self::assertSame('00000000-0000-4000-8000-000000000101', $config['user']['id'] ?? '');
        self::assertSame('home-user@example.com', $config['user']['identifier'] ?? '');

        // token
        self::assertArrayHasKey('token', $config);
        self::assertNotEmpty($config['token']['access'] ?? '');
        self::assertNotEmpty($config['token']['refresh'] ?? '');

        $expectedHub = getenv('MERCURE_PUBLIC_URL') ?: (getenv('DEFAULT_URI') . ':8080/.well-known/mercure');
        $expectedTopicPrefix = getenv('MERCURE_TOPIC_PREFIX') ?: (getenv('DEFAULT_URI') . '/user');

        // mercure
        self::assertArrayHasKey('mercure', $config);
        self::assertSame($expectedHub, $config['mercure']['hub'] ?? '');
        self::assertSame($expectedTopicPrefix . '/' . (string) $user->id, $config['mercure']['topic'] ?? '');
        self::assertNotEmpty($config['mercure']['token'] ?? '');

        // routes
        self::assertArrayHasKey('route', $config);
        self::assertSame('/api/video/', $config['route']['video']['list'] ?? '');
        self::assertSame('/api/task/', $config['route']['task']['list'] ?? '');
        self::assertSame('/api/video/__UUID__', $config['route']['video']['details'] ?? '');
        self::assertSame('/api/video/__UUID__/transcode/__PRESET_ID__', $config['route']['video']['transcode'] ?? '');
        self::assertSame('/api/task/__TASK_ID__/cancel', $config['route']['task']['cancel'] ?? '');
        self::assertSame('/api/upload', $config['route']['upload'] ?? '');
        self::assertSame('/video/__UUID__', $config['route']['videoDetails'] ?? '');
        self::assertSame('/task/__TASK_ID__/download', $config['route']['task']['download'] ?? '');
        self::assertSame('/', $config['route']['home'] ?? '/');

        // tariff storage
        self::assertArrayHasKey('tariff', $config);
        self::assertIsArray($config['tariff']);
        self::assertSame(0, $config['tariff']['storage']['now'] ?? 0);
        self::assertSame(0, $config['tariff']['storage']['max'] ?? 0);

        // maxVideoSize absent or empty
        self::assertSame('', (string)($config['maxVideoSize'] ?? ''));
    }
}
