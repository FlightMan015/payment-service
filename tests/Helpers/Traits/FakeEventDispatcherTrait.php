<?php

declare(strict_types=1);

namespace Tests\Helpers\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * Fixes known issue: https://github.com/laravel/framework/issues/18923#issuecomment-1311520190
 */
trait FakeEventDispatcherTrait
{
    private function fakeEvents(array $eventsToFake = []): void
    {
        $fakeEventDispatcher = Event::fake(eventsToFake: $eventsToFake);
        DB::setEventDispatcher($fakeEventDispatcher);
    }
}
