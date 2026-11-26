<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_device_test', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_device_id')->constrained('lab_devices')->cascadeOnDelete();
            $table->foreignId('lab_test_id')->constrained('lab_tests')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['lab_device_id', 'lab_test_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_device_test');
    }
};
