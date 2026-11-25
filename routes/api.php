<?php

use App\Http\Controllers\Api\V1\AppointmentController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\EncounterController;
use App\Http\Controllers\Api\V1\MetaController;
use App\Http\Controllers\Api\V1\PatientController;
use App\Http\Controllers\Api\V1\QueueController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SettingController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    /*
    |--------------------------------------------------------------------------
    | Public endpoints
    |--------------------------------------------------------------------------
    */
    Route::get('public/meta', [MetaController::class, 'publicMeta'])->name('api.public.meta');
    Route::get('public/queues/{branch}', [QueueController::class, 'publicBoard'])->name('api.public.queues');

    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('api.auth.login');

    /*
    |--------------------------------------------------------------------------
    | Authenticated endpoints
    |--------------------------------------------------------------------------
    */
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me'])->name('api.auth.me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('api.auth.logout');
        Route::patch('auth/preferences', [AuthController::class, 'updatePreferences'])->name('api.auth.preferences');
        Route::post('auth/change-password', [AuthController::class, 'changePassword'])->name('api.auth.change-password');

        Route::get('meta/bootstrap', [MetaController::class, 'bootstrap'])->name('api.meta.bootstrap');

        Route::get('dashboard/summary', [DashboardController::class, 'summary'])
            ->middleware('permission:dashboard.view')
            ->name('api.dashboard.summary');

        /*
        |--------------------------------------------------------------------------
        | Patients
        |--------------------------------------------------------------------------
        */
        Route::get('patients', [PatientController::class, 'index'])
            ->middleware('permission:patients.view')
            ->name('api.patients.index');
        Route::post('patients', [PatientController::class, 'store'])
            ->middleware('permission:patients.create')
            ->name('api.patients.store');
        Route::post('patients/check-duplicates', [PatientController::class, 'duplicates'])
            ->middleware('permission:patients.create')
            ->name('api.patients.duplicates');
        Route::get('patients/{patient}', [PatientController::class, 'show'])
            ->middleware('permission:patients.view')
            ->name('api.patients.show');
        Route::match(['put', 'patch'], 'patients/{patient}', [PatientController::class, 'update'])
            ->middleware('permission:patients.update')
            ->name('api.patients.update');
        Route::delete('patients/{patient}', [PatientController::class, 'destroy'])
            ->middleware('permission:patients.merge')
            ->name('api.patients.destroy');
        Route::get('patients/{patient}/timeline', [PatientController::class, 'timeline'])
            ->middleware('permission:patients.view')
            ->name('api.patients.timeline');
        Route::post('patients/{patient}/merge', [PatientController::class, 'merge'])
            ->middleware('permission:patients.merge')
            ->name('api.patients.merge');
        Route::post('patients/{patient}/allergies', [PatientController::class, 'storeAllergy'])
            ->middleware('permission:patients.update')
            ->name('api.patients.allergies.store');
        Route::delete('patients/{patient}/allergies/{allergy}', [PatientController::class, 'destroyAllergy'])
            ->middleware('permission:patients.update')
            ->name('api.patients.allergies.destroy');
        Route::post('patients/{patient}/conditions', [PatientController::class, 'storeCondition'])
            ->middleware('permission:patients.update')
            ->name('api.patients.conditions.store');
        Route::delete('patients/{patient}/conditions/{condition}', [PatientController::class, 'destroyCondition'])
            ->middleware('permission:patients.update')
            ->name('api.patients.conditions.destroy');

        /*
        |--------------------------------------------------------------------------
        | Appointments
        |--------------------------------------------------------------------------
        */
        Route::get('appointments', [AppointmentController::class, 'index'])
            ->middleware('permission:appointments.view')
            ->name('api.appointments.index');
        Route::get('appointments/summary', [AppointmentController::class, 'summary'])
            ->middleware('permission:appointments.view')
            ->name('api.appointments.summary');
        Route::post('appointments', [AppointmentController::class, 'store'])
            ->middleware('permission:appointments.create')
            ->name('api.appointments.store');
        Route::get('appointments/{appointment}', [AppointmentController::class, 'show'])
            ->middleware('permission:appointments.view')
            ->name('api.appointments.show');
        Route::match(['put', 'patch'], 'appointments/{appointment}', [AppointmentController::class, 'update'])
            ->middleware('permission:appointments.manage')
            ->name('api.appointments.update');
        Route::post('appointments/{appointment}/check-in', [AppointmentController::class, 'checkIn'])
            ->middleware('permission:appointments.manage')
            ->name('api.appointments.check-in');
        Route::post('appointments/{appointment}/cancel', [AppointmentController::class, 'cancel'])
            ->middleware('permission:appointments.manage')
            ->name('api.appointments.cancel');
        Route::post('appointments/{appointment}/no-show', [AppointmentController::class, 'noShow'])
            ->middleware('permission:appointments.manage')
            ->name('api.appointments.no-show');

        /*
        |--------------------------------------------------------------------------
        | Queues
        |--------------------------------------------------------------------------
        */
        Route::get('queues', [QueueController::class, 'index'])
            ->middleware('permission:queues.view')
            ->name('api.queues.index');
        Route::post('queues', [QueueController::class, 'store'])
            ->middleware('permission:queues.manage')
            ->name('api.queues.store');
        Route::post('queues/next', [QueueController::class, 'next'])
            ->middleware('permission:queues.manage')
            ->name('api.queues.next');
        Route::post('queues/{ticket}/call', [QueueController::class, 'call'])
            ->middleware('permission:queues.manage')
            ->name('api.queues.call');
        Route::post('queues/{ticket}/start', [QueueController::class, 'start'])
            ->middleware('permission:queues.manage')
            ->name('api.queues.start');
        Route::post('queues/{ticket}/complete', [QueueController::class, 'complete'])
            ->middleware('permission:queues.manage')
            ->name('api.queues.complete');
        Route::post('queues/{ticket}/skip', [QueueController::class, 'skip'])
            ->middleware('permission:queues.manage')
            ->name('api.queues.skip');
        Route::post('queues/{ticket}/no-show', [QueueController::class, 'noShow'])
            ->middleware('permission:queues.manage')
            ->name('api.queues.no-show');
        Route::post('queues/{ticket}/recall', [QueueController::class, 'recall'])
            ->middleware('permission:queues.manage')
            ->name('api.queues.recall');

        /*
        |--------------------------------------------------------------------------
        | Encounters / EMR
        |--------------------------------------------------------------------------
        */
        Route::get('encounters', [EncounterController::class, 'index'])
            ->middleware('permission:encounters.view')
            ->name('api.encounters.index');
        Route::post('encounters', [EncounterController::class, 'store'])
            ->middleware('permission:encounters.create')
            ->name('api.encounters.store');
        Route::get('encounters/{encounter}', [EncounterController::class, 'show'])
            ->middleware('permission:encounters.view')
            ->name('api.encounters.show');
        Route::match(['put', 'patch'], 'encounters/{encounter}', [EncounterController::class, 'update'])
            ->middleware('permission:encounters.update')
            ->name('api.encounters.update');
        Route::post('encounters/{encounter}/sign', [EncounterController::class, 'sign'])
            ->middleware('permission:encounters.sign')
            ->name('api.encounters.sign');
        Route::post('encounters/{encounter}/vitals', [EncounterController::class, 'storeVitals'])
            ->middleware('permission:vitals.create')
            ->name('api.encounters.vitals.store');
        Route::post('encounters/{encounter}/diagnoses', [EncounterController::class, 'storeDiagnosis'])
            ->middleware('permission:diagnoses.create')
            ->name('api.encounters.diagnoses.store');
        Route::post('encounters/{encounter}/notes', [EncounterController::class, 'storeNote'])
            ->middleware('permission:clinical_notes.create')
            ->name('api.encounters.notes.store');

        /*
        |--------------------------------------------------------------------------
        | Administration
        |--------------------------------------------------------------------------
        */
        Route::get('users', [UserController::class, 'index'])
            ->middleware('permission:users.manage')
            ->name('api.users.index');
        Route::post('users', [UserController::class, 'store'])
            ->middleware('permission:users.manage')
            ->name('api.users.store');
        Route::get('users/{user}', [UserController::class, 'show'])
            ->middleware('permission:users.manage')
            ->name('api.users.show');
        Route::match(['put', 'patch'], 'users/{user}', [UserController::class, 'update'])
            ->middleware('permission:users.manage')
            ->name('api.users.update');
        Route::post('users/{user}/deactivate', [UserController::class, 'deactivate'])
            ->middleware('permission:users.manage')
            ->name('api.users.deactivate');

        Route::get('roles', [RoleController::class, 'index'])
            ->middleware('permission:roles.manage')
            ->name('api.roles.index');
        Route::get('roles/{role}', [RoleController::class, 'show'])
            ->middleware('permission:roles.manage')
            ->name('api.roles.show');
        Route::put('roles/{role}/permissions', [RoleController::class, 'updatePermissions'])
            ->middleware('permission:roles.manage')
            ->name('api.roles.permissions');

        Route::get('audit-logs', [AuditLogController::class, 'index'])
            ->middleware('permission:audit.view')
            ->name('api.audit-logs.index');

        Route::get('settings', [SettingController::class, 'index'])
            ->middleware('permission:settings.manage')
            ->name('api.settings.index');
        Route::put('settings', [SettingController::class, 'update'])
            ->middleware('permission:settings.manage')
            ->name('api.settings.update');
    });
});
