<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Encounters\Models\Encounter;
use App\Domains\Encounters\Requests\StoreClinicalNoteRequest;
use App\Domains\Encounters\Requests\StoreDiagnosisRequest;
use App\Domains\Encounters\Requests\StoreEncounterRequest;
use App\Domains\Encounters\Requests\StoreVitalsRequest;
use App\Domains\Encounters\Requests\UpdateEncounterRequest;
use App\Domains\Encounters\Services\EncounterService;
use App\Http\Controllers\Controller;
use App\Http\Resources\ClinicalNoteResource;
use App\Http\Resources\DiagnosisResource;
use App\Http\Resources\EncounterResource;
use App\Http\Resources\VitalSignResource;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EncounterController extends Controller
{
    public function __construct(private readonly EncounterService $encounterService) {}

    public function index(Request $request): JsonResponse
    {
        $query = Encounter::query()
            ->with([
                'patient:id,mrn,first_name_ar,last_name_ar,first_name_en,last_name_en,date_of_birth,gender',
                'doctor:id,name,job_title',
                'department:id,name_ar,name_en',
                'branch:id,code,name_ar,name_en',
                'diagnoses',
            ])
            ->withCount(['vitalSigns', 'clinicalNotes']);

        if ($status = $request->string('status')->toString()) {
            $statuses = explode(',', $status);
            $query->whereIn('status', $statuses);
        }

        if ($request->boolean('open')) {
            $query->open();
        }

        if ($patientId = $request->integer('patient_id')) {
            $query->where('patient_id', $patientId);
        }

        if ($doctorId = $request->integer('doctor_id')) {
            $query->where('doctor_id', $doctorId);
        }

        if ($departmentId = $request->integer('department_id')) {
            $query->where('department_id', $departmentId);
        }

        if ($branchId = $request->integer('branch_id')) {
            $query->where('branch_id', $branchId);
        }

        if ($type = $request->string('type')->toString()) {
            $query->where('type', $type);
        }

        if ($date = $request->string('date')->toString()) {
            $query->whereDate('started_at', $date);
        }

        if ($search = $request->string('search')->toString()) {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like) {
                $q->where('encounter_number', 'like', $like)
                    ->orWhereHas('patient', function ($patient) use ($like) {
                        $patient->where('mrn', 'like', $like)
                            ->orWhere('first_name_ar', 'like', $like)
                            ->orWhere('last_name_ar', 'like', $like)
                            ->orWhere('first_name_en', 'like', $like)
                            ->orWhere('last_name_en', 'like', $like);
                    });
            });
        }

        $encounters = $query
            ->orderByDesc('started_at')
            ->paginate(min(max($request->integer('per_page', 20), 5), 100))
            ->withQueryString();

        return ApiResponse::paginated($encounters, EncounterResource::collection($encounters));
    }

    public function store(StoreEncounterRequest $request): JsonResponse
    {
        $encounter = $this->encounterService->create(
            $request->validated(),
            $request->user()
        );

        return $encounter->wasRecentlyCreated
            ? ApiResponse::created(new EncounterResource($encounter), __('encounters.started'))
            : ApiResponse::success(new EncounterResource($encounter), __('encounters.existing'));
    }

    public function show(Encounter $encounter): JsonResponse
    {
        $encounter->load([
            'patient.allergies',
            'patient.conditions',
            'branch',
            'department',
            'doctor',
            'appointment',
            'vitalSigns' => fn ($query) => $query->latest('recorded_at'),
            'vitalSigns.recordedBy:id,name',
            'diagnoses.diagnosedBy:id,name',
            'clinicalNotes' => fn ($query) => $query->latest('created_at'),
            'clinicalNotes.author:id,name',
        ]);

        return ApiResponse::success(new EncounterResource($encounter));
    }

    public function update(UpdateEncounterRequest $request, Encounter $encounter): JsonResponse
    {
        $encounter = $this->encounterService->update(
            $encounter,
            $request->validated(),
            $request->user()
        );

        return ApiResponse::success(
            new EncounterResource($encounter->load(['patient', 'doctor', 'department'])),
            __('encounters.updated')
        );
    }

    public function sign(Request $request, Encounter $encounter): JsonResponse
    {
        $encounter = $this->encounterService->sign($encounter, $request->user());

        return ApiResponse::success(new EncounterResource($encounter), __('encounters.signed'));
    }

    public function storeVitals(StoreVitalsRequest $request, Encounter $encounter): JsonResponse
    {
        $vitals = $this->encounterService->addVitals(
            $encounter,
            $request->validated(),
            $request->user()
        );

        return ApiResponse::created(new VitalSignResource($vitals), __('encounters.vitals_recorded'));
    }

    public function storeDiagnosis(StoreDiagnosisRequest $request, Encounter $encounter): JsonResponse
    {
        $diagnosis = $this->encounterService->addDiagnosis(
            $encounter,
            $request->validated(),
            $request->user()
        );

        return ApiResponse::created(new DiagnosisResource($diagnosis), __('encounters.diagnosis_added'));
    }

    public function storeNote(StoreClinicalNoteRequest $request, Encounter $encounter): JsonResponse
    {
        $note = $this->encounterService->addNote(
            $encounter,
            $request->validated(),
            $request->user()
        );

        return ApiResponse::created(
            new ClinicalNoteResource($note->load('author:id,name')),
            __('encounters.note_added')
        );
    }
}
