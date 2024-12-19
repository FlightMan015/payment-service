<?php

declare(strict_types=1);

namespace App\Logging\Taps;

use Illuminate\Log\Logger;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\FormattableHandlerInterface;

class WithDataDogContext
{
    private Logger $logger;
    private array $context;

    /**
     * Customize the given logger instance.
     *
     * @param Logger $logger
     *
     * @return void
     */
    public function __invoke(Logger $logger): void
    {
        $this->setLogger($logger);

        if ($this->canGetDatadogContext()) {
            $this->setDataDogContext();
        } else {
            return; // Data dog context is not defined so exit
        }

        if ($this->loggerHasJsonFormatter()) {
            $this->addDataDogContextToJsonFormattedLogRecord();
        } else {
            // Default to Monolog LineFormatter
            $this->addDataDogContextToLineFormattedLogRecord();
        }
    }

    private function setLogger(Logger $logger): void
    {
        $this->logger = $logger;
    }

    private function canGetDatadogContext(): bool
    {
        return function_exists('\DDTrace\current_context');
    }

    private function setDataDogContext(): void
    {
        // Need the current Datadog context for the trace_ids and span_ids
        $this->context = \DDTrace\current_context();
    }

    private function loggerHasJsonFormatter(): bool
    {
        /** @var array<FormattableHandlerInterface> $handlers */
        $handlers = $this->logger->getHandlers();

        foreach ($handlers as $handler) {
            if (is_a($handler->getFormatter(), JsonFormatter::class)) {
                return true;
            }
        }

        return false;
    }

    private function addDataDogContextToJsonFormattedLogRecord(): void
    {
        $this->logger->pushProcessor(function ($record) {
            // Reference: https://docs.datadoghq.com/tracing/other_telemetry/connect_logs_and_traces/php/
            $record->extra['dd'] = [
                'trace_id' => $this->context['trace_id'],
                'span_id' => $this->context['span_id']
            ];

            return $record;
        });
    }

    private function addDataDogContextToLineFormattedLogRecord(): void
    {
        $this->logger->pushProcessor(function ($record) {
            // DataDog requires the trace and span IDs to be added to the 'message' portion of the log record
            //   in this format for Line Formatted logs
            // Reference: https://docs.datadoghq.com/tracing/other_telemetry/connect_logs_and_traces/php/
            $record['message'] .= sprintf(
                ' [dd.trace_id=%s dd.span_id=%s]',
                $this->context['trace_id'],
                $this->context['span_id']
            );

            return $record;
        });
    }
}
