<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger as MonologLogger;
use Throwable;

class TenantDailyLogTap
{
    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();

        $tenantId = $this->tenantId();

        $logPath = $tenantId === 'central'
            ? storage_path('logs/central.log')
            : storage_path('logs/tenants/' . $tenantId . '.log');

        if (! is_dir(dirname($logPath))) {
            mkdir(dirname($logPath), 0755, true);
        }

        $handlers = $monolog->getHandlers();

        foreach ($handlers as $index => $handler) {

            if (! $handler instanceof StreamHandler) {
                continue;
            }

            $newHandler = new RotatingFileHandler(
                $logPath,
                (int) env('LOG_DAILY_DAYS', 14),
                method_exists($handler, 'getLevel')
                    ? $handler->getLevel()
                    : MonologLogger::DEBUG,
                method_exists($handler, 'getBubble')
                    ? $handler->getBubble()
                    : true,
                0644,
                false
            );

            if (method_exists($handler, 'getFormatter')) {
                $newHandler->setFormatter($handler->getFormatter());
            }

            $handlers[$index] = $newHandler;
        }

        /*
         * Monolog 3 removed setHandlers().
         * Re-add handlers using pushHandler().
         */
        foreach ($monolog->getHandlers() as $handler) {
            $monolog->popHandler();
        }

        foreach (array_reverse($handlers) as $handler) {
            $monolog->pushHandler($handler);
        }
    }


    private function tenantId(): string
    {
        try {

            if (function_exists('tenancy') && tenancy()->initialized) {

                return preg_replace(
                    '/[^A-Za-z0-9_-]/',
                    '_',
                    (string) tenant('id')
                ) ?: 'tenant';
            }

        } catch (Throwable) {

            return 'central';
        }

        return 'central';
    }
}