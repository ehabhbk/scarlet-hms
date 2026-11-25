<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encounters', function (Blueprint $table) {
            $table->id();
            $table->string('encounter_number', 40)->unique();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20)->default('outpatient');
            $table->string('status', 20)->default('open')->index();
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->string('chief_complaint')->nullable();
            $table->longText('history')->nullable();
            $table->longText('examination')->nullable();
            $table->longText('assessment')->nullable();
            $table->longText('plan')->nullable();
            $table->string('disposition', 30)->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->foreignId('signed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'started_at']);
            $table->index(['branch_id', 'status', 'started_at']);
            $table->index(['doctor_id', 'status']);
        });

        Schema::create('vital_signs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('encounter_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('temperature_c', 4, 1)->nullable();
            $table->unsignedSmallInteger('heart_rate')->nullable();
            $table->unsignedSmallInteger('respiratory_rate')->nullable();
            $table->unsignedSmallInteger('systolic_bp')->nullable();
            $table->unsignedSmallInteger('diastolic_bp')->nullable();
            $table->unsignedSmallInteger('oxygen_saturation')->nullable();
            $table->decimal('weight_kg', 5, 2)->nullable();
            $table->decimal('height_cm', 5, 2)->nullable();
            $table->decimal('bmi', 4, 1)->nullable();
            $table->unsignedTinyInteger('pain_score')->nullable();
            $table->string('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('recorded_at');
            $table->timestamps();

            $table->index(['patient_id', 'recorded_at']);
            $table->index(['encounter_id', 'recorded_at']);
        });

        Schema::create('clinical_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('encounter_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 30)->default('progress');
            $table->longText('content');
            $table->string('status', 20)->default('draft')->index();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('signed_at')->nullable();
            $table->foreignId('amended_from_id')->nullable()->constrained('clinical_notes')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('diagnoses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('encounter_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('icd10_code', 20)->nullable()->index();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('type', 20)->default('primary');
            $table->string('certainty', 20)->default('confirmed');
            $table->text('notes')->nullable();
            $table->foreignId('diagnosed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnoses');
        Schema::dropIfExists('clinical_notes');
        Schema::dropIfExists('vital_signs');
        Schema::dropIfExists('encounters');
    }
};
