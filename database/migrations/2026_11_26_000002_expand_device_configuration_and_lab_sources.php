<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_devices', function (Blueprint $table): void {
            $table->string('host', 100)->nullable()->after('model');
            $table->unsignedSmallInteger('port')->nullable()->after('host');
        });

        Schema::table('lab_order_items', function (Blueprint $table): void {
            $table->string('source', 20)->default('manual')->after('notes');
            $table->unsignedBigInteger('source_device_id')->nullable()->after('source');
            $table->unsignedBigInteger('source_message_id')->nullable()->after('source_device_id');
            $table->timestamp('imported_at')->nullable()->after('source_message_id');
            $table->index(['status', 'source'], 'lab_order_items_status_source_index');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE lab_order_items ADD CONSTRAINT lab_order_items_source_device_fk FOREIGN KEY (source_device_id) REFERENCES lab_devices(id) ON DELETE SET NULL');
            DB::statement('ALTER TABLE lab_order_items ADD CONSTRAINT lab_order_items_source_message_fk FOREIGN KEY (source_message_id) REFERENCES lab_device_messages(id) ON DELETE SET NULL');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE lab_order_items DROP FOREIGN KEY lab_order_items_source_message_fk');
            DB::statement('ALTER TABLE lab_order_items DROP FOREIGN KEY lab_order_items_source_device_fk');
        }

        Schema::table('lab_order_items', function (Blueprint $table): void {
            $table->dropIndex('lab_order_items_status_source_index');
            $table->dropColumn(['source', 'source_device_id', 'source_message_id', 'imported_at']);
        });

        Schema::table('lab_devices', function (Blueprint $table): void {
            $table->dropColumn(['host', 'port']);
        });
    }
};
