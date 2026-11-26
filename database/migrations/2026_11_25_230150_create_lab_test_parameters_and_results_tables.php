<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_test_parameters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_test_id')->constrained('lab_tests')->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('unit', 30)->nullable();
            $table->string('reference_range', 80)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('lab_result_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_order_item_id')->constrained('lab_order_items')->cascadeOnDelete();
            $table->foreignId('lab_test_parameter_id')->constrained('lab_test_parameters')->cascadeOnDelete();
            $table->string('value')->nullable();
            $table->string('flag', 20)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_result_values');
        Schema::dropIfExists('lab_test_parameters');
    }
};
