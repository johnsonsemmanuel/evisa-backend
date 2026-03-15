<?php

namespace App\Jobs;

use App\Models\Application;
use App\Services\EVisaPdfService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateEVisaPdf extends CriticalJob implements ShouldBeUnique
{
    use SerializesModels;

    public int $uniqueFor = 3600;

    public function __construct(
        public Application $application,
    ) {
        $this->onQueue('critical');
    }

    public function uniqueId(): string
    {
        return 'evisa:' . $this->application->id;
    }

    protected function getApplicationId(): ?int
    {
        return $this->application->id;
    }

    protected function getApplication(): ?Application
    {
        return $this->application;
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
        )->onQueue('default');
    }

    protected function handleApplicationFailure(Application $application, Throwable $exception): void
    {
        Log::error("eVisa PDF generation permanently failed for application {$application->id}", [
            'exception' => $exception->getMessage(),
        ]);
        // Optionally update application or notify officer for manual PDF generation
    }
}
