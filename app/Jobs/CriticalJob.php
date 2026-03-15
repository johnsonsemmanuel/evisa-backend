<?php

namespace App\Jobs;

/**
 * Base for payment/visa/critical-path jobs: more retries and longer backoff.
 * Use for: payment callbacks, visa issuance, Interpol/Sumsub, risk assessment.
 */
abstract class CriticalJob extends BaseJob
{
    public int $tries = 5;
    public array $backoff = [60, 300, 900, 3600, 7200]; // 1min, 5min, 15min, 1h, 2h
    public int $timeout = 180;
}
