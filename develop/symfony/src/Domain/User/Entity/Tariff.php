<?php

namespace App\Domain\User\Entity;

class Tariff
{
    private ?int $id;
    private string $title;
    private int $delay;
    private int $instance;

    public function __construct(
        string $title,
        int    $delay,
        int    $instance,
        ?int   $id = null
    ) {
        // TODO DDD
        $this->title = $title;
        $this->delay = $delay;
        $this->instance = $instance;
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

    public function instance(): int
    {
        return $this->instance;
    }

    public function __toString(): string
    {
        return $this->title;
    }
}
