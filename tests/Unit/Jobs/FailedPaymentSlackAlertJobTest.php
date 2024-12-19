<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Jobs\FailedPaymentSlackAlertJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\UnitTestCase;

class FailedPaymentSlackAlertJobTest extends UnitTestCase
{
    #[Test]
    public function job_is_queued_with_correct_data_in_the_correct_queue(): void
    {
        $webhookUrl = 'https://example.com/slack-webhook';
        $text = 'Payment failed for Invoice #123.';
        $blocks = null;
        $channel = '#general';

        Http::fake([$webhookUrl => Http::response(['status' => 'success'], 200)]);
        Queue::fake();

        FailedPaymentSlackAlertJob::dispatch($webhookUrl, $text, $blocks, $channel);

        Queue::assertPushedOn(
            queue: config(key: 'queue.connections.sqs.queues.notifications'),
            job: static function (FailedPaymentSlackAlertJob $job) use ($webhookUrl, $text, $blocks, $channel) {
                return $job->webhookUrl === $webhookUrl
                    && $job->text === $text
                    && $job->blocks === $blocks
                    && $job->channel === $channel;
            }
        );
    }
}
