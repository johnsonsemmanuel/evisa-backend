<?php

namespace App\Services;

use App\Models\Application;
use App\Models\InterpolCheck;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class AeropassService
{
    private string $baseUrl;
    private string $username;
    private string $password;
    private int $timeout;
    private int $retryDelay;
    private int $maxRetries;

    public function __construct()
    {
        $this->baseUrl = config('services.aeropass.base_url');
        $this->username = config('services.aeropass.username');
        $this->password = config('services.aeropass.password');
        $this->timeout = config('services.aeropass.timeout', 20);
        $this->retryDelay = config('services.aeropass.retry_delay', 2);
        $this->maxRetries = config('services.aeropass.max_retries', 3);
    }

    /**
     * Submit traveler details for Interpol nominal verification
     */
    public function submitInterpolCheck(Application $application): InterpolCheck
    {
        $uniqueReferenceId = $this->generateUniqueReferenceId($application);

        // Create or update Interpol check record
        $interpolCheck = InterpolCheck::updateOrCreate(
            ['application_id' => $application->id],
            [
                'unique_reference_id' => $uniqueReferenceId,
                'first_name' => $application->first_name,
                'surname' => $application->last_name,
                'date_of_birth' => $application->date_of_birth,
                'status' => 'pending',
                'retry_count' => 0,
            ]
        );

        try {
            $response = $this->sendInterpolRequest($interpolCheck);
            
            if ($response['success']) {
                Log::info('Interpol check submitted successfully', [
                    'application_id' => $application->id,
                    'unique_reference_id' => $uniqueReferenceId,
                    'response_code' => $response['data']['responseCode'] ?? null,
                ]);
            } else {
                $interpolCheck->update([
                    'status' => 'failed',
                    'last_error' => $response['error'] ?? 'Unknown error',
                ]);
                
                Log::error('Interpol check submission failed', [
                    'application_id' => $application->id,
                    'error' => $response['error'] ?? 'Unknown error',
                ]);
            }
        } catch (Exception $e) {
            $interpolCheck->update([
                'status' => 'failed',
                'last_error' => $e->getMessage(),
            ]);
            
            Log::error('Interpol check submission exception', [
                'application_id' => $application->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $interpolCheck;
    }

    /**
     * Send Interpol verification request to Aeropass
     */
    private function sendInterpolRequest(InterpolCheck $interpolCheck): array
    {
        $payload = [
            'uniqueReferenceId' => $interpolCheck->unique_reference_id,
            'firstName' => $interpolCheck->first_name,
            'surname' => $interpolCheck->surname,
            'dateOfBirth' => $interpolCheck->date_of_birth->format('d/m/Y'),
        ];

        $headers = [
            'Authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->password),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        $retryCount = 0;
        
        while ($retryCount <= $this->maxRetries) {
            try {
                $response = Http::withHeaders($headers)
                    ->timeout($this->timeout)
                    ->post($this->baseUrl . '/aeropass/e-visa/interpol-nominal-verification', $payload);

                if ($response->successful()) {
                    $data = $response->json();
                    
                    // Update retry count
                    $interpolCheck->update(['retry_count' => $retryCount]);
                    
                    return [
                        'success' => true,
                        'data' => $data,
                    ];
                } else {
                    $error = "HTTP {$response->status()}: " . $response->body();
                    
                    if ($retryCount >= $this->maxRetries) {
                        return [
                            'success' => false,
                            'error' => $error,
                        ];
                    }
                }
            } catch (Exception $e) {
                if ($retryCount >= $this->maxRetries) {
                    return [
                        'success' => false,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            $retryCount++;
            if ($retryCount <= $this->maxRetries) {
                sleep($this->retryDelay);
            }
        }

        return [
            'success' => false,
            'error' => 'Max retries exceeded',
        ];
    }

    /**
     * Process callback from Aeropass with Interpol check results
     */
    public function processInterpolCallback(array $callbackData): array
    {
        try {
            $uniqueReferenceId = $callbackData['uniqueReferenceId'] ?? null;
            
            if (!$uniqueReferenceId) {
                return [
                    'success' => false,
                    'error' => 'Missing uniqueReferenceId',
                    'response_code' => '400',
                ];
            }

            $interpolCheck = InterpolCheck::where('unique_reference_id', $uniqueReferenceId)->first();
            
            if (!$interpolCheck) {
                return [
                    'success' => false,
                    'error' => 'Interpol check record not found',
                    'response_code' => '400',
                ];
            }

            // Validate callback data
            $requiredFields = ['firstName', 'surname', 'dateOfBirth', 'interpolNominalMatched'];
            foreach ($requiredFields as $field) {
                if (!isset($callbackData[$field])) {
                    return [
                        'success' => false,
                        'error' => "Missing mandatory field: {$field}",
                        'response_code' => '400',
                    ];
                }
            }

            // Update Interpol check record
            $isMatched = strtolower($callbackData['interpolNominalMatched']) === 'yes';
            
            $interpolCheck->update([
                'status' => $isMatched ? 'matched' : 'no_match',
                'interpol_nominal_matched' => $isMatched,
                'aeropass_response' => $callbackData,
                'callback_received_at' => now(),
            ]);

            // Update application status if there's a match
            if ($isMatched) {
                $application = $interpolCheck->application;
                $application->update([
                    'watchlist_flagged' => true,
                    'watchlist_reason' => 'Interpol nominal match detected',
                ]);

                Log::warning('Interpol match detected', [
                    'application_id' => $application->id,
                    'unique_reference_id' => $uniqueReferenceId,
                    'applicant_name' => $callbackData['firstName'] . ' ' . $callbackData['surname'],
                ]);
            }

            Log::info('Interpol callback processed successfully', [
                'unique_reference_id' => $uniqueReferenceId,
                'matched' => $isMatched,
            ]);

            return [
                'success' => true,
                'response_code' => '200',
            ];

        } catch (Exception $e) {
            Log::error('Interpol callback processing failed', [
                'error' => $e->getMessage(),
                'callback_data' => $callbackData,
            ]);

            return [
                'success' => false,
                'error' => 'Internal server error',
                'response_code' => '500',
            ];
        }
    }

    /**
     * Handle E-Visa check request from Aeropass
     */
    public function processEVisaCheck(array $requestData): array
    {
        try {
            // Validate required fields
            $requiredFields = ['uniqueReferenceId', 'firstName', 'surname', 'dateOfBirth', 'nationality', 'travelDocNumber'];
            foreach ($requiredFields as $field) {
                if (!isset($requestData[$field])) {
                    return [
                        'success' => false,
                        'error' => "Missing mandatory field: {$field}",
                        'response_code' => 400,
                    ];
                }
            }

            // Search for matching application
            $application = $this->findMatchingApplication($requestData);

            if (!$application) {
                return [
                    'success' => false,
                    'error' => 'No matching E-Visa found',
                    'response_code' => 404,
                    'data' => [
                        'uniqueReferenceId' => $requestData['uniqueReferenceId'],
                        'errorMessage' => 'No matching E-Visa found for the provided traveler details',
                    ],
                ];
            }

            // Build response data
            $responseData = $this->buildEVisaResponse($application, $requestData['uniqueReferenceId']);

            Log::info('E-Visa check processed successfully', [
                'unique_reference_id' => $requestData['uniqueReferenceId'],
                'application_id' => $application->id,
                'passport_number' => $requestData['travelDocNumber'],
            ]);

            return [
                'success' => true,
                'response_code' => 200,
                'data' => $responseData,
            ];

        } catch (Exception $e) {
            Log::error('E-Visa check processing failed', [
                'error' => $e->getMessage(),
                'request_data' => $requestData,
            ]);

            return [
                'success' => false,
                'error' => 'Internal server error',
                'response_code' => 500,
                'data' => [
                    'uniqueReferenceId' => $requestData['uniqueReferenceId'] ?? '',
                    'errorMessage' => 'Internal server error occurred while processing the request',
                ],
            ];
        }
    }

    /**
     * Find matching application based on traveler details
     */
    private function findMatchingApplication(array $requestData): ?Application
    {
        $query = Application::where('status', 'issued')
            ->where('first_name', 'LIKE', '%' . $requestData['firstName'] . '%')
            ->where('last_name', 'LIKE', '%' . $requestData['surname'] . '%')
            ->where('nationality', $requestData['nationality'])
            ->where('passport_number', $requestData['travelDocNumber']);

        // Try exact date match first
        if (isset($requestData['dateOfBirth'])) {
            $dateOfBirth = \Carbon\Carbon::createFromFormat('Y-m-d', $requestData['dateOfBirth']);
            $query->where('date_of_birth', $dateOfBirth->format('Y-m-d'));
        }

        return $query->with(['visaType', 'documents'])->first();
    }

    /**
     * Build E-Visa response data
     */
    private function buildEVisaResponse(Application $application, string $uniqueReferenceId): array
    {
        // Get passport photo and copy (Base64 encoded)
        $passportPhoto = $this->getDocumentBase64($application, 'passport_bio');
        $passportCopy = $this->getDocumentBase64($application, 'passport_bio');
        
        // Get supporting documents
        $supportingDocs = $this->getSupportingDocuments($application);

        // Calculate planned departure date (arrival + duration)
        $plannedDepartureDate = null;
        if ($application->intended_arrival && $application->duration_days) {
            $plannedDepartureDate = \Carbon\Carbon::parse($application->intended_arrival)
                ->addDays($application->duration_days)
                ->format('Y-m-d');
        }

        return [
            'uniqueReferenceId' => $uniqueReferenceId,
            'firstName' => $application->first_name,
            'surname' => $application->last_name,
            'dateOfBirth' => $application->date_of_birth ? $application->date_of_birth->format('Y-m-d') : null,
            'nationality' => $application->nationality,
            'travelDocNumber' => $application->passport_number,
            'visaType' => $application->visaType?->name ?? 'UNKNOWN',
            'emailAddress' => $application->email,
            'contactNumber' => $application->phone_code . $application->phone,
            'plannedArrivalDate' => $application->intended_arrival ? \Carbon\Carbon::parse($application->intended_arrival)->format('Y-m-d') : null,
            'plannedDepartureDate' => $plannedDepartureDate,
            'purposeOfVisit' => $application->purpose_of_visit ?? $application->visaType?->name ?? 'Not specified',
            'passportPhoto' => $passportPhoto,
            'passportCopy' => $passportCopy,
            'supportingDocs' => $supportingDocs,
            'errorMessage' => null,
        ];
    }

    /**
     * Get document as Base64 encoded string
     */
    private function getDocumentBase64(Application $application, string $documentType): ?string
    {
        $document = $application->documents()
            ->where('document_type', $documentType)
            ->where('verification_status', 'verified')
            ->first();

        if (!$document) {
            return null;
        }

        $filePath = storage_path('app/' . $document->stored_path);
        
        if (!file_exists($filePath)) {
            return null;
        }

        return base64_encode(file_get_contents($filePath));
    }

    /**
     * Get supporting documents as Base64 encoded array
     */
    private function getSupportingDocuments(Application $application): array
    {
        $supportingDocs = [];
        
        $documents = $application->documents()
            ->whereNotIn('document_type', ['passport_bio', 'photo'])
            ->where('verification_status', 'verified')
            ->get();

        foreach ($documents as $document) {
            $filePath = storage_path('app/' . $document->stored_path);
            
            if (file_exists($filePath)) {
                $supportingDocs[] = base64_encode(file_get_contents($filePath));
            }
        }

        return $supportingDocs;
    }

    /**
     * Generate unique reference ID for Interpol check
     */
    private function generateUniqueReferenceId(Application $application): string
    {
        return 'EVISA-' . $application->id . '-' . time();
    }
}