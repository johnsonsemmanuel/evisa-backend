<?php

namespace App\Http\Controllers\Api;

use App\Jobs\ProcessSumsubWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Sumsub webhook: return 200 immediately, process async via ProcessSumsubWebhook.
 * Verification: X-App-Token === webhook_secret; X-App-Access-Sig === HMAC-SHA256(rawBody, secret_key).
 */
class SumsubWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();

        if (!$this->verifyWebhook($request, $rawBody)) {
            Log::warning('Sumsub webhook verification failed', ['ip' => $request->ip()]);
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $payload = $request->all();
        $type = $payload['type'] ?? null;
        $applicantId = $payload['applicantId'] ?? null;

        if (!$applicantId) {
            Log::warning('Sumsub webhook missing applicantId');
            return response()->json(['error' => 'Bad request'], 400);
        }

        // Return 200 immediately so Sumsub does not retry
        ProcessSumsubWebhook::dispatch($payload)->onQueue('default');

        return response()->json(['status' => 'ok'], 200);
    }

    private function verifyWebhook(Request $request, string $rawBody): bool
    {
        $webhookSecret = config('sumsub.webhook_secret');
        $secretKey = config('sumsub.secret_key');

        $token = $request->header('X-App-Token');
        if ($webhookSecret && $token !== $webhookSecret) {
            return false;
        }

        $sig = $request->header('X-App-Access-Sig');
        if ($sig === null || $sig === '') {
            return false;
        }

        $expectedSig = hash_hmac('sha256', $rawBody, $secretKey);
        return hash_equals($expectedSig, $sig);
    }
}
