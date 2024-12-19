<?php

declare(strict_types=1);

namespace App\Traits;

trait LoadingAreaNames
{
    private array $areaNames = [];

    private function loadAreaNames(): void
    {
        if (!property_exists(static::class, 'areaRepository') || !isset($this->areaRepository)) {
            throw new \RuntimeException(message: __('messages.area.repository_not_set'));
        }

        $this->areaNames = $this->areaRepository->retrieveAllNames();
    }
}
