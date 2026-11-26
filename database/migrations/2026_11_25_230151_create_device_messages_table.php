<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_device_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_device_id')->constrained('lab_devices')->cascadeOnDelete();
            $table->longText('raw_message');
            $table->string('status', 20)->default('received');
            $table->text('parsed_summary')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_device_messages');
    }
};
