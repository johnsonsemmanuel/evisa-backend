<?php

namespace App\Services;

use App\Exceptions\AeropassApiException;
use App\Exceptions\AeropassSubmissionException;
use App\Exceptions\AeropassUnavailableException;
use App\Exceptions\AeropassValidationException;
use App\Models\AeropassAuditLog;
use App\Models\Application;
use App\Models\InterpolCheck;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use Exception;

class AeropassService
{
    private PendingRequest $client;
    private string $baseUrl;
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->baseUrl = rtrim((string) config('aeropass.base_url', config('services.aeropass.base_url', '')), '/');
        $timeout = (int) config('aeropass.timeout_seconds', config('aeropass.timeout', 30));

        if ($this->baseUrl && class_exists(\App\Services\ExternalUrlValidator::class)) {
            app(\App\Services\ExternalUrlValidator::class)->validateExternalUrl($this->baseUrl);
        }

        $this->client = Http::baseUrl($this->baseUrl)
            ->withBasicAuth(
                config('aeropass.username') ?? config('services.aeropass.username'),
                config('aeropass.password') ?? config('services.aeropass.password')
            )
            ->timeout($timeout)
            ->retry(3, 1000, function (Exception $exception): bool {
                if ($exception instanceof ConnectionException) {
                    return true;
                }
                if (method_exists($exception, 'response')) {
                    $response = $exception->response ?? null;
                    return $response && method_exists($response, 'serverError') && $response->serverError();
                }
                return false;
            });
    }

    /**
     * Normalise date for Aeropass (ICD: YYYY-MM-DD, UTC).
     * NEVER send null to Aeropass for required fields — validate before calling.
     */
    private function normaliseDateForAeropass(Carbon|string|null $date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }
        $d = $date instanceof Carbon ? $date : Carbon::parse($date);
        return $d->utc()->format('Y-m-d');
    }

    /**
     * Ensure required date is present; throw AeropassValidationException if missing.
     */
    private function requireDate(Carbon|string|null $date, string $fieldName): string
    {
        $normalised = $this->normaliseDateForAeropass($date);
        if ($normalised === null || $normalised === '') {
            throw new AeropassValidationException(
                "Aeropass requires {$fieldName} but it is missing or invalid.",
                0,
                null,
                $fieldName
            );
        }
        return $normalised;
    }

    /**
     * Get applicant data from application (decrypted PII via accessors).
     * Application holds applicant data directly; no separate Applicant model.
     */
    private function getApplicantData(Application $application): object
    {
        $dob = $application->date_of_birth;
        if (is_string($dob)) {
            $dob = $dob ? Carbon::parse($dob) : null;
        }
        $expiry = $application->passport_expiry ?? null;
        $issue = $application->passport_issue_date ?? null;
        $gender = $application->gender;
        if (is_string($gender)) {
            $gender = strtoupper(substr($gender, 0, 1)) === 'F' ? 'F' : 'M';
        }
        return (object) [
            'first_name' => $application->first_name ?? '',
            'last_name' => $application->last_name ?? '',
            'date_of_birth' => $dob,
            'nationality_code' => $application->nationality ?? '',
            'passport_number' => $application->passport_number ?? '',
            'passport_expiry_date' => $expiry,
            'passport_issue_date' => $issue,
            'gender' => $gender,
        ];
    }

    /**
     * Interpol Nominal Check (ASYNC). POST /api/v1/nominal-check → 202.
     * Returns transactionId for callback matching; store on application in job.
     */
    public function submitNominalCheck(Application $application, string $callbackUrl): array
    {
        $applicant = $this->getApplicantData($application);

        $transactionId = 'EVISA-' . $application->id . '-' . time();
        $dateOfBirth = $this->requireDate($applicant->date_of_birth, 'dateOfBirth');
        $documentExpiry = $this->requireDate($applicant->passport_expiry_date, 'documentExpiry');

        $payload = [
            'transactionId' => $transactionId,
            'surname' => $applicant->last_name,
            'forename' => $applicant->first_name,
            'sex' => $applicant->gender,
            'dateOfBirth' => $dateOfBirth,
            'nationality' => $applicant->nationality_code,
            'documentNumber' => $applicant->passport_number,
            'documentType' => 'P',
            'documentExpiry' => $documentExpiry,
            'callbackUrl' => $callbackUrl,
        ];

        $response = $this->client->post('/api/v1/nominal-check', $payload);

        if ($response->status() !== 202) {
            $this->throwFromResponse($response);
        }

        $responseData = $response->json();
        $this->storeAuditRecord($application, 'nominal_check_submitted', $payload, $responseData ?? []);

        $this->logger->info('Aeropass nominal check submitted (202)', [
            'application_id' => $application->id,
            'transaction_id' => $transactionId,
        ]);

        return array_merge($responseData ?? [], ['transaction_id' => $transactionId]);
    }

    /**
     * E-Visa Record Check (SYNCHRONOUS). POST /api/v1/evisa-record-check.
     * Shorter timeout; do not block submission on failure.
     */
    public function checkExistingVisaRecord(Application $application): array
    {
        $applicant = $this->getApplicantData($application);
        $dateOfBirth = $this->normaliseDateForAeropass($applicant->date_of_birth);

        $payload = [
            'documentNumber' => $applicant->passport_number,
            'nationality' => $applicant->nationality_code,
            'dateOfBirth' => $dateOfBirth,
        ];

        $response = $this->client->timeout(15)->post('/api/v1/evisa-record-check', $payload);
        $responseData = $response->json() ?? [];
        $this->storeAuditRecord($application, 'evisa_record_check', $payload, $responseData);

        if ($response->failed()) {
            Log::warning('Aeropass e-visa record check failed', [
                'application_id' => $application->id,
                'status' => $response->status(),
            ]);
            return ['status' => 'check_unavailable', 'previous_visas' => []];
        }

        return $responseData;
    }

    /**
     * ICAO audit: store all Aeropass request/response (encrypted).
     */
    private function storeAuditRecord(Application $application, string $type, array $request, array $response): void
    {
        AeropassAuditLog::create([
            'application_id' => $application->id,
            'interaction_type' => $type,
            'request_payload' => encrypt(json_encode($this->sanitiseForAudit($request))),
            'response_payload' => encrypt(json_encode($response)),
            'performed_at' => now(),
        ]);
    }

    /**
     * Mask sensitive fields in audit logs.
     */
    private function sanitiseForAudit(array $payload): array
    {
        $out = $payload;
        if (isset($out['documentNumber']) && is_string($out['documentNumber'])) {
            $out['documentNumber'] = '***' . substr($out['documentNumber'], -3);
        }
        if (isset($out['passport_number'])) {
            $out['passport_number'] = '***' . substr((string) $out['passport_number'], -3);
        }
        return $out;
    }

    private function throwFromResponse(\Illuminate\Http\Client\Response $response): void
    {
        $status = $response->status();
        $body = $response->body();
        if ($status >= 400 && $status < 500) {
            throw new AeropassSubmissionException("Aeropass returned {$status}: " . $body, $status);
        }
        throw AeropassApiException::fromResponse($status, $body);
    }

    // ----- Legacy / compatibility (existing callers) -----

    public function generateUniqueReferenceId(Application $application): string
    {
        return 'EVISA-' . $application->id . '-' . time();
    }

    /**
     * Legacy: submit Interpol check (sync flow using InterpolCheck model).
     */
    public function submitInterpolCheck(Application $application): InterpolCheck
    {
        $uniqueReferenceId = $this->generateUniqueReferenceId($application);
        $interpolCheck = InterpolCheck::updateOrCreate(
            ['application_id' => $application->id],
            [
                'unique_reference_id' => $uniqueReferenceId,
                'first_name' => $application->first_name,
                'surname' => $application->last_name,
                'date_of_birth' => $application->date_of_birth ? Carbon::parse($application->date_of_birth) : null,
                'status' => 'pending',
                'retry_count' => 0,
            ]
        );

        try {
            $payload = [
                'uniqueReferenceId' => $uniqueReferenceId,
                'firstName' => $application->first_name,
                'surname' => $application->last_name,
                'dateOfBirth' => $this->normaliseDateForAeropass($application->date_of_birth ? Carbon::parse($application->date_of_birth) : null),
            ];
            $response = $this->client->post($this->baseUrl . '/aeropass/e-visa/interpol-nominal-verification', $payload);
            if ($response->successful()) {
                $interpolCheck->update(['retry_count' => 0]);
                return $interpolCheck;
            }
            $interpolCheck->update(['status' => 'failed', 'last_error' => $response->body()]);
        } catch (Exception $e) {
            $interpolCheck->update(['status' => 'failed', 'last_error' => $e->getMessage()]);
        }
        return $interpolCheck;
    }

    public function processInterpolCallback(array $callbackData): array
    {
        $uniqueReferenceId = $callbackData['uniqueReferenceId'] ?? null;
        if (!$uniqueReferenceId) {
            return ['success' => false, 'error' => 'Missing uniqueReferenceId', 'response_code' => '400'];
        }
        $interpolCheck = InterpolCheck::where('unique_reference_id', $uniqueReferenceId)->first();
        if (!$interpolCheck) {
            return ['success' => false, 'error' => 'Interpol check record not found', 'response_code' => '400'];
        }
        $isMatched = strtolower($callbackData['interpolNominalMatched'] ?? '') === 'yes';
        $interpolCheck->update([
            'status' => $isMatched ? 'matched' : 'no_match',
            'interpol_nominal_matched' => $isMatched,
            'aeropass_response' => $callbackData,
            'callback_received_at' => now(),
        ]);
        if ($isMatched) {
            $interpolCheck->application?->update(['watchlist_flagged' => true, 'watchlist_reason' => 'Interpol nominal match detected']);
        }
        return ['success' => true, 'response_code' => '200'];
    }

    public function processEVisaCheck(array $requestData): array
    {
        $application = $this->findMatchingApplication($requestData);
        if (!$application) {
            return [
                'success' => false,
                'error' => 'No matching E-Visa found',
                'response_code' => 404,
                'data' => ['uniqueReferenceId' => $requestData['uniqueReferenceId'] ?? '', 'errorMessage' => 'No matching E-Visa found'],
            ];
        }
        return [
            'success' => true,
            'response_code' => 200,
            'data' => $this->buildEVisaResponse($application, $requestData['uniqueReferenceId'] ?? ''),
        ];
    }

    private function findMatchingApplication(array $requestData): ?Application
    {
        $firstName = trim($requestData['firstName'] ?? '');
        $lastName = trim($requestData['surname'] ?? '');
        $nationality = trim($requestData['nationality'] ?? '');
        $passportNumber = trim($requestData['travelDocNumber'] ?? '');
        $query = Application::withoutGlobalScopes()->where('status', 'issued')
            ->where('first_name', 'like', '%' . addcslashes($firstName, '%_\\') . '%')
            ->where('last_name', 'like', '%' . addcslashes($lastName, '%_\\') . '%')
            ->where('nationality', $nationality)
            ->where('passport_number', $passportNumber);
        if (!empty($requestData['dateOfBirth'])) {
            $dob = Carbon::parse($requestData['dateOfBirth'])->format('Y-m-d');
            $query->where('date_of_birth', $dob);
        }
        return $query->first();
    }

    private function buildEVisaResponse(Application $application, string $uniqueReferenceId): array
    {
        return [
            'uniqueReferenceId' => $uniqueReferenceId,
            'firstName' => $application->first_name,
            'surname' => $application->last_name,
            'dateOfBirth' => $this->normaliseDateForAeropass($application->date_of_birth),
            'nationality' => $application->nationality,
            'travelDocNumber' => $application->passport_number,
            'visaType' => $application->visaType?->name ?? 'UNKNOWN',
            'emailAddress' => $application->email,
            'contactNumber' => ($application->phone_code ?? '') . ($application->phone ?? ''),
            'plannedArrivalDate' => $application->intended_arrival ? Carbon::parse($application->intended_arrival)->format('Y-m-d') : null,
            'purposeOfVisit' => $application->purpose_of_visit ?? '',
            'errorMessage' => null,
        ];
    }
}
