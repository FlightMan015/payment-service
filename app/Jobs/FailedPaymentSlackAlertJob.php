<?php

declare(strict_types=1);

namespace App\Jobs;

use Spatie\SlackAlerts\Jobs\SendToSlackChannelJob;

class FailedPaymentSlackAlertJob extends SendToSlackChannelJob
{
    /**
     * @param string $webhookUrl
     * @param string|null $text
     * @param array|null $blocks
     * @param string|null $channel
     */
    public function __construct(
        string $webhookUrl,
        string|null $text = null,
        array|null $blocks = null,
        string|null $channel = null
    ) {
        parent::__construct(webhookUrl: $webhookUrl, text: $text, blocks: $blocks, channel: $channel);

        $this->onQueue(queue: config(key: 'queue.connections.sqs.queues.notifications'));
    }
}
