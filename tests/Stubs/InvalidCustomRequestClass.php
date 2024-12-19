<?php

declare(strict_types=1);

namespace Tests\Stubs;

use App\Helpers\RequestDefaultValuesTrait;
use Illuminate\Support\Facades\Request;

class InvalidCustomRequestClass extends Request
{
    // this class is not extending FormRequest, so the instance of this class cannot be created
    use RequestDefaultValuesTrait;
}
