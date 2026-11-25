<?php

namespace App\Http\Resources;

use App\Domains\Encounters\Models\ClinicalNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ClinicalNote */
class ClinicalNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'encounter_id' => $this->encounter_id,
            'type' => $this->type,
            'content' => $this->content,
            'status' => $this->status,
            'author' => $this->whenLoaded('author', fn () => $this->author?->name),
            'signed_at' => $this->signed_at?->toIso8601String(),
            'is_signed' => $this->signed_at !== null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
