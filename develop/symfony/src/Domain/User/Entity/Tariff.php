<?php

namespace App\Domain\User\Entity;

class Tariff
{
    private ?int $id;
    private string $title;
    private int $delay;

    public function __construct(
        string $title,
        int    $delay,
        ?int   $id = null
    ) {
        $this->title = $title;
        $this->delay = $delay;
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

    public function delay(): int
    {
        return $this->delay;
    }

    public function __toString(): string
    {
        return $this->title;
    }
}
