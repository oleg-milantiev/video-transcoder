<?php
declare(strict_types=1);

namespace App\Domain\User\Entity;

use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\User\ValueObject\TariffDelay;
use App\Domain\User\ValueObject\TariffInstance;
use App\Domain\User\ValueObject\TariffMaxHeight;
use App\Domain\User\ValueObject\TariffMaxWidth;
use App\Domain\User\ValueObject\TariffStorageGb;
use App\Domain\User\ValueObject\TariffStorageHour;
use App\Domain\User\ValueObject\TariffTitle;
use App\Domain\User\ValueObject\TariffVideoDuration;
use App\Domain\User\ValueObject\TariffVideoSize;

class Tariff
{
    private ?Uuid $id;
    private TariffTitle $title;
    private TariffDelay $delay;
    private TariffInstance $instance;
    private TariffVideoDuration $videoDuration;
    private TariffVideoSize $videoSize;
    private TariffMaxWidth $maxWidth;
    private TariffMaxHeight $maxHeight;
    private TariffStorageGb $storageGb;
    private TariffStorageHour $storageHour;

    public function __construct(
        TariffTitle $title,
        TariffDelay $delay,
        TariffInstance $instance,
        TariffVideoDuration $videoDuration,
        TariffVideoSize $videoSize,
        TariffMaxWidth $maxWidth,
        TariffMaxHeight $maxHeight,
        TariffStorageGb $storageGb,
        TariffStorageHour $storageHour,
        ?Uuid $id = null
    ) {
        $this->title = $title;
        $this->delay = $delay;
        $this->instance = $instance;
        $this->videoDuration = $videoDuration;
        $this->videoSize = $videoSize;
        $this->maxWidth = $maxWidth;
        $this->maxHeight = $maxHeight;
        $this->storageGb = $storageGb;
        $this->storageHour = $storageHour;
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

    public function videoDuration(): TariffVideoDuration
    {
        return $this->videoDuration;
    }

    public function videoSize(): TariffVideoSize
    {
        return $this->videoSize;
    }

    public function maxWidth(): TariffMaxWidth
    {
        return $this->maxWidth;
    }

    public function maxHeight(): TariffMaxHeight
    {
        return $this->maxHeight;
    }

    public function storageGb(): TariffStorageGb
    {
        return $this->storageGb;
    }

    public function storageHour(): TariffStorageHour
    {
        return $this->storageHour;
    }

    public function __toString(): string
    {
        return $this->title->value();
    }
}
