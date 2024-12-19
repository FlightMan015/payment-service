<?php

declare(strict_types=1);

namespace App\Entities\Enums;

enum AccountStatusEnum: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case FROZEN = 'frozen';
}
