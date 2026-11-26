<?php

namespace App\Providers;

use App\Domains\Encounters\Events\ConsultationCompleted;
use App\Domains\Encounters\Events\ConsultationStarted;
use App\Domains\Patients\Events\PatientRegistered;
use App\Domains\Scheduling\Events\AppointmentBooked;
use App\Domains\Scheduling\Events\PatientCheckedIn;
use App\Domains\Scheduling\Events\QueueAssigned;
use App\Listeners\RecordWorkflowEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
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
        if (config('app.force_https')) {
            URL::forceScheme('https');
        }

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        foreach ([
            PatientRegistered::class,
            AppointmentBooked::class,
            PatientCheckedIn::class,
            QueueAssigned::class,
            ConsultationStarted::class,
            ConsultationCompleted::class,
        ] as $event) {
            Event::listen($event, RecordWorkflowEvent::class);
        }
    }
}
