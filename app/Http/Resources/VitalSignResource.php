<?php

namespace App\Http\Resources;

use App\Domains\Encounters\Models\VitalSign;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin VitalSign */
class VitalSignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'encounter_id' => $this->encounter_id,
            'temperature_c' => $this->temperature_c,
            'heart_rate' => $this->heart_rate,
            'respiratory_rate' => $this->respiratory_rate,
            'systolic_bp' => $this->systolic_bp,
            'diastolic_bp' => $this->diastolic_bp,
            'blood_pressure' => $this->systolic_bp && $this->diastolic_bp
                ? $this->systolic_bp.'/'.$this->diastolic_bp
                : null,
            'oxygen_saturation' => $this->oxygen_saturation,
            'weight_kg' => $this->weight_kg,
            'height_cm' => $this->height_cm,
            'bmi' => $this->bmi,
            'pain_score' => $this->pain_score,
            'notes' => $this->notes,
            'recorded_by' => $this->whenLoaded('recordedBy', fn () => $this->recordedBy?->name),
            'recorded_at' => $this->recorded_at?->toIso8601String(),
            'flags' => $this->when(true, fn () => $this->flags()),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function flags(): array
    {
        $flags = [];

        if ($this->temperature_c !== null && (float) $this->temperature_c >= 38.0) {
            $flags[] = 'fever';
        }

        if ($this->heart_rate !== null && $this->heart_rate > 100) {
            $flags[] = 'tachycardia';
        }

        if ($this->heart_rate !== null && $this->heart_rate < 60) {
            $flags[] = 'bradycardia';
        }

        if ($this->systolic_bp !== null && $this->systolic_bp >= 140) {
            $flags[] = 'high_systolic';
        }

        if ($this->oxygen_saturation !== null && $this->oxygen_saturation < 94) {
            $flags[] = 'low_oxygen';
        }

        return $flags;
    }
}
