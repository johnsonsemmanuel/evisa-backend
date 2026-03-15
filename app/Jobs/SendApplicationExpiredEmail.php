<?php

namespace App\Jobs;

use App\Models\Application;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendApplicationExpiredEmail extends BaseJob
{
    use SerializesModels;

    public function __construct(
        public Application $application
    ) {
        $this->onQueue('default');
    }

    protected function getApplicationId(): ?int
    {
        return $this->application->id;
    }

    public function handle(): void
    {
        $user = $this->application->user;
        if (!$user || !$user->email) {
            Log::warning('Cannot send application expired email - no user or email', [
                'application_id' => $this->application->id,
            ]);
            return;
        }

        $mailView = 'emails.application-expired';
        if (!view()->exists($mailView)) {
            Mail::raw(
                "Your Ghana eVisa application {$this->application->reference_number} has expired because payment was not completed within 72 hours. You may submit a new application.",
                function ($message) use ($user) {
                    $message->to($user->email)
                        ->subject('Ghana eVisa application expired - ' . $this->application->reference_number)
                        ->from(config('mail.from.address'), config('mail.from.name'));
                }
            );
        } else {
            Mail::send($mailView, ['application' => $this->application, 'user' => $user], function ($message) use ($user) {
                $message->to($user->email)
                    ->subject('Ghana eVisa application expired - ' . $this->application->reference_number)
                    ->from(config('mail.from.address'), config('mail.from.name'));
            });
        }

        Log::info('Application expired email sent', [
            'application_id' => $this->application->id,
            'reference_number' => $this->application->reference_number,
            'email' => $user->email,
        ]);
    }
}
