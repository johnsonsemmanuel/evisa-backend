<?php

namespace App\Jobs;

use App\Models\ApplicationDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ValidateDocumentOcr implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $backoff = 60;

    public function __construct(
        public ApplicationDocument $document,
    ) {
        $this->onQueue('ocr');
    }

    /**
     * Async OCR validation for uploaded documents.
     * In production, this would call an external OCR service API.
     */
    public function handle(): void
    {
        Log::info("Starting OCR validation for document {$this->document->id} ({$this->document->document_type})");

        $this->document->update(['ocr_status' => 'processing']);

        try {
            // Check file exists
            if (!Storage::disk('secure')->exists($this->document->stored_path)) {
                throw new \RuntimeException('Document file not found in storage.');
            }

            // Placeholder: In production, send file to OCR API (e.g., Google Vision, AWS Textract)
            // $ocrResult = $this->callOcrService($this->document->stored_path);
            $ocrResult = $this->simulateOcr();

            if ($ocrResult['readable']) {
                $this->document->update([
                    'ocr_status' => 'passed',
                    'ocr_result' => json_encode($ocrResult),
                ]);
                Log::info("OCR passed for document {$this->document->id}");
            } else {
                $this->document->update([
                    'ocr_status'          => 'failed',
                    'ocr_result'          => json_encode($ocrResult),
                    'verification_status' => 'reupload_requested',
                    'rejection_reason'    => 'Document is not readable. Please re-upload a clearer copy.',
                ]);

                // Notify applicant to re-upload
                $application = $this->document->application;
                SendNotification::dispatch(
                    $application,
                    'document_reupload_required',
                    [
                        'document_type' => $this->document->document_type,
                        'reason'        => 'Document failed readability check.',
                    ]
                );

                Log::warning("OCR failed for document {$this->document->id}: not readable");
            }
        } catch (\Throwable $e) {
            $this->document->update([
                'ocr_status' => 'failed',
                'ocr_result' => json_encode(['error' => $e->getMessage()]),
            ]);
            Log::error("OCR error for document {$this->document->id}: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Simulate OCR result for development/testing.
     */
    private function simulateOcr(): array
    {
        return [
            'readable'   => true,
            'confidence' => 0.95,
            'text'       => 'Simulated OCR text output',
            'provider'   => 'simulation',
        ];
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("OCR job permanently failed for document {$this->document->id}: {$exception->getMessage()}");

        $this->document->update([
            'ocr_status' => 'skipped',
            'ocr_result' => json_encode(['error' => 'OCR processing failed after retries']),
        ]);
    }
}
