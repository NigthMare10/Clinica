<?php

namespace App\Providers;

use App\Models\Doctor;
use App\Models\Patient;
use App\Models\PdfTemplate;
use App\Models\Setting;
use App\Models\SitePage;
use App\Models\Specialty;
use App\Policies\AdminResourcePolicy;
use App\Policies\PatientPolicy;
use App\Policies\ReferenceResourcePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Specialty::class, AdminResourcePolicy::class);
        Gate::policy(PdfTemplate::class, AdminResourcePolicy::class);
        Gate::policy(Setting::class, AdminResourcePolicy::class);
        Gate::policy(SitePage::class, AdminResourcePolicy::class);
        Gate::policy(Doctor::class, ReferenceResourcePolicy::class);
        Gate::policy(Patient::class, PatientPolicy::class);
        Vite::prefetch(concurrency: 3);
    }
}
