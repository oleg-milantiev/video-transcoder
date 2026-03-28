<?php

declare(strict_types=1);

namespace App\Tests\Application\Exception;

use App\Application\Exception\InvalidUuidException;
use App\Application\Exception\PresetNotFoundException;
use App\Application\Exception\QueryException;
use App\Application\Exception\TaskCreationFailedException;
use App\Application\Exception\TaskNotFoundException;
use App\Application\Exception\TranscodeAccessDeniedException;
use App\Application\Exception\UserNotFoundException;
use App\Application\Exception\VideoNotFoundException;
use PHPUnit\Framework\TestCase;

final class ApplicationExceptionsTest extends TestCase
{
    public function testQueryExceptionIsRuntimeException(): void
    {
        $e = new QueryException('test');
        $this->assertInstanceOf(\RuntimeException::class, $e);
        $this->assertSame('test', $e->getMessage());
    }

    public function testInvalidUuidExceptionExtendsQueryException(): void
    {
        $e = new InvalidUuidException('bad uuid');
        $this->assertInstanceOf(QueryException::class, $e);
    }

    public function testPresetNotFoundExceptionExtendsQueryException(): void
    {
        $e = new PresetNotFoundException('preset not found');
        $this->assertInstanceOf(QueryException::class, $e);
    }

    public function testTaskCreationFailedExceptionExtendsQueryException(): void
    {
        $e = new TaskCreationFailedException('create failed');
        $this->assertInstanceOf(QueryException::class, $e);
    }

    public function testTaskNotFoundExceptionExtendsDomainException(): void
    {
        $e = new TaskNotFoundException('task not found');
        $this->assertInstanceOf(\DomainException::class, $e);
    }

    public function testTranscodeAccessDeniedExceptionExtendsQueryException(): void
    {
        $e = new TranscodeAccessDeniedException('denied');
        $this->assertInstanceOf(QueryException::class, $e);
    }

    public function testUserNotFoundExceptionExtendsQueryException(): void
    {
        $e = new UserNotFoundException('user not found');
        $this->assertInstanceOf(QueryException::class, $e);
    }

    public function testVideoNotFoundExceptionExtendsQueryException(): void
    {
        $e = new VideoNotFoundException('video not found');
        $this->assertInstanceOf(QueryException::class, $e);
    }
}
