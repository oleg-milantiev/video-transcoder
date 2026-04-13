<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Controller;

use App\Presentation\Controller\UploadController;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;
use TusPhp\Tus\Server as TusServer;

/**
 * @covers \App\Presentation\Controller\UploadController
 */
class UploadControllerTest extends WebTestCase
{
    public function testUploadHandlerReturnsResponseFromTusServer(): void
    {
        $client = static::createClient();
        
        // We need to authenticate first to pass the IsGranted check
        // Create a test user and log in
        $user = $this->createTestUser();
        $client->loginUser($user);
        
        // Mock the TusServer and EventDispatcher
        /** @var TusServer|MockObject $tusServer */
        $tusServer = $this->createMock(TusServer::class);
        $tusServer->expects($this->once())
            ->method('getUploadDir')
            ->willReturn(sys_get_temp_dir() . '/tus-upload');
        
        $tusServer->expects($this->once())
            ->method('setDispatcher')
            ->with($this->isInstanceOf(EventDispatcherInterface::class));
            
        $tusServer->expects($this->once())
            ->method('serve')
            ->willReturn(new Response('Tus server response', 200));
        
        /** @var EventDispatcherInterface|MockObject $eventDispatcher */
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        
        // Override the services in the container
        static::getContainer()->set(TusServer::class, $tusServer);
        static::getContainer()->set(EventDispatcherInterface::class, $eventDispatcher);
        
        $client->request('POST', '/api/upload');
        
        self::assertResponseStatusCodeSame(200);
        self::assertSame('Tus server response', $client->getResponse()->getContent());
    }

    public function testUploadHandlerCreatesUploadDirectoryIfNotExists(): void
    {
        $client = static::createClient();
        
        // Create a test user and log in
        $user = $this->createTestUser();
        $client->loginUser($user);
        
        // Mock the TusServer
        /** @var TusServer|MockObject $tusServer */
        $tusServer = $this->createMock(TusServer::class);
        $uploadDir = sys_get_temp_dir() . '/tus-upload-test';
        
        // Ensure directory doesn't exist initially
        if (is_dir($uploadDir)) {
            rmdir($uploadDir);
        }
        
        $tusServer->expects($this->once())
            ->method('getUploadDir')
            ->willReturn($uploadDir);
            
        $tusServer->expects($this->once())
            ->method('setDispatcher')
            ->with($this->isInstanceOf(EventDispatcherInterface::class));
            
        $tusServer->expects($this->once())
            ->method('serve')
            ->willReturn(new Response('Tus server response', 200));
        
        /** @var EventDispatcherInterface|MockObject $eventDispatcher */
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        
        // Override the services in the container
        static::getContainer()->set(TusServer::class, $tusServer);
        static::getContainer()->set(EventDispatcherInterface::class, $eventDispatcher);
        
        $client->request('POST', '/api/upload');
        
        self::assertResponseStatusCodeSame(200);
        self::assertTrue(is_dir($uploadDir));
        
        // Clean up
        if (is_dir($uploadDir)) {
            rmdir($uploadDir);
        }
    }

    private function createTestUser(): \App\Domain\User\Entity\UserEntity
    {
        $user = new \App\Domain\User\Entity\UserEntity();
        $user->id = \Ramsey\Uuid\Uuid::uuid4();
        $user->email = 'test@example.com';
        $user->roles = ['ROLE_USER'];
        $user->setPassword('hashed-password');
        
        return $user;
    }
}