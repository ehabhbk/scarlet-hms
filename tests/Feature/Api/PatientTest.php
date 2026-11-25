<?php

namespace Tests\Feature\Api;

use App\Domains\Patients\Models\Patient;

class PatientTest extends ApiTestCase
{
    public function test_receptionist_can_register_patient_with_generated_mrn(): void
    {
        $this->actingAsRole('receptionist');

        $response = $this->postJson('/api/v1/patients', [
            'first_name_ar' => 'سلمى',
            'last_name_ar' => 'حسن',
            'first_name_en' => 'Salma',
            'last_name_en' => 'Hassan',
            'gender' => 'female',
            'date_of_birth' => '1994-06-12',
            'phone' => '0918000000',
            'national_id' => '11010109999',
            'blood_type' => 'A+',
            'city' => 'الخرطوم',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.first_name_ar', 'سلمى')
            ->assertJsonPath('data.full_name_en', 'Salma Hassan');

        $this->assertStringStartsWith('SC-', $response->json('data.mrn'));
        $this->assertDatabaseHas('patients', ['national_id' => '11010109999']);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'created',
            'auditable_type' => Patient::class,
        ]);
    }

    public function test_duplicate_patient_returns_conflict_with_explainable_candidates(): void
    {
        $existing = Patient::factory()->create([
            'first_name_ar' => 'نور',
            'last_name_ar' => 'الهدى',
            'first_name_en' => 'Nour',
            'last_name_en' => 'Alhuda',
            'phone' => '0918111222',
            'date_of_birth' => '1990-01-01',
            'national_id' => '11010108888',
        ]);

        $this->actingAsRole('receptionist');

        $response = $this->postJson('/api/v1/patients', [
            'first_name_ar' => 'نور',
            'last_name_ar' => 'الهدى',
            'gender' => 'female',
            'date_of_birth' => '1990-01-01',
            'phone' => '0918111222',
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('code', 'duplicate_patient')
            ->assertJsonPath('errors.duplicates.0.id', $existing->id)
            ->assertJsonPath('errors.duplicates.0.score', 175);

        $this->assertContains('phone', $response->json('errors.duplicates.0.reasons'));
        $this->assertContains('arabic_name_and_dob', $response->json('errors.duplicates.0.reasons'));
    }

    public function test_duplicate_check_can_be_overridden_with_explicit_flag(): void
    {
        Patient::factory()->create([
            'phone' => '0918222333',
            'first_name_ar' => 'إبراهيم',
            'last_name_ar' => 'موسى',
            'date_of_birth' => '1970-02-02',
        ]);

        $this->actingAsRole('receptionist');

        $this->postJson('/api/v1/patients', [
            'first_name_ar' => 'إبراهيم',
            'last_name_ar' => 'موسى',
            'gender' => 'male',
            'date_of_birth' => '1970-02-02',
            'phone' => '0918222333',
            'ignore_duplicates' => true,
        ])->assertCreated();

        $this->assertSame(2, Patient::query()->where('phone', '0918222333')->count());
    }

    public function test_doctor_without_create_permission_cannot_register_patient(): void
    {
        $this->actingAsRole('doctor');

        $this->postJson('/api/v1/patients', [
            'first_name_ar' => 'اختبار',
            'last_name_ar' => 'صلاحيات',
            'gender' => 'male',
            'date_of_birth' => '1980-01-01',
        ])->assertForbidden();
    }

    public function test_patient_record_includes_allergies_and_timeline(): void
    {
        $patient = Patient::factory()->create();
        $patient->allergies()->create([
            'allergen' => 'البنسلين',
            'reaction' => 'طفح جلدي',
            'severity' => 'severe',
            'status' => 'active',
        ]);

        $this->actingAsRole('doctor');

        $this->getJson('/api/v1/patients/'.$patient->id)
            ->assertOk()
            ->assertJsonPath('data.patient.allergies.0.allergen', 'البنسلين')
            ->assertJsonPath('data.patient.has_allergy_alert', true);

        $this->getJson('/api/v1/patients/'.$patient->id.'/timeline')
            ->assertOk()
            ->assertJsonStructure(['data' => ['patient', 'items']]);
    }
}
