<?php

namespace App\Domain\User\Entity;

use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\User\ValueObject\TariffDelay;
use App\Domain\User\ValueObject\TariffInstance;
use App\Domain\User\ValueObject\TariffTitle;

class Tariff
{
    private ?Uuid $id;
    private TariffTitle $title;
    private TariffDelay $delay;
    private TariffInstance $instance;

    public function __construct(
        TariffTitle $title,
        TariffDelay $delay,
        TariffInstance $instance,
        ?Uuid $id = null
    ) {
        $this->title = $title;
        $this->delay = $delay;
        $this->instance = $instance;
        $this->id = $id;
    }

    public function id(): ?Uuid
    {
        return $this->id;
    }

    public function title(): TariffTitle
    {
        return $this->title;
    }

    public function delay(): TariffDelay
    {
        return $this->delay;
    }

    public function instance(): TariffInstance
    {
        return $this->instance;
    }

    public function __toString(): string
    {
        return $this->title->value();
    }
}
