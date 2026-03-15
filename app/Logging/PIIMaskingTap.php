<?php

namespace App\Logging;

use Monolog\Logger;

/**
 * PIIMaskingTap
 * 
 * Tap class to inject PIIMaskingProcessor into Monolog logger.
 * Implements ISO 27001 A.8.11 (Data masking) compliance.
 * 
 * @package App\Logging
 */
class PIIMaskingTap
{
    /**
     * Customize the given logger instance.
     *
     * @param Logger $logger
     * @return void
     */
    public function __invoke(Logger $logger): void
    {
        foreach ($logger->getHandlers() as $handler) {
            $handler->pushProcessor(new PIIMaskingProcessor());
        }
    }
}
