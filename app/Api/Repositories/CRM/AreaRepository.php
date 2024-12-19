<?php

declare(strict_types=1);

namespace App\Api\Repositories\CRM;

use App\Models\CRM\FieldOperations\Area;
use Illuminate\Database\Eloquent\Model;

class AreaRepository
{
    /**
     * @param int $id
     *
     * @return Area|null
     */
    public function find(int $id): Area|null
    {
        return Area::whereIsActive(true)->find($id);
    }

    /**
     * @param int $externalRefId
     *
     * @return Area|Model|null
     */
    public function findByExternalRefId(int $externalRefId): Area|Model|null
    {
        return Area::whereIsActive(true)->whereExternalRefId($externalRefId)->first();
    }

    /**
     * @param int $id
     *
     * @return bool
     */
    public function exists(int $id): bool
    {
        return Area::whereIsActive(true)->whereId($id)->exists();
    }

    /**
     * @param int $externalRefId
     *
     * @return bool
     */
    public function existsByExternalRefId(int $externalRefId): bool
    {
        return Area::whereIsActive(true)->whereExternalRefId($externalRefId)->exists();
    }

    /**
     * @return int[]
     */
    public function retrieveAllIds(): array
    {
        return Area::whereIsActive(true)->pluck(column: 'id')->toArray();
    }

    /**
     * @return int[]
     */
    public function retrieveAllNames(): array
    {
        return Area::whereIsActive(true)->pluck(column: 'name', key: 'id')->toArray();
    }
}
