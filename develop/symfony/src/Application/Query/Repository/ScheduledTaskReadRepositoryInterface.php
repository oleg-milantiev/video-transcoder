<?php
declare(strict_types=1);

namespace App\Application\Query\Repository;

use App\Application\DTO\ScheduledTaskDTO;

interface ScheduledTaskReadRepositoryInterface
{
    /**
     * @return ScheduledTaskDTO[]
     */
    public function getScheduled(): array;
}
