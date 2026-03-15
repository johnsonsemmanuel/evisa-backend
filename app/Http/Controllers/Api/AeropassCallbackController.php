<?php

namespace App\Http\Controllers\Api;

use App\Enums\RiskLevel;
use App\Models\AeropassAuditLog;
use App\Models\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Aeropass async nominal check callback (ICD).
 * POST /api/aeropass/callback
 * Payload: transactionId, result ('CLEAR'|'HIT'|'INCONCLUSIVE'), matchDetails.
 * Return 200 immediately; verify HMAC before processing.
 */
class AeropassCallbackController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if (!$this->verifySignature($request)) {
            Log::warning('Aeropass callback signature verification failed', ['ip' => $request->ip()]);
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $payload = $request->all();
        $transactionId = $payload['transactionId'] ?? $payload['transaction_id'] ?? $payload['uniqueReferenceId'] ?? null;

        if (!$transactionId) {
            Log::warning('Aeropass callback missing transactionId', ['payload_keys' => array_keys($payload)]);
            return response()->json(['error' => 'Missing transaction reference'], 400);
        }

        $applicationId = $this->parseApplicationIdFromTransactionId($transactionId);
        if ($applicationId === null) {
            $application = Application::withoutGlobalScopes()
                ->where('aeropass_transaction_ref', $transactionId)
                ->first();
        } else {
            $application = Application::withoutGlobalScopes()->find($applicationId);
        }

        if (!$application) {
            Log::warning('Aeropass callback: application not found', ['transaction_id' => $transactionId]);
            return response()->json(['error' => 'Application not found'], 404);
        }

        $result = $this->normaliseResult($payload);
        if ($result === null) {
            Log::warning('Aeropass callback: invalid or missing result', [
                'transaction_id' => $transactionId,
                'payload' => $payload,
            ]);
            return response()->json(['error' => 'Invalid result status'], 400);
        }

        $updates = [
            'aeropass_status' => $result,
            'aeropass_result_at' => now(),
            'aeropass_raw_result' => encrypt($payload),
        ];

        if ($result === 'hit') {
            $updates['risk_level'] = RiskLevel::Critical;
            $updates['watchlist_flagged'] = true;
            $updates['risk_score'] = max((int) ($application->risk_score ?? 0), 100);
            $this->notifySupervisorHit($application);
            Log::warning('Aeropass Interpol HIT — supervisor notified', [
                'application_id' => $application->id,
                'transaction_id' => $transactionId,
            ]);
        } elseif ($result === 'clear') {
            $updates['risk_score'] = $application->risk_score ?? 0;
        } elseif ($result === 'inconclusive') {
            Log::info('Aeropass result inconclusive — flag for manual review', [
                'application_id' => $application->id,
                'transaction_id' => $transactionId,
            ]);
        }

        if (isset($payload['riskScore']) && is_numeric($payload['riskScore'])) {
            $updates['risk_score'] = (int) $payload['riskScore'];
        }

        $application->forceFill($updates)->save();

        $this->storeCallbackAudit($application, $payload);

        return response()->json(['status' => 'ok', 'result' => $result], 200);
    }

    private function parseApplicationIdFromTransactionId(string $transactionId): ?int
    {
        if (preg_match('/^EVISA-(\d+)-/', $transactionId, $m)) {
            return (int) $m[1];
        }
        return null;
    }

    private function normaliseResult(array $payload): ?string
    {
        $s = $payload['result'] ?? $payload['status'] ?? $payload['reviewResult'] ?? null;
        if ($s === null) {
            return null;
        }
        $s = is_string($s) ? strtoupper(trim($s)) : (string) $s;
        if (in_array($s, ['CLEAR', 'HIT', 'INCONCLUSIVE'], true)) {
            return strtolower($s);
        }
        if (in_array($s, ['YES', 'MATCHED'])) {
            return 'hit';
        }
        if (in_array($s, ['NO', 'NO_MATCH'])) {
            return 'clear';
        }
        return null;
    }

    private function verifySignature(Request $request): bool
    {
        $secret = config('aeropass.callback_webhook_secret') ?? config('aeropass.secret_key');
        if (empty($secret)) {
            \Illuminate\Support\Facades\Log::error('Aeropass webhook secret not configured — rejecting callback');
            return false;
        }
        $header = config('aeropass.callback_signature_header', 'X-Aeropass-Signature');
        $signature = $request->header($header);
        if (empty($signature)) {
            return false;
        }
        $body = $request->getContent();
        $expected = 'sha256=' . hash_hmac('sha256', $body, $secret);
        return hash_equals($expected, $signature);
    }

    private function storeCallbackAudit(Application $application, array $payload): void
    {
        AeropassAuditLog::create([
            'application_id' => $application->id,
            'interaction_type' => 'callback_received',
            'request_payload' => encrypt(json_encode($payload)),
            'response_payload' => encrypt(json_encode(['processed' => true])),
            'performed_at' => now(),
        ]);
    }

    private function notifySupervisorHit(Application $application): void
    {
        $email = config('security.monitoring.alert_email') ?? config('mail.finance_alert') ?? config('mail.from.address');
        if ($email) {
            try {
                Notification::route('mail', $email)->notify(
                    new \App\Notifications\AeropassInterpolHitNotification($application)
                );
            } catch (\Throwable $e) {
                Log::error('Failed to send Aeropass hit notification', ['error' => $e->getMessage()]);
            }
        }
        $webhook = config('logging.channels.slack.url') ?? env('LOG_SLACK_WEBHOOK_URL');
        if ($webhook) {
            try {
                Notification::route('slack', $webhook)->notify(
                    new \App\Notifications\AeropassInterpolHitNotification($application)
                );
            } catch (\Throwable $e) {
                Log::error('Failed to send Aeropass hit Slack notification', ['error' => $e->getMessage()]);
            }
        }
    }
}
