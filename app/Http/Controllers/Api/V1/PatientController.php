<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Encounters\Models\Encounter;
use App\Domains\Patients\Models\Patient;
use App\Domains\Patients\Models\PatientAllergy;
use App\Domains\Patients\Models\PatientCondition;
use App\Domains\Patients\Requests\CheckDuplicatePatientRequest;
use App\Domains\Patients\Requests\MergePatientRequest;
use App\Domains\Patients\Requests\StorePatientAllergyRequest;
use App\Domains\Patients\Requests\StorePatientConditionRequest;
use App\Domains\Patients\Requests\StorePatientRequest;
use App\Domains\Patients\Requests\UpdatePatientRequest;
use App\Domains\Patients\Services\PatientService;
use App\Domains\Scheduling\Models\Appointment;
use App\Exceptions\DuplicatePatientException;
use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentResource;
use App\Http\Resources\EncounterResource;
use App\Http\Resources\PatientAllergyResource;
use App\Http\Resources\PatientConditionResource;
use App\Http\Resources\PatientResource;
use App\Support\Audit\AuditLogger;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PatientController extends Controller
{
    public function __construct(
        private readonly PatientService $patientService,
        private readonly AuditLogger $auditLogger
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Patient::query()
            ->with(['registrationBranch:id,code,name_ar,name_en', 'allergies'])
            ->withCount(['appointments', 'encounters'])
            ->withMax('encounters as last_visit_at', 'started_at');

        if ($search = $request->string('search')->toString()) {
            $query->search($search);
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($gender = $request->string('gender')->toString()) {
            $query->where('gender', $gender);
        }

        if ($branchId = $request->integer('branch_id')) {
            $query->where('registration_branch_id', $branchId);
        }

        if ($request->boolean('has_allergy')) {
            $query->whereHas('allergies', fn ($q) => $q->where('status', 'active'));
        }

        $sortable = ['mrn', 'created_at', 'date_of_birth', 'last_visit_at', 'first_name_ar', 'last_name_ar'];
        $sort = in_array($request->string('sort')->toString(), $sortable, true)
            ? $request->string('sort')->toString()
            : 'created_at';
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';

        $patients = $query
            ->orderBy($sort, $direction)
            ->paginate(min(max($request->integer('per_page', 20), 5), 100))
            ->withQueryString();

        return ApiResponse::paginated($patients, PatientResource::collection($patients));
    }

    public function store(StorePatientRequest $request): JsonResponse
    {
        try {
            $patient = $this->patientService->register(
                $request->safe()->except(['ignore_duplicates', 'registration_branch_id']),
                $request->user(),
                $request->integer('registration_branch_id') ?: null,
                $request->boolean('ignore_duplicates')
            );
        } catch (DuplicatePatientException $exception) {
            return ApiResponse::error(
                $exception->getMessage(),
                ['duplicates' => $exception->candidates()],
                409,
                'duplicate_patient'
            );
        }

        return ApiResponse::created(new PatientResource($patient), __('patients.registered'));
    }

    public function show(Request $request, Patient $patient): JsonResponse
    {
        $this->auditLogger->viewed($patient, 'Patient record viewed: '.$patient->mrn);

        $patient->load([
            'registrationBranch',
            'allergies.recordedBy:id,name',
            'conditions.recordedBy:id,name',
        ])->loadCount(['appointments', 'encounters']);

        $recentEncounters = $patient->encounters()
            ->with(['doctor:id,name', 'department:id,name_ar,name_en', 'diagnoses'])
            ->latest('started_at')
            ->limit(10)
            ->get();

        $upcomingAppointments = $patient->appointments()
            ->with(['doctor:id,name', 'department:id,name_ar,name_en'])
            ->whereIn('status', [Appointment::STATUS_BOOKED, Appointment::STATUS_CHECKED_IN])
            ->where('scheduled_at', '>=', now()->subHours(6))
            ->orderBy('scheduled_at')
            ->limit(10)
            ->get();

        return ApiResponse::success([
            'patient' => new PatientResource($patient),
            'recent_encounters' => EncounterResource::collection($recentEncounters),
            'upcoming_appointments' => AppointmentResource::collection($upcomingAppointments),
        ]);
    }

    public function update(UpdatePatientRequest $request, Patient $patient): JsonResponse
    {
        $patient = $this->patientService->update(
            $patient,
            $request->validated(),
            $request->user()
        );

        return ApiResponse::success(new PatientResource($patient), __('patients.updated'));
    }

    public function destroy(Request $request, Patient $patient): JsonResponse
    {
        abort_if(
            $patient->encounters()->exists(),
            422,
            __('patients.cannot_delete_with_history')
        );

        $patient->delete();

        return ApiResponse::success(null, __('patients.archived'));
    }

    public function duplicates(CheckDuplicatePatientRequest $request): JsonResponse
    {
        $candidates = $this->patientService->duplicates($request->validated());

        return ApiResponse::success([
            'has_duplicates' => $candidates->isNotEmpty(),
            'candidates' => $candidates->map(fn (array $item) => [
                'id' => $item['patient']->id,
                'mrn' => $item['patient']->mrn,
                'name' => $item['patient']->display_name,
                'name_ar' => $item['patient']->full_name_ar,
                'name_en' => $item['patient']->full_name_en,
                'phone' => $item['patient']->phone,
                'national_id' => $item['patient']->national_id,
                'date_of_birth' => $item['patient']->date_of_birth?->toDateString(),
                'gender' => $item['patient']->gender,
                'score' => $item['score'],
                'reasons' => $item['reasons'],
            ])->values(),
        ]);
    }

    public function timeline(Patient $patient): JsonResponse
    {
        return ApiResponse::success([
            'patient' => new PatientResource($patient->load(['allergies', 'conditions'])),
            'items' => $this->patientService->timeline($patient),
        ]);
    }

    public function merge(MergePatientRequest $request, Patient $patient): JsonResponse
    {
        $target = Patient::query()->findOrFail($request->integer('target_patient_id'));

        abort_if(
            $target->status === Patient::STATUS_MERGED,
            422,
            __('patients.target_already_merged')
        );

        $merged = $this->patientService->merge(
            $patient,
            $target,
            $request->user(),
            $request->string('reason')->toString()
        );

        $this->auditLogger->log(
            'patients.merged',
            $merged,
            ['source_id' => $patient->id, 'source_mrn' => $patient->mrn],
            ['target_id' => $target->id, 'target_mrn' => $target->mrn],
            $request->string('reason')->toString()
        );

        return ApiResponse::success(new PatientResource($merged), __('patients.merged'));
    }

    public function storeAllergy(StorePatientAllergyRequest $request, Patient $patient): JsonResponse
    {
        $allergy = DB::transaction(fn () => $patient->allergies()->create(array_merge(
            $request->validated(),
            [
                'status' => 'active',
                'recorded_by' => $request->user()->id,
            ]
        )));

        return ApiResponse::created(
            new PatientAllergyResource($allergy->load('recordedBy:id,name')),
            __('patients.allergy_added')
        );
    }

    public function destroyAllergy(Patient $patient, PatientAllergy $allergy): JsonResponse
    {
        abort_unless($allergy->patient_id === $patient->id, 404);

        $allergy->update(['status' => 'resolved']);
        $allergy->delete();

        return ApiResponse::success(null, __('patients.allergy_removed'));
    }

    public function storeCondition(StorePatientConditionRequest $request, Patient $patient): JsonResponse
    {
        $condition = DB::transaction(fn () => $patient->conditions()->create(array_merge(
            $request->validated(),
            [
                'status' => 'active',
                'recorded_by' => $request->user()->id,
            ]
        )));

        return ApiResponse::created(
            new PatientConditionResource($condition->load('recordedBy:id,name')),
            __('patients.condition_added')
        );
    }

    public function destroyCondition(Patient $patient, PatientCondition $condition): JsonResponse
    {
        abort_unless($condition->patient_id === $patient->id, 404);

        $condition->update(['status' => 'resolved']);
        $condition->delete();

        return ApiResponse::success(null, __('patients.condition_removed'));
    }
}
