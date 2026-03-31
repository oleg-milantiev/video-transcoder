<?php
declare(strict_types=1);

namespace App\Domain\Video\Exception;

final class TaskAlreadyDeleted extends \DomainException
{
    public static function forTask(): self
    {
        return new self('Task is already deleted.');
    }
}
