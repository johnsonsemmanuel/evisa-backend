<?php

namespace App\Jobs;

use App\Models\Application;
use App\Notifications\AeropassCheckFailedNotification;
use App\Services\AeropassService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;
use Throwable;

class ProcessAeropassNominalCheck extends BaseJob implements ShouldBeUnique
{
    use SerializesModels;

    public int $tries = 3;
    public array $backoff = [300, 900, 3600]; // 5min, 15min, 1hr
    public int $timeout = 60;

    public int $uniqueFor = 3600;

    public function __construct(
        public Application $application
    ) {
        $this->onQueue('critical');
    }

    public function uniqueId(): string
    {
        return 'aeropass:nominal:' . $this->application->id;
    }

    protected function getApplicationId(): ?int
    {
        return $this->application->id;
    }

    protected function getApplication(): ?Application
    {
        return $this->application->exists ? $this->application->fresh() : null;
    }

    public function handle(AeropassService $aeropass): void
    {
        $this->application->update(['aeropass_status' => 'checking']);

        $response = $aeropass->submitNominalCheck(
            application: $this->application,
            callbackUrl: route('aeropass.callback')
        );

        $this->application->update([
            'aeropass_transaction_ref' => $response['transaction_id'],
            'aeropass_submitted_at' => now(),
        ]);
    }

    public function failed(Throwable $e): void
    {
        $app = $this->getApplication();
        if ($app) {
            $app->update(['aeropass_status' => 'check_failed']);
            $officer = $app->assignedOfficer;
            if ($officer) {
                Notification::send($officer, new AeropassCheckFailedNotification($app));
            }
        }
        parent::failed($e);
    }
}
