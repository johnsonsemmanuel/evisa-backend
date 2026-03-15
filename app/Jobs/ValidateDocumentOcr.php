<?php

namespace App\Jobs;

use App\Models\ApplicationDocument;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ValidateDocumentOcr extends BaseJob
{
    use SerializesModels;

    public function __construct(
        public ApplicationDocument $document,
    ) {
        $this->onQueue('default');
    }

    protected function getApplicationId(): ?int
    {
        return $this->document->application?->id;
    }

    protected function getApplication(): ?\App\Models\Application
    {
        return $this->document->application;
    }

    public function handle(): void
    {
        Log::info("Starting OCR validation for document {$this->document->id} ({$this->document->document_type})");

        $this->document->update(['ocr_status' => 'processing']);

        $path = $this->document->stored_path;
        if (!Storage::disk('secure')->exists($path)) {
            throw new \RuntimeException('Document file not found in storage.');
        }

        $ocrResult = $this->simulateOcr();

        if ($ocrResult['readable']) {
            $this->document->update([
                'ocr_status' => 'passed',
                'ocr_result' => json_encode($ocrResult),
            ]);
            Log::info("OCR passed for document {$this->document->id}");
        } else {
            $this->document->update([
                'ocr_status' => 'failed',
                'ocr_result' => json_encode($ocrResult),
                'verification_status' => 'reupload_requested',
                'rejection_reason' => 'Document is not readable. Please re-upload a clearer copy.',
            ]);

            $application = $this->document->application;
            if ($application) {
                SendNotification::dispatch(
                    $application,
                    'document_reupload_required',
                    [
                        'document_type' => $this->document->document_type,
                        'reason' => 'Document failed readability check.',
                    ]
                )->onQueue('default');
            }

            Log::warning("OCR failed for document {$this->document->id}: not readable");
        }
    }

    private function simulateOcr(): array
    {
        return [
            'readable' => true,
            'confidence' => 0.95,
            'text' => 'Simulated OCR text output',
            'provider' => 'simulation',
        ];
    }

    protected function handleApplicationFailure(\App\Models\Application $application, Throwable $exception): void
    {
        $this->document->update([
            'ocr_status' => 'skipped',
            'ocr_result' => json_encode(['error' => 'OCR processing failed after retries']),
        ]);
    }
}
