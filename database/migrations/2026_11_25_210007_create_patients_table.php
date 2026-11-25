<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('mrn', 30)->unique();
            $table->string('first_name_ar');
            $table->string('last_name_ar');
            $table->string('first_name_en')->nullable();
            $table->string('last_name_en')->nullable();
            $table->string('national_id', 50)->nullable()->unique();
            $table->string('passport_number', 50)->nullable();
            $table->string('gender', 20);
            $table->date('date_of_birth');
            $table->string('phone', 30)->nullable();
            $table->string('alt_phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('marital_status', 20)->nullable();
            $table->string('nationality', 60)->nullable();
            $table->string('blood_type', 5)->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 30)->nullable();
            $table->string('emergency_contact_relation', 30)->nullable();
            $table->string('photo_path')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->foreignId('registration_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->unsignedBigInteger('merged_into_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('merged_into_id')->references('id')->on('patients')->nullOnDelete();
            $table->index(['last_name_ar', 'first_name_ar']);
            $table->index(['last_name_en', 'first_name_en']);
            $table->index('phone');
            $table->index('date_of_birth');
        });

        Schema::create('patient_allergies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('allergen');
            $table->string('reaction')->nullable();
            $table->string('severity', 20)->default('moderate');
            $table->string('status', 20)->default('active');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'status']);
        });

        Schema::create('patient_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30)->default('chronic');
            $table->string('code', 30)->nullable();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->date('diagnosed_on')->nullable();
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_conditions');
        Schema::dropIfExists('patient_allergies');
        Schema::dropIfExists('patients');
    }
};
