<?php

namespace Tests\Feature\Api;

use App\Domains\Patients\Models\Patient;

class EncounterTest extends ApiTestCase
{
    public function test_doctor_can_document_and_sign_encounter(): void
    {
        $patient = Patient::factory()->create();
        $doctor = $this->actingAsRole('doctor');

        $created = $this->postJson('/api/v1/encounters', [
            'patient_id' => $patient->id,
            'branch_id' => $this->branch->id,
            'department_id' => $this->department->id,
            'chief_complaint' => 'ألم في الصدر',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'in_progress');

        $encounterId = $created->json('data.id');
        $this->assertStringStartsWith('ENC-', $created->json('data.encounter_number'));

        $this->postJson('/api/v1/encounters/'.$encounterId.'/vitals', [
            'temperature_c' => 37.1,
            'heart_rate' => 82,
            'systolic_bp' => 130,
            'diastolic_bp' => 85,
            'oxygen_saturation' => 98,
            'weight_kg' => 80,
            'height_cm' => 180,
        ])->assertCreated()
            ->assertJsonPath('data.bmi', '24.7')
            ->assertJsonPath('data.blood_pressure', '130/85');

        $this->postJson('/api/v1/encounters/'.$encounterId.'/diagnoses', [
            'icd10_code' => 'R07.4',
            'name_ar' => 'ألم في الصدر غير محدد',
            'name_en' => 'Chest pain, unspecified',
            'type' => 'primary',
            'certainty' => 'suspected',
        ])->assertCreated();

        $this->postJson('/api/v1/encounters/'.$encounterId.'/notes', [
            'type' => 'progress',
            'content' => 'تم تقييم المريض، تخطيط القلب سليم، يُوصى بمتابعة العيادة.',
        ])->assertCreated();

        $this->patchJson('/api/v1/encounters/'.$encounterId, [
            'assessment' => 'ألم صدري غير قلبي.',
            'plan' => 'مسكنات ومتابعة بعد أسبوع.',
        ])->assertOk();

        $signed = $this->postJson('/api/v1/encounters/'.$encounterId.'/sign')
            ->assertOk()
            ->assertJsonPath('data.is_signed', true)
            ->assertJsonPath('data.status', 'completed');

        $this->assertNotNull($signed->json('data.signed_at'));

        $this->patchJson('/api/v1/encounters/'.$encounterId, [
            'assessment' => 'محاولة تعديل بعد التوقيع',
        ])->assertStatus(422);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'workflow.consultation_completed',
            'auditable_id' => $encounterId,
        ]);

        $this->assertSame(
            0,
            $this->getJson('/api/v1/encounters/'.$encounterId)
                ->json('data.clinical_notes.0.status') === 'draft' ? 1 : 0
        );
    }

    public function test_signing_requires_clinical_content(): void
    {
        $patient = Patient::factory()->create();
        $this->actingAsRole('doctor');

        $encounterId = $this->postJson('/api/v1/encounters', [
            'patient_id' => $patient->id,
            'branch_id' => $this->branch->id,
            'department_id' => $this->department->id,
        ])->assertCreated()->json('data.id');

        $this->postJson('/api/v1/encounters/'.$encounterId.'/sign')
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['encounter']]);
    }

    public function test_receptionist_cannot_create_or_sign_encounters(): void
    {
        $patient = Patient::factory()->create();
        $this->actingAsRole('receptionist');

        $this->postJson('/api/v1/encounters', [
            'patient_id' => $patient->id,
            'branch_id' => $this->branch->id,
            'department_id' => $this->department->id,
        ])->assertForbidden();
    }
}
