<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendEmailVerification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public User $user,
    ) {}

    public function handle(): void
    {
        $verificationUrl = config('app.frontend_url') . '/verify-email?token=' . $this->user->email_verification_token;

        // In production, this would send an actual email
        // For now, log the verification URL
        Log::info('Email verification link for ' . $this->user->email . ': ' . $verificationUrl);

        // If mail is configured, send actual email
        if (config('mail.mailer') !== 'log') {
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
