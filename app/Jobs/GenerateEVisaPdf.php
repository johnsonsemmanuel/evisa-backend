<?php

namespace App\Jobs;

use App\Models\Application;
use App\Services\EVisaPdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateEVisaPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public Application $application,
    ) {
        $this->onQueue('pdf');
    }

    public function handle(EVisaPdfService $pdfService): void
    {
        Log::info("Generating eVisa PDF for application {$this->application->reference_number}");

        $path = $pdfService->generate($this->application);

        Log::info("eVisa PDF generated at {$path}");

        SendNotification::dispatch(
            $this->application,
            'application_approved',
            ['evisa_path' => $path]
        );
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Failed to generate eVisa PDF for {$this->application->reference_number}: {$exception->getMessage()}");
    }
}
