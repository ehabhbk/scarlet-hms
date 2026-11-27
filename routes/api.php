<?php

use App\Http\Controllers\Api\V1\AccountingController;
use App\Http\Controllers\Api\V1\AppointmentController;
use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\PayrollController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ClinicalDeviceController;
use App\Http\Controllers\Api\V1\DeviceIngestController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\AdmissionController;
use App\Http\Controllers\Api\V1\BedController;
use App\Http\Controllers\Api\V1\EmployeeController;
use App\Http\Controllers\Api\V1\EncounterController;
use App\Http\Controllers\Api\V1\InsuranceContractController;
use App\Http\Controllers\Api\V1\LabDeviceController;
use App\Http\Controllers\Api\V1\LabDeviceMessageController;
use App\Http\Controllers\Api\V1\LabTestParameterController;
use App\Http\Controllers\Api\V1\MaternityController;
use App\Http\Controllers\Api\V1\NurseryController;
use App\Http\Controllers\Api\V1\SurgeryController;
use App\Http\Controllers\Api\V1\WardController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\InsuranceClaimController;
use App\Http\Controllers\Api\V1\InsuranceProviderController;
use App\Http\Controllers\Api\V1\LabOrderController;
use App\Http\Controllers\Api\V1\LabOrderItemController;
use App\Http\Controllers\Api\V1\LabTestController;
use App\Http\Controllers\Api\V1\MedicationController;
use App\Http\Controllers\Api\V1\PatientInsuranceController;
use App\Http\Controllers\Api\V1\PrescriptionController;
use App\Http\Controllers\Api\V1\RadiologyDeviceController;
use App\Http\Controllers\Api\V1\RadiologyDeviceMessageController;
use App\Http\Controllers\Api\V1\RadiologyOrderController;
use App\Http\Controllers\Api\V1\MetaController;
use App\Http\Controllers\Api\V1\PatientController;
use App\Http\Controllers\Api\V1\QueueController;
use App\Http\Controllers\Api\V1\RadiologyStudyController;
use App\Http\Controllers\Api\V1\ServiceController;
use App\Http\Controllers\Api\V1\ReportController;
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

    // Device push endpoints use the shared DEVICE_INGEST_TOKEN instead of a user session.
    Route::post('device-ingest/lab', [DeviceIngestController::class, 'lab'])
        ->name('api.device-ingest.lab');
    Route::post('device-ingest/radiology', [DeviceIngestController::class, 'radiology'])
        ->name('api.device-ingest.radiology');
    Route::post('device-ingest/clinical', [DeviceIngestController::class, 'clinical'])
        ->name('api.device-ingest.clinical');

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
        | Pharmacy
        |--------------------------------------------------------------------------
        */
        Route::get('medications', [MedicationController::class, 'index'])
            ->middleware('permission:prescriptions.view')
            ->name('api.medications.index');
        Route::post('medications', [MedicationController::class, 'store'])
            ->middleware('permission:prescriptions.create')
            ->name('api.medications.store');
        Route::get('medications/{medication}', [MedicationController::class, 'show'])
            ->middleware('permission:prescriptions.view')
            ->name('api.medications.show');
        Route::match(['put', 'patch'], 'medications/{medication}', [MedicationController::class, 'update'])
            ->middleware('permission:prescriptions.create')
            ->name('api.medications.update');
        Route::delete('medications/{medication}', [MedicationController::class, 'destroy'])
            ->middleware('permission:prescriptions.create')
            ->name('api.medications.destroy');

        Route::get('prescriptions', [PrescriptionController::class, 'index'])
            ->middleware('permission:prescriptions.view')
            ->name('api.prescriptions.index');
        Route::post('prescriptions', [PrescriptionController::class, 'store'])
            ->middleware('permission:prescriptions.create')
            ->name('api.prescriptions.store');
        Route::get('prescriptions/{prescription}', [PrescriptionController::class, 'show'])
            ->middleware('permission:prescriptions.view')
            ->name('api.prescriptions.show');
        Route::post('prescriptions/{prescription}/dispense', [PrescriptionController::class, 'dispense'])
            ->middleware('permission:prescriptions.dispense')
            ->name('api.prescriptions.dispense');
        Route::post('prescriptions/{prescription}/cancel', [PrescriptionController::class, 'cancel'])
            ->middleware('permission:prescriptions.create')
            ->name('api.prescriptions.cancel');

        /*
        |--------------------------------------------------------------------------
        | Billing
        |--------------------------------------------------------------------------
        */
        Route::get('invoices', [InvoiceController::class, 'index'])
            ->middleware('permission:billing.view')
            ->name('api.invoices.index');
        Route::post('invoices', [InvoiceController::class, 'store'])
            ->middleware('permission:billing.view')
            ->name('api.invoices.store');
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])
            ->middleware('permission:billing.view')
            ->name('api.invoices.show');
        Route::post('invoices/{invoice}/issue', [InvoiceController::class, 'issue'])
            ->middleware('permission:billing.view')
            ->name('api.invoices.issue');
        Route::post('invoices/{invoice}/payments', [InvoiceController::class, 'payment'])
            ->middleware('permission:payments.collect')
            ->name('api.invoices.payments');
        Route::post('invoices/{invoice}/refunds', [InvoiceController::class, 'refund'])
            ->middleware('permission:billing.view')
            ->name('api.invoices.refunds');
        Route::post('refunds/{refund}/approve', [InvoiceController::class, 'approveRefund'])
            ->middleware('permission:refunds.approve')
            ->name('api.refunds.approve');
        Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])
            ->middleware('permission:billing.view')
            ->name('api.invoices.cancel');

        /*
        |--------------------------------------------------------------------------
        | Insurance
        |--------------------------------------------------------------------------
        */
        Route::get('insurance-providers', [InsuranceProviderController::class, 'index'])
            ->middleware('permission:insurance.view')
            ->name('api.insurance-providers.index');
        Route::post('insurance-providers', [InsuranceProviderController::class, 'store'])
            ->middleware('permission:insurance.manage')
            ->name('api.insurance-providers.store');
        Route::get('insurance-providers/{provider}', [InsuranceProviderController::class, 'show'])
            ->middleware('permission:insurance.view')
            ->name('api.insurance-providers.show');
        Route::match(['put', 'patch'], 'insurance-providers/{provider}', [InsuranceProviderController::class, 'update'])
            ->middleware('permission:insurance.manage')
            ->name('api.insurance-providers.update');
        Route::delete('insurance-providers/{provider}', [InsuranceProviderController::class, 'destroy'])
            ->middleware('permission:insurance.manage')
            ->name('api.insurance-providers.destroy');

        Route::get('patient-insurances', [PatientInsuranceController::class, 'index'])
            ->middleware('permission:insurance.view')
            ->name('api.patient-insurances.index');
        Route::post('patient-insurances', [PatientInsuranceController::class, 'store'])
            ->middleware('permission:insurance.manage')
            ->name('api.patient-insurances.store');
        Route::get('patient-insurances/{policy}', [PatientInsuranceController::class, 'show'])
            ->middleware('permission:insurance.view')
            ->name('api.patient-insurances.show');
        Route::match(['put', 'patch'], 'patient-insurances/{policy}', [PatientInsuranceController::class, 'update'])
            ->middleware('permission:insurance.manage')
            ->name('api.patient-insurances.update');
        Route::delete('patient-insurances/{policy}', [PatientInsuranceController::class, 'destroy'])
            ->middleware('permission:insurance.manage')
            ->name('api.patient-insurances.destroy');

        Route::get('insurance-claims', [InsuranceClaimController::class, 'index'])
            ->middleware('permission:insurance.view')
            ->name('api.insurance-claims.index');
        Route::post('insurance-claims', [InsuranceClaimController::class, 'store'])
            ->middleware('permission:insurance.manage')
            ->name('api.insurance-claims.store');
        Route::get('insurance-claims/{claim}', [InsuranceClaimController::class, 'show'])
            ->middleware('permission:insurance.view')
            ->name('api.insurance-claims.show');
        Route::post('insurance-claims/{claim}/approve', [InsuranceClaimController::class, 'approve'])
            ->middleware('permission:insurance.manage')
            ->name('api.insurance-claims.approve');
        Route::post('insurance-claims/{claim}/reject', [InsuranceClaimController::class, 'reject'])
            ->middleware('permission:insurance.manage')
            ->name('api.insurance-claims.reject');

        /*
        |--------------------------------------------------------------------------
        | Laboratory
        |--------------------------------------------------------------------------
        */
        Route::get('lab-tests', [LabTestController::class, 'index'])
            ->middleware('permission:laboratory.view')
            ->name('api.lab-tests.index');
        Route::post('lab-tests', [LabTestController::class, 'store'])
            ->middleware('permission:laboratory.manage|settings.manage')
            ->name('api.lab-tests.store');
        Route::get('lab-tests/{test}', [LabTestController::class, 'show'])
            ->middleware('permission:laboratory.view')
            ->name('api.lab-tests.show');
        Route::match(['put', 'patch'], 'lab-tests/{test}', [LabTestController::class, 'update'])
            ->middleware('permission:laboratory.manage|settings.manage')
            ->name('api.lab-tests.update');
        Route::delete('lab-tests/{test}', [LabTestController::class, 'destroy'])
            ->middleware('permission:laboratory.manage|settings.manage')
            ->name('api.lab-tests.destroy');

        Route::get('lab-orders', [LabOrderController::class, 'index'])
            ->middleware('permission:laboratory.view')
            ->name('api.lab-orders.index');
        Route::get('lab-worklist', [LabOrderController::class, 'worklist'])
            ->middleware('permission:laboratory.view')
            ->name('api.lab-worklist');
        Route::get('lab-collection-worklist', [LabOrderController::class, 'collectionWorklist'])
            ->middleware('permission:laboratory.view')
            ->name('api.lab-collection-worklist');
        Route::post('lab-orders', [LabOrderController::class, 'store'])
            ->middleware('permission:laboratory.manage|settings.manage')
            ->name('api.lab-orders.store');
        Route::get('lab-orders/{order}', [LabOrderController::class, 'show'])
            ->middleware('permission:laboratory.view')
            ->name('api.lab-orders.show');

        Route::get('lab-order-items', [LabOrderItemController::class, 'index'])
            ->middleware('permission:laboratory.view')
            ->name('api.lab-order-items.index');
        Route::post('lab-order-items/{item}/collect', [LabOrderController::class, 'collectSample'])
            ->middleware('permission:laboratory.manage|settings.manage')
            ->name('api.lab-order-items.collect');
        Route::post('lab-orders/{order}/collect', [LabOrderController::class, 'collectOrderSamples'])
            ->middleware('permission:laboratory.manage|settings.manage')
            ->name('api.lab-orders.collect');
        Route::post('lab-order-items/{item}/result', [LabOrderController::class, 'enterResult'])
            ->middleware('permission:laboratory.manage|settings.manage')
            ->name('api.lab-order-items.result');
        Route::post('lab-order-items/{item}/values', [LabOrderController::class, 'saveValues'])
            ->middleware('permission:laboratory.manage|settings.manage')
            ->name('api.lab-order-items.values');
        Route::post('lab-order-items/{item}/approve', [LabOrderController::class, 'approveResult'])
            ->middleware('permission:laboratory.approve')
            ->name('api.lab-order-items.approve');
        Route::post('lab-order-items/{item}/unapprove', [LabOrderController::class, 'unapproveResult'])
            ->middleware('permission:laboratory.approve')
            ->name('api.lab-order-items.unapprove');
        Route::post('lab-orders/{order}/print', [LabOrderController::class, 'printOrder'])
            ->middleware('permission:laboratory.view')
            ->name('api.lab-orders.print');

        /*
        |--------------------------------------------------------------------------
        | Radiology
        |--------------------------------------------------------------------------
        */
        Route::get('radiology-worklist', [RadiologyOrderController::class, 'worklist'])
            ->middleware('permission:radiology.view|settings.manage')
            ->name('api.radiology-worklist');
        Route::get('radiology-orders', [RadiologyOrderController::class, 'index'])
            ->middleware('permission:radiology.view|settings.manage')
            ->name('api.radiology-orders.index');
        Route::post('radiology-orders', [RadiologyOrderController::class, 'store'])
            ->middleware('permission:radiology.manage|settings.manage')
            ->name('api.radiology-orders.store');
        Route::get('radiology-orders/{order}', [RadiologyOrderController::class, 'show'])
            ->middleware('permission:radiology.view|settings.manage')
            ->name('api.radiology-orders.show');
        Route::get('radiology-orders/{order}/devices', [RadiologyOrderController::class, 'devices'])
            ->middleware('permission:radiology.view|settings.manage')
            ->name('api.radiology-orders.devices');
        Route::get('radiology-orders/{order}/device-data', [RadiologyOrderController::class, 'deviceData'])
            ->middleware('permission:radiology.view|settings.manage')
            ->name('api.radiology-orders.device-data');
        Route::post('radiology-orders/{order}/fetch-devices', [RadiologyOrderController::class, 'fetchDevices'])
            ->middleware('permission:radiology.manage|settings.manage')
            ->name('api.radiology-orders.fetch-devices');
        Route::post('radiology-orders/{order}/report', [RadiologyOrderController::class, 'report'])
            ->middleware('permission:radiology.manage|settings.manage')
            ->name('api.radiology-orders.report');
        Route::post('radiology-orders/{order}/reopen', [RadiologyOrderController::class, 'reopen'])
            ->middleware('permission:radiology.manage|settings.manage')
            ->name('api.radiology-orders.reopen');
        Route::post('radiology-orders/{order}/print', [RadiologyOrderController::class, 'printOrder'])
            ->middleware('permission:radiology.view|settings.manage')
            ->name('api.radiology-orders.print');
        Route::get('radiology-images/{image}/download', [RadiologyOrderController::class, 'downloadImage'])
            ->middleware('permission:radiology.view|settings.manage')
            ->name('api.radiology-images.download');
        Route::post('radiology-orders/{order}/confirm-result', [RadiologyOrderController::class, 'confirmResult'])
            ->middleware('permission:radiology.manage|settings.manage')
            ->name('api.radiology-orders.confirm-result');
        Route::post('radiology-orders/{order}/cancel', [RadiologyOrderController::class, 'cancel'])
            ->middleware('permission:radiology.manage|settings.manage')
            ->name('api.radiology-orders.cancel');

        /*
        |--------------------------------------------------------------------------
        | Accounting
        |--------------------------------------------------------------------------
        */
        Route::get('accounts', [AccountingController::class, 'accounts'])
            ->middleware('permission:accounting.view')
            ->name('api.accounts.index');
        Route::post('accounts', [AccountingController::class, 'storeAccount'])
            ->middleware('permission:accounting.manage')
            ->name('api.accounts.store');
        Route::get('journal-entries', [AccountingController::class, 'journalEntries'])
            ->middleware('permission:accounting.view')
            ->name('api.journal-entries.index');
        Route::post('journal-entries', [AccountingController::class, 'storeJournalEntry'])
            ->middleware('permission:accounting.manage')
            ->name('api.journal-entries.store');
        Route::get('accounting/trial-balance', [AccountingController::class, 'trialBalance'])
            ->middleware('permission:accounting.view')
            ->name('api.accounting.trial-balance');
        Route::get('accounting/income-statement', [AccountingController::class, 'incomeStatement'])
            ->middleware('permission:accounting.view')
            ->name('api.accounting.income-statement');
        Route::get('accounting/balance-sheet', [AccountingController::class, 'balanceSheet'])
            ->middleware('permission:accounting.view')
            ->name('api.accounting.balance-sheet');
        Route::get('accounts/{account}/ledger', [AccountingController::class, 'ledger'])
            ->middleware('permission:accounting.view')
            ->name('api.accounts.ledger');

        /*
        |--------------------------------------------------------------------------
        | Human Resources
        |--------------------------------------------------------------------------
        */
        Route::get('employees', [EmployeeController::class, 'index'])
            ->middleware('permission:users.manage')
            ->name('api.employees.index');
        Route::post('employees', [EmployeeController::class, 'store'])
            ->middleware('permission:users.manage')
            ->name('api.employees.store');
        Route::get('employees/{employee}', [EmployeeController::class, 'show'])
            ->middleware('permission:users.manage')
            ->name('api.employees.show');
        Route::match(['put', 'patch'], 'employees/{employee}', [EmployeeController::class, 'update'])
            ->middleware('permission:users.manage')
            ->name('api.employees.update');

        /*
        |--------------------------------------------------------------------------
        | Inpatient
        |--------------------------------------------------------------------------
        */
        Route::get('wards', [WardController::class, 'index'])
            ->middleware('permission:patients.view')
            ->name('api.wards.index');
        Route::post('wards', [WardController::class, 'store'])
            ->middleware('permission:patients.update')
            ->name('api.wards.store');
        Route::get('wards/{ward}', [WardController::class, 'show'])
            ->middleware('permission:patients.view')
            ->name('api.wards.show');
        Route::match(['put', 'patch'], 'wards/{ward}', [WardController::class, 'update'])
            ->middleware('permission:patients.update')
            ->name('api.wards.update');

        Route::get('beds', [BedController::class, 'index'])
            ->middleware('permission:patients.view')
            ->name('api.beds.index');
        Route::post('beds', [BedController::class, 'store'])
            ->middleware('permission:patients.update')
            ->name('api.beds.store');
        Route::match(['put', 'patch'], 'beds/{bed}', [BedController::class, 'update'])
            ->middleware('permission:patients.update')
            ->name('api.beds.update');

        Route::get('admissions', [AdmissionController::class, 'index'])
            ->middleware('permission:patients.view')
            ->name('api.admissions.index');
        Route::post('admissions', [AdmissionController::class, 'store'])
            ->middleware('permission:patients.update')
            ->name('api.admissions.store');
        Route::post('admissions/{admission}/discharge', [AdmissionController::class, 'discharge'])
            ->middleware('permission:patients.update')
            ->name('api.admissions.discharge');

        /*
        |--------------------------------------------------------------------------
        | Insurance Contracts
        |--------------------------------------------------------------------------
        */
        Route::get('insurance-contracts', [InsuranceContractController::class, 'index'])
            ->middleware('permission:insurance.view')
            ->name('api.insurance-contracts.index');
        Route::post('insurance-contracts', [InsuranceContractController::class, 'store'])
            ->middleware('permission:insurance.manage')
            ->name('api.insurance-contracts.store');
        Route::get('insurance-contracts/{contract}', [InsuranceContractController::class, 'show'])
            ->middleware('permission:insurance.view')
            ->name('api.insurance-contracts.show');
        Route::post('insurance-contracts/{contract}/services', [InsuranceContractController::class, 'addService'])
            ->middleware('permission:insurance.manage')
            ->name('api.insurance-contracts.services');

        /*
        |--------------------------------------------------------------------------
        | Lab Devices
        |--------------------------------------------------------------------------
        */
        Route::get('lab-devices', [LabDeviceController::class, 'index'])
            ->middleware('permission:laboratory.view')
            ->name('api.lab-devices.index');
        Route::post('lab-devices', [LabDeviceController::class, 'store'])
            ->middleware('permission:laboratory.manage|settings.manage')
            ->name('api.lab-devices.store');
        Route::post('lab-devices/{device}/test-connection', [LabDeviceController::class, 'testConnection'])
            ->middleware('permission:laboratory.manage|settings.manage')
            ->name('api.lab-devices.test-connection');
        Route::post('lab-devices/{device}/poll', [LabDeviceController::class, 'pollNow'])
            ->middleware('permission:laboratory.manage|settings.manage')
            ->name('api.lab-devices.poll');
        Route::get('lab-devices/{device}', [LabDeviceController::class, 'show'])
            ->middleware('permission:laboratory.view')
            ->name('api.lab-devices.show');
        Route::match(['put', 'patch'], 'lab-devices/{device}', [LabDeviceController::class, 'update'])
            ->middleware('permission:laboratory.manage|settings.manage')
            ->name('api.lab-devices.update');
        Route::delete('lab-devices/{device}', [LabDeviceController::class, 'destroy'])
            ->middleware('permission:laboratory.manage|settings.manage')
            ->name('api.lab-devices.destroy');

        /*
        |--------------------------------------------------------------------------
        | Radiology & Ultrasound Devices
        |--------------------------------------------------------------------------
        */
        Route::get('radiology-devices', [RadiologyDeviceController::class, 'index'])
            ->middleware('permission:radiology.view|settings.manage')
            ->name('api.radiology-devices.index');
        Route::post('radiology-devices', [RadiologyDeviceController::class, 'store'])
            ->middleware('permission:radiology.manage|settings.manage')
            ->name('api.radiology-devices.store');
        Route::post('radiology-devices/{device}/test-connection', [RadiologyDeviceController::class, 'testConnection'])
            ->middleware('permission:radiology.manage|settings.manage')
            ->name('api.radiology-devices.test-connection');
        Route::post('radiology-devices/{device}/poll', [RadiologyDeviceController::class, 'pollNow'])
            ->middleware('permission:radiology.manage|settings.manage')
            ->name('api.radiology-devices.poll');
        Route::get('radiology-devices/{device}', [RadiologyDeviceController::class, 'show'])
            ->middleware('permission:radiology.view|settings.manage')
            ->name('api.radiology-devices.show');
        Route::match(['put', 'patch'], 'radiology-devices/{device}', [RadiologyDeviceController::class, 'update'])
            ->middleware('permission:radiology.manage|settings.manage')
            ->name('api.radiology-devices.update');
        Route::delete('radiology-devices/{device}', [RadiologyDeviceController::class, 'destroy'])
            ->middleware('permission:radiology.manage|settings.manage')
            ->name('api.radiology-devices.destroy');
        Route::get('radiology-devices/{device}/messages', [RadiologyDeviceMessageController::class, 'index'])
            ->middleware('permission:radiology.view|settings.manage')
            ->name('api.radiology-devices.messages');

        /*
        |--------------------------------------------------------------------------
        | Universal gateway -- bedside devices (emergency / OR / ICU / wards)
        |--------------------------------------------------------------------------
        */
        Route::get('clinical-devices', [ClinicalDeviceController::class, 'index'])
            ->middleware('permission:settings.manage')
            ->name('api.clinical-devices.index');
        Route::post('clinical-devices', [ClinicalDeviceController::class, 'store'])
            ->middleware('permission:settings.manage')
            ->name('api.clinical-devices.store');
        Route::get('clinical-devices/{device}', [ClinicalDeviceController::class, 'show'])
            ->middleware('permission:settings.manage')
            ->name('api.clinical-devices.show');
        Route::match(['put', 'patch'], 'clinical-devices/{device}', [ClinicalDeviceController::class, 'update'])
            ->middleware('permission:settings.manage')
            ->name('api.clinical-devices.update');
        Route::delete('clinical-devices/{device}', [ClinicalDeviceController::class, 'destroy'])
            ->middleware('permission:settings.manage')
            ->name('api.clinical-devices.destroy');
        Route::post('clinical-devices/{device}/test-connection', [ClinicalDeviceController::class, 'testConnection'])
            ->middleware('permission:settings.manage')
            ->name('api.clinical-devices.test-connection');
        Route::post('clinical-devices/{device}/poll', [ClinicalDeviceController::class, 'pollNow'])
            ->middleware('permission:settings.manage')
            ->name('api.clinical-devices.poll');
        Route::get('clinical-devices/{device}/messages', [ClinicalDeviceController::class, 'messages'])
            ->middleware('permission:settings.manage')
            ->name('api.clinical-devices.messages');
        Route::get('clinical-devices/{device}/logs', [ClinicalDeviceController::class, 'logs'])
            ->middleware('permission:settings.manage')
            ->name('api.clinical-devices.logs');

        /*
        |--------------------------------------------------------------------------
        | Attendance & Payroll
        |--------------------------------------------------------------------------
        */
        Route::get('attendance', [AttendanceController::class, 'index'])
            ->middleware('permission:users.manage')
            ->name('api.attendance.index');
        Route::post('attendance', [AttendanceController::class, 'store'])
            ->middleware('permission:users.manage')
            ->name('api.attendance.store');

        Route::get('payroll-runs', [PayrollController::class, 'index'])
            ->middleware('permission:users.manage')
            ->name('api.payroll-runs.index');
        Route::post('payroll-runs/generate', [PayrollController::class, 'generate'])
            ->middleware('permission:users.manage')
            ->name('api.payroll-runs.generate');
        Route::post('payroll-runs/{run}/pay', [PayrollController::class, 'markPaid'])
            ->middleware('permission:users.manage')
            ->name('api.payroll-runs.pay');

        Route::get('reports/commissions', [ReportController::class, 'commissions'])
            ->middleware('permission:reports.view')
            ->name('api.reports.commissions');

        /*
        |--------------------------------------------------------------------------
        | Services & Catalogs
        |--------------------------------------------------------------------------
        */
        Route::get('services', [ServiceController::class, 'index'])
            ->middleware('permission:settings.manage')
            ->name('api.services.index');
        Route::post('services', [ServiceController::class, 'store'])
            ->middleware('permission:settings.manage')
            ->name('api.services.store');
        Route::get('services/{service}', [ServiceController::class, 'show'])
            ->middleware('permission:settings.manage')
            ->name('api.services.show');
        Route::match(['put', 'patch'], 'services/{service}', [ServiceController::class, 'update'])
            ->middleware('permission:settings.manage')
            ->name('api.services.update');
        Route::delete('services/{service}', [ServiceController::class, 'destroy'])
            ->middleware('permission:settings.manage')
            ->name('api.services.destroy');

        Route::get('radiology-studies', [RadiologyStudyController::class, 'index'])
            ->middleware('permission:radiology.view|settings.manage')
            ->name('api.radiology-studies.index');
        Route::post('radiology-studies', [RadiologyStudyController::class, 'store'])
            ->middleware('permission:radiology.manage|settings.manage')
            ->name('api.radiology-studies.store');
        Route::get('radiology-studies/{study}', [RadiologyStudyController::class, 'show'])
            ->middleware('permission:radiology.view|settings.manage')
            ->name('api.radiology-studies.show');
        Route::match(['put', 'patch'], 'radiology-studies/{study}', [RadiologyStudyController::class, 'update'])
            ->middleware('permission:radiology.manage|settings.manage')
            ->name('api.radiology-studies.update');
        Route::delete('radiology-studies/{study}', [RadiologyStudyController::class, 'destroy'])
            ->middleware('permission:radiology.manage|settings.manage')
            ->name('api.radiology-studies.destroy');

        /*
        |--------------------------------------------------------------------------
        | Lab Parameters & Device Messages
        |--------------------------------------------------------------------------
        */
        Route::get('lab-tests/{test}/parameters', [LabTestParameterController::class, 'index'])
            ->middleware('permission:laboratory.view')
            ->name('api.lab-test-parameters.index');
        Route::post('lab-tests/{test}/parameters', [LabTestParameterController::class, 'store'])
            ->middleware('permission:laboratory.manage|settings.manage')
            ->name('api.lab-test-parameters.store');
        Route::match(['put', 'patch'], 'lab-test-parameters/{parameter}', [LabTestParameterController::class, 'update'])
            ->middleware('permission:laboratory.manage|settings.manage')
            ->name('api.lab-test-parameters.update');
        Route::delete('lab-test-parameters/{parameter}', [LabTestParameterController::class, 'destroy'])
            ->middleware('permission:laboratory.manage|settings.manage')
            ->name('api.lab-test-parameters.destroy');

        Route::get('lab-device-messages', [LabDeviceMessageController::class, 'index'])
            ->middleware('permission:laboratory.view')
            ->name('api.lab-device-messages.index');
        Route::get('lab-device-messages/{message}', [LabDeviceMessageController::class, 'show'])
            ->middleware('permission:laboratory.view')
            ->name('api.lab-device-messages.show');
        Route::get('lab-device-messages/{message}/values', [LabDeviceMessageController::class, 'values'])
            ->middleware('permission:laboratory.view')
            ->name('api.lab-device-messages.values');
        Route::get('lab-order-items/{item}/device-values', [LabDeviceMessageController::class, 'itemValues'])
            ->middleware('permission:laboratory.view')
            ->name('api.lab-order-items.device-values');
        Route::get('lab-orders/{order}/device-values', [LabDeviceMessageController::class, 'orderValues'])
            ->middleware('permission:laboratory.view')
            ->name('api.lab-orders.device-values');
        Route::post('lab-device-messages', [LabDeviceMessageController::class, 'store'])
            ->middleware('permission:laboratory.manage|settings.manage')
            ->name('api.lab-device-messages.store');
        Route::post('lab-device-messages/{message}/reprocess', [LabDeviceMessageController::class, 'reprocess'])
            ->middleware('permission:laboratory.manage|settings.manage')
            ->name('api.lab-device-messages.reprocess');

        Route::get('radiology-device-messages', [RadiologyDeviceMessageController::class, 'index'])
            ->middleware('permission:radiology.view|settings.manage')
            ->name('api.radiology-device-messages.index');
        Route::post('radiology-device-messages', [RadiologyDeviceMessageController::class, 'store'])
            ->middleware('permission:radiology.manage|settings.manage')
            ->name('api.radiology-device-messages.store');
        Route::post('radiology-device-messages/{message}/reprocess', [RadiologyDeviceMessageController::class, 'reprocess'])
            ->middleware('permission:radiology.manage|settings.manage')
            ->name('api.radiology-device-messages.reprocess');

        /*
        |--------------------------------------------------------------------------
        | Surgery / Maternity / Nursery
        |--------------------------------------------------------------------------
        */
        Route::get('surgeries', [SurgeryController::class, 'index'])
            ->middleware('permission:patients.view')
            ->name('api.surgeries.index');
        Route::post('surgeries', [SurgeryController::class, 'store'])
            ->middleware('permission:patients.update')
            ->name('api.surgeries.store');
        Route::match(['put', 'patch'], 'surgeries/{surgery}', [SurgeryController::class, 'update'])
            ->middleware('permission:patients.update')
            ->name('api.surgeries.update');

        Route::get('maternity-records', [MaternityController::class, 'index'])
            ->middleware('permission:patients.view')
            ->name('api.maternity-records.index');
        Route::post('maternity-records', [MaternityController::class, 'store'])
            ->middleware('permission:patients.update')
            ->name('api.maternity-records.store');
        Route::match(['put', 'patch'], 'maternity-records/{record}', [MaternityController::class, 'update'])
            ->middleware('permission:patients.update')
            ->name('api.maternity-records.update');

        Route::get('nursery-records', [NurseryController::class, 'index'])
            ->middleware('permission:patients.view')
            ->name('api.nursery-records.index');
        Route::post('nursery-records', [NurseryController::class, 'store'])
            ->middleware('permission:patients.update')
            ->name('api.nursery-records.store');
        Route::post('nursery-records/{record}/discharge', [NurseryController::class, 'discharge'])
            ->middleware('permission:patients.update')
            ->name('api.nursery-records.discharge');

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

        Route::get('reports/export', [ReportController::class, 'export'])
            ->middleware('permission:reports.export')
            ->name('api.reports.export');
    });
});
