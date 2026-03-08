<?php

namespace App\Domain\User\Entity;

class Tariff
{
    private ?int $id;
    private string $title;
    private int $timeDelay;

    public function __construct(
        string $title,
        int $timeDelay,
        ?int $id = null
    ) {
        $this->title = $title;
        $this->timeDelay = $timeDelay;
        $this->id = $id;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function timeDelay(): int
    {
        return $this->timeDelay;
    }

    public function __toString(): string
    {
        return $this->title;
    }
}
