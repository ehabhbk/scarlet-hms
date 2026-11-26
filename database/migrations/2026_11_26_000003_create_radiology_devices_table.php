<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('radiology_devices', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->string('protocol', 40)->default('HL7');
            $table->string('ae_title', 40)->nullable();
            $table->string('host', 100)->nullable();
            $table->unsignedSmallInteger('port')->nullable();
            $table->string('modality', 40)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('radiology_devices');
    }
};
