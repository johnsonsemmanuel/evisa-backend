<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\PaymentReconciliationIssue;
use App\Models\User;
use App\Notifications\ReconciliationAlertNotification;
use App\Services\GcbPaymentService;
use App\Services\PaystackService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class ReconcilePayments extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'reconcile:payments 
                            {--gateway=all : Gateway to reconcile (gcb|paystack|all)}
                            {--date= : Date to reconcile (YYYY-MM-DD), defaults to yesterday}';

    /**
     * The console command description.
     */
    protected $description = 'Reconcile local payment records against gateway records for financial audit compliance';

    /**
     * Payment gateway services.
     */
    protected GcbPaymentService $gcbService;
    protected PaystackService $paystackService;

    /**
     * Reconciliation statistics.
     */
    protected array $stats = [
        'total_checked' => 0,
        'matched' => 0,
        'discrepancies' => 0,
        'errors' => 0,
    ];

    /**
     * Create a new command instance.
     */
    public function __construct(GcbPaymentService $gcbService, PaystackService $paystackService)
    {
        parent::__construct();
        $this->gcbService = $gcbService;
        $this->paystackService = $paystackService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $gateway = $this->option('gateway');
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::yesterday();

        $this->info("Starting payment reconciliation for {$date->format('Y-m-d')}");
        $this->info("Gateway: {$gateway}");
        $this->newLine();

        try {
            // Validate gateway option
            if (!in_array($gateway, ['gcb', 'paystack', 'all'])) {
                $this->error('Invalid gateway. Must be: gcb, paystack, or all');
                return self::FAILURE;
            }

            // Run reconciliation
            if ($gateway === 'all') {
                $this->reconcileGateway('gcb', $date);
                $this->reconcileGateway('paystack', $date);
            } else {
                $this->reconcileGateway($gateway, $date);
            }

            // Display summary
            $this->displaySummary();

            // Send alerts if discrepancies found
            if ($this->stats['discrepancies'] > 0) {
                $this->sendReconciliationAlert($date, $gateway);
            }

            $this->info('Payment reconciliation completed successfully');
            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Reconciliation failed: ' . $e->getMessage());
            Log::error('Payment reconciliation failed', [
                'gateway' => $gateway,
                'date' => $date->format('Y-m-d'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return self::FAILURE;
        }
    }

    /**
     * Reconcile payments for a specific gateway.
     */
    protected function reconcileGateway(string $gateway, Carbon $date): void
    {
        $this->info("Reconciling {$gateway} payments...");

        // Get payments for the date
        $payments = Payment::where('gateway', $gateway)
            ->whereIn('status', ['paid', 'failed', 'refunded'])
            ->whereDate('created_at', $date)
            ->get();

        if ($payments->isEmpty()) {
            $this->warn("No {$gateway} payments found for {$date->format('Y-m-d')}");
            return;
        }

        $this->info("Found {$payments->count()} {$gateway} payments to reconcile");

        // Create progress bar
        $progressBar = $this->output->createProgressBar($payments->count());
        $progressBar->start();

        foreach ($payments as $payment) {
            $this->reconcilePayment($payment, $date);
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
    }

    /**
     * Reconcile a single payment.
     */
    protected function reconcilePayment(Payment $payment, Carbon $date): void
    {
        $this->stats['total_checked']++;

        try {
            // Get gateway status
            $gatewayResult = $this->getGatewayStatus($payment);

            if (!$gatewayResult['success']) {
                $this->handleGatewayError($payment, $gatewayResult, $date);
                return;
            }

            // Compare statuses
            $this->comparePaymentStatus($payment, $gatewayResult, $date);

        } catch (\Exception $e) {
            $this->stats['errors']++;
            Log::error('Payment reconciliation error', [
                'payment_id' => $payment->id,
                'reference' => $payment->transaction_reference,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get payment status from gateway.
     */
    protected function getGatewayStatus(Payment $payment): array
    {
        return match ($payment->gateway) {
            'gcb' => $this->gcbService->getTransactionStatus($payment->transaction_reference),
            'paystack' => $this->paystackService->verifyTransaction($payment->transaction_reference),
            default => ['success' => false, 'message' => 'Unknown gateway'],
        };
    }

    /**
     * Handle gateway API errors.
     */
    protected function handleGatewayError(Payment $payment, array $gatewayResult, Carbon $date): void
    {
        $this->stats['errors']++;

        // Check if it's a "not found" error
        if (isset($gatewayResult['status']) && $gatewayResult['status'] === 'not_found') {
            $this->createReconciliationIssue([
                'payment_id' => $payment->id,
                'gateway' => $payment->gateway,
                'gateway_reference' => $payment->gateway_reference,
                'issue_type' => 'REFERENCE_NOT_FOUND',
                'local_status' => $payment->status,
                'gateway_status' => 'not_found',
                'local_amount' => $payment->amount,
                'gateway_amount' => null,
                'reconciliation_date' => $date,
                'notes' => 'Payment reference not found at gateway: ' . ($gatewayResult['message'] ?? 'Unknown error'),
            ]);

            $this->stats['discrepancies']++;
        }
    }

    /**
     * Compare local payment status with gateway status.
     */
    protected function comparePaymentStatus(Payment $payment, array $gatewayResult, Carbon $date): void
    {
        $localStatus = $payment->status;
        $gatewayStatus = $gatewayResult['status'] ?? 'unknown';
        $gatewayAmount = $gatewayResult['amount'] ?? null;

        // Check for status discrepancies
        $issueType = $this->detectStatusDiscrepancy($localStatus, $gatewayStatus);

        if ($issueType) {
            $this->createReconciliationIssue([
                'payment_id' => $payment->id,
                'gateway' => $payment->gateway,
                'gateway_reference' => $gatewayResult['gateway_reference'] ?? $payment->gateway_reference,
                'issue_type' => $issueType,
                'local_status' => $localStatus,
                'gateway_status' => $gatewayStatus,
                'local_amount' => $payment->amount,
                'gateway_amount' => $gatewayAmount,
                'gateway_data' => $gatewayResult['raw_data'] ?? null,
                'reconciliation_date' => $date,
            ]);

            $this->stats['discrepancies']++;

            // Handle critical issues
            if (in_array($issueType, ['LOCAL_PAID_GATEWAY_FAILED', 'AMOUNT_MISMATCH'])) {
                $this->handleCriticalIssue($payment, $issueType);
            }

            return;
        }

        // Check for amount discrepancies
        if ($gatewayAmount && abs($payment->amount - $gatewayAmount) > 1) {
            $this->createReconciliationIssue([
                'payment_id' => $payment->id,
                'gateway' => $payment->gateway,
                'gateway_reference' => $gatewayResult['gateway_reference'] ?? $payment->gateway_reference,
                'issue_type' => 'AMOUNT_MISMATCH',
                'local_status' => $localStatus,
                'gateway_status' => $gatewayStatus,
                'local_amount' => $payment->amount,
                'gateway_amount' => $gatewayAmount,
                'gateway_data' => $gatewayResult['raw_data'] ?? null,
                'reconciliation_date' => $date,
                'notes' => 'Amount difference: ' . abs($payment->amount - $gatewayAmount) . ' pesewas',
            ]);

            $this->stats['discrepancies']++;
            $this->handleCriticalIssue($payment, 'AMOUNT_MISMATCH');
            return;
        }

        // No discrepancies found
        $this->stats['matched']++;
    }

    /**
     * Detect status discrepancy type.
     */
    protected function detectStatusDiscrepancy(string $localStatus, string $gatewayStatus): ?string
    {
        // Normalize statuses for comparison
        $localNormalized = $this->normalizeStatus($localStatus);
        $gatewayNormalized = $this->normalizeStatus($gatewayStatus);

        if ($localNormalized === $gatewayNormalized) {
            return null; // No discrepancy
        }

        // Detect specific discrepancy types
        if ($localNormalized === 'paid' && $gatewayNormalized === 'failed') {
            return 'LOCAL_PAID_GATEWAY_FAILED';
        }

        if ($localNormalized === 'failed' && $gatewayNormalized === 'paid') {
            return 'LOCAL_FAILED_GATEWAY_PAID';
        }

        // Other status mismatches (less critical)
        return null;
    }

    /**
     * Normalize payment status for comparison.
     */
    protected function normalizeStatus(string $status): string
    {
        return match (strtolower($status)) {
            'paid', 'completed', 'success', 'successful' => 'paid',
            'failed', 'failure', 'declined', 'error' => 'failed',
            'pending', 'processing', 'initiated' => 'pending',
            'refunded', 'refund' => 'refunded',
            'expired', 'timeout' => 'expired',
            default => $status,
        };
    }

    /**
     * Handle critical reconciliation issues.
     */
    protected function handleCriticalIssue(Payment $payment, string $issueType): void
    {
        if ($issueType === 'LOCAL_PAID_GATEWAY_FAILED' || $issueType === 'AMOUNT_MISMATCH') {
            // Freeze the application to prevent further processing
            $application = $payment->application;
            if ($application && $application->status !== 'frozen') {
                $application->update([
                    'status' => 'frozen',
                    'notes' => ($application->notes ?? '') . "\n\n[SYSTEM] Application frozen due to payment reconciliation issue: {$issueType}",
                ]);

                Log::critical('Application frozen due to payment reconciliation issue', [
                    'application_id' => $application->id,
                    'payment_id' => $payment->id,
                    'issue_type' => $issueType,
                ]);
            }
        }
    }

    /**
     * Create a reconciliation issue record.
     */
    protected function createReconciliationIssue(array $data): void
    {
        // Check if issue already exists for this payment and date
        $existing = PaymentReconciliationIssue::where('payment_id', $data['payment_id'])
            ->where('reconciliation_date', $data['reconciliation_date'])
            ->where('issue_type', $data['issue_type'])
            ->first();

        if ($existing) {
            // Update existing issue
            $existing->update([
                'gateway_status' => $data['gateway_status'],
                'gateway_amount' => $data['gateway_amount'],
                'gateway_data' => $data['gateway_data'] ?? $existing->gateway_data,
                'notes' => ($existing->notes ?? '') . "\n\n[" . now()->format('Y-m-d H:i:s') . "] Issue detected again during reconciliation",
            ]);
        } else {
            // Create new issue
            PaymentReconciliationIssue::create($data);
        }
    }

    /**
     * Display reconciliation summary.
     */
    protected function displaySummary(): void
    {
        $this->newLine();
        $this->info('=== RECONCILIATION SUMMARY ===');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Checked', $this->stats['total_checked']],
                ['Matched', $this->stats['matched']],
                ['Discrepancies', $this->stats['discrepancies']],
                ['Errors', $this->stats['errors']],
            ]
        );

        if ($this->stats['discrepancies'] > 0) {
            $this->warn("⚠️  {$this->stats['discrepancies']} discrepancies found!");
            $this->info('Finance officers will be notified.');
        } else {
            $this->info('✅ All payments reconciled successfully');
        }
    }

    /**
     * Send reconciliation alert to finance officers.
     */
    protected function sendReconciliationAlert(Carbon $date, string $gateway): void
    {
        try {
            // Get finance officers
            $financeOfficers = User::whereHas('roles', function ($query) {
                $query->where('name', 'finance_officer');
            })->get();

            if ($financeOfficers->isEmpty()) {
                $this->warn('No finance officers found to notify');
                Log::warning('No finance officers found for reconciliation alert');
                return;
            }

            // Send notification
            Notification::send($financeOfficers, new ReconciliationAlertNotification(
                $date,
                $gateway,
                $this->stats
            ));

            $this->info("Reconciliation alert sent to {$financeOfficers->count()} finance officers");

        } catch (\Exception $e) {
            $this->error('Failed to send reconciliation alert: ' . $e->getMessage());
            Log::error('Failed to send reconciliation alert', [
                'error' => $e->getMessage(),
                'date' => $date->format('Y-m-d'),
                'gateway' => $gateway,
            ]);
        }
    }
}