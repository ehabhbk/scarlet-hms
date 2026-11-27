<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('radiology_orders', function (Blueprint $table): void {
            $table->timestamp('printed_at')->nullable()->after('completed_at');
            $table->foreignId('printed_by')->nullable()->after('printed_at')->constrained('users')->nullOnDelete();
        });

        Schema::create('radiology_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('radiology_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('radiology_device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('radiology_device_message_id')->nullable()->constrained()->nullOnDelete();
            $table->string('disk', 40)->default('local');
            $table->string('path');
            $table->string('sop_uid', 128)->nullable();
            $table->string('study_uid', 128)->nullable();
            $table->string('modality', 40)->nullable();
            $table->string('mime_type', 80)->default('application/dicom');
            $table->timestamp('received_at')->useCurrent();
            $table->timestamps();

            $table->index(['radiology_order_id', 'received_at']);
            $table->index(['radiology_device_id', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('radiology_images');

        Schema::table('radiology_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('printed_by');
            $table->dropColumn('printed_at');
        });
    }
};
