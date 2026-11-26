<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('radiology_device_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('radiology_device_id')->constrained('radiology_devices')->cascadeOnDelete();
            $table->longText('raw_message');
            $table->string('status', 20)->default('received');
            $table->string('message_id', 80)->nullable();
            $table->text('parsed_summary')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('radiology_device_messages');
    }
};
