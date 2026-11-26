<?php

use App\Domains\Laboratory\Models\LabOrder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Lab order numbers mirror the analyzer's sample numbers: plain, unique
     * integers starting from 1. Existing prefixed numbers are normalized and
     * the shared sequence is moved to the next free integer.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            $orders = LabOrder::query()->orderBy('id')->get(['id', 'order_number']);

            // Free every unique slot before assigning the new numeric values.
            foreach ($orders as $order) {
                DB::table('lab_orders')
                    ->where('id', $order->id)
                    ->update(['order_number' => 'TMP-LAB-'.$order->id]);
            }

            foreach ($orders as $index => $order) {
                DB::table('lab_orders')
                    ->where('id', $order->id)
                    ->update(['order_number' => (string) ($index + 1)]);
            }

            DB::table('document_sequences')->updateOrInsert(
                ['key' => 'lab_order'],
                [
                    'prefix' => '',
                    'padding' => 1,
                    'next_value' => $orders->count() + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        });
    }

    public function down(): void
    {
        // Numeric sample numbers have no reversible prefixed form.
    }
};
