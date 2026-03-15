<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendEmailVerification extends BaseJob
{
    use SerializesModels;

    public function __construct(
        public User $user,
    ) {
        $this->onQueue('default');
    }

    protected function getUserId(): ?int
    {
        return $this->user->id;
    }

    public function handle(): void
    {
        $verificationUrl = config('app.frontend_url', config('app.url')) . '/verify-email?token=' . $this->user->email_verification_token;

        Log::info('Email verification link for ' . $this->user->email . ': ' . $verificationUrl);

        if (config('mail.default') !== 'log') {
            Mail::send('emails.verify-email', [
                'user' => $this->user,
                'verificationUrl' => $verificationUrl,
            ], function ($message) {
                $message->to($this->user->email, $this->user->first_name . ' ' . $this->user->last_name)
                    ->subject('Verify your GH-eVISA account');
            });
        }
    }
}
