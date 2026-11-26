<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('radiology_orders', function (Blueprint $table): void {
            $table->string('source', 20)->default('manual')->after('report');
            $table->unsignedBigInteger('source_device_id')->nullable()->after('source');
            $table->unsignedBigInteger('source_message_id')->nullable()->after('source_device_id');
            $table->unsignedBigInteger('radiology_study_id')->nullable()->after('source_message_id');
            $table->timestamp('result_received_at')->nullable()->after('radiology_study_id');
            $table->index(['status', 'source'], 'radiology_orders_status_source_index');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE radiology_orders ADD CONSTRAINT radiology_orders_source_device_fk FOREIGN KEY (source_device_id) REFERENCES radiology_devices(id) ON DELETE SET NULL');
            DB::statement('ALTER TABLE radiology_orders ADD CONSTRAINT radiology_orders_source_message_fk FOREIGN KEY (source_message_id) REFERENCES radiology_device_messages(id) ON DELETE SET NULL');
            DB::statement('ALTER TABLE radiology_orders ADD CONSTRAINT radiology_orders_study_fk FOREIGN KEY (radiology_study_id) REFERENCES radiology_studies(id) ON DELETE SET NULL');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE radiology_orders DROP FOREIGN KEY radiology_orders_study_fk');
            DB::statement('ALTER TABLE radiology_orders DROP FOREIGN KEY radiology_orders_source_message_fk');
            DB::statement('ALTER TABLE radiology_orders DROP FOREIGN KEY radiology_orders_source_device_fk');
        }

        Schema::table('radiology_orders', function (Blueprint $table): void {
            $table->dropIndex('radiology_orders_status_source_index');
            $table->dropColumn(['source', 'source_device_id', 'source_message_id', 'radiology_study_id', 'result_received_at']);
        });
    }
};
