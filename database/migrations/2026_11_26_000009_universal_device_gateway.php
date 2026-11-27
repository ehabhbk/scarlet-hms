<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['lab_devices', 'radiology_devices'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('direction', 20)->default('push')->after('port');
                $table->boolean('poll_enabled')->default(false)->after('direction');
                $table->unsignedInteger('poll_interval_seconds')->default(30)->after('poll_enabled');
                $table->text('poll_query')->nullable()->after('poll_interval_seconds');
                $table->string('poll_query_encoding', 10)->default('literal')->after('poll_query');
                $table->string('protocol_detected')->nullable()->after('poll_query_encoding');
                $table->timestamp('last_poll_at')->nullable()->after('protocol_detected');
                $table->text('last_error')->nullable()->after('last_poll_at');
            });
        }

        Schema::create('clinical_devices', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->string('category', 30)->default('other')->comment('emergency, operating_room, icu, ward, other');
            $table->string('protocol', 40)->default('AUTO');
            $table->string('host', 100)->nullable();
            $table->unsignedSmallInteger('port')->nullable();
            $table->string('direction', 20)->default('push');
            $table->boolean('poll_enabled')->default(false);
            $table->unsignedInteger('poll_interval_seconds')->default(30);
            $table->text('poll_query')->nullable();
            $table->string('poll_query_encoding', 10)->default('literal');
            $table->string('protocol_detected')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamp('last_poll_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->index(['category', 'status']);
        });

        Schema::create('clinical_device_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinical_device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained()->nullOnDelete();
            $table->string('protocol', 40)->nullable();
            $table->string('message_id')->nullable();
            $table->string('patient_mrn')->nullable();
            $table->string('reference')->nullable()->comment('sample/accession/encounter number when present');
            $table->longText('raw_message');
            $table->longText('canonical_json')->nullable();
            $table->string('parsed_summary')->nullable();
            $table->string('status', 20)->default('received');
            $table->timestamp('received_at');
            $table->timestamps();
            $table->index(['clinical_device_id', 'received_at']);
            $table->index(['patient_mrn', 'reference']);
        });

        Schema::create('device_poll_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('device_type', 20);
            $table->unsignedBigInteger('device_id');
            $table->string('status', 20);
            $table->unsignedInteger('frames_count')->default(0);
            $table->unsignedInteger('imported_count')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('polled_at');
            $table->index(['device_type', 'device_id', 'polled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_poll_logs');
        Schema::dropIfExists('clinical_device_messages');
        Schema::dropIfExists('clinical_devices');

        foreach (['lab_devices', 'radiology_devices'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn([
                    'direction', 'poll_enabled', 'poll_interval_seconds', 'poll_query',
                    'poll_query_encoding', 'protocol_detected', 'last_poll_at', 'last_error',
                ]);
            });
        }
    }
};
