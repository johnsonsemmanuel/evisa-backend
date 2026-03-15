<?php

namespace App\Providers;

use App\Models\Application;
use App\Models\BoardingAuthorization;
use App\Models\Payment;
use App\Models\RefundRequest;
use App\Models\User;
use App\Models\VisaType;
use App\Policies\ApplicationPolicy;
use App\Policies\BorderVerificationPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\RefundPolicy;
use App\Policies\UserPolicy;
use App\Policies\VisaTypePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Application::class => ApplicationPolicy::class,
        Payment::class => PaymentPolicy::class,
        RefundRequest::class => RefundPolicy::class,
        User::class => UserPolicy::class,
        VisaType::class => VisaTypePolicy::class,
        BoardingAuthorization::class => BorderVerificationPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
