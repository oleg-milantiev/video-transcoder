<?php

namespace App\Domain\Video\Exception;

final class TaskAlreadyDeleted extends \DomainException
{
    public static function forTask(): self
    {
        return new self('Task is already deleted.');
    }
}
