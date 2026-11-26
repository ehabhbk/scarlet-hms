<?php

namespace App\Domains\Laboratory\Models;

use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabOrderItem extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ENTERED = 'entered';
    public const STATUS_APPROVED = 'approved';

    protected $fillable = [
        'lab_order_id', 'lab_test_id', 'status', 'result_value',
        'result_unit', 'flag', 'notes', 'entered_by', 'approved_by',
        'entered_at', 'approved_at', 'printed_at', 'printed_by',
        'sample_collected_at', 'sample_collected_by',
        'source', 'source_device_id', 'source_message_id', 'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'entered_at' => 'datetime',
            'approved_at' => 'datetime',
            'printed_at' => 'datetime',
            'sample_collected_at' => 'datetime',
            'imported_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(LabOrder::class, 'lab_order_id');
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(LabTest::class, 'lab_test_id');
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function sampleCollector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sample_collected_by');
    }

    public function values(): HasMany
    {
        return $this->hasMany(LabResultValue::class);
    }

    public function enteredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function sampleCollectorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sample_collected_by');
    }

    public function sourceDevice(): BelongsTo
    {
        return $this->belongsTo(LabDevice::class, 'source_device_id');
    }

    public function sourceMessage(): BelongsTo
    {
        return $this->belongsTo(LabDeviceMessage::class, 'source_message_id');
    }

    public function printedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'printed_by');
    }
}
