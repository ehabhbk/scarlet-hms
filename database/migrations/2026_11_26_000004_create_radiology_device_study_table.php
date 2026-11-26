<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('radiology_device_study', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('radiology_device_id')->constrained('radiology_devices')->cascadeOnDelete();
            $table->foreignId('radiology_study_id')->constrained('radiology_studies')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['radiology_device_id', 'radiology_study_id'], 'radiology_device_study_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('radiology_device_study');
    }
};
