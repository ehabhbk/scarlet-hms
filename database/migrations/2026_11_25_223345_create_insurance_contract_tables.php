<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurance_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('insurance_provider_id')->constrained('insurance_providers')->cascadeOnDelete();
            $table->string('contract_number', 60)->unique();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('patient_share_percent', 5, 2)->default(0);
            $table->decimal('company_share_percent', 5, 2)->default(100);
            $table->text('terms')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('insurance_contract_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('insurance_contract_id')->constrained('insurance_contracts')->cascadeOnDelete();
            $table->string('service_code', 60);
            $table->string('service_name');
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('patient_share_percent', 5, 2)->nullable();
            $table->decimal('company_share_percent', 5, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_contract_services');
        Schema::dropIfExists('insurance_contracts');
    }
};
