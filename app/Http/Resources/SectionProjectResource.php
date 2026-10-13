<?php

namespace App\Http\Resources;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A project as seen from one section: only that section's item counts.
 *
 * @mixin Project
 */
class SectionProjectResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->contract_number,
            'client' => $this->client_name,
            'deadline' => $this->deadline?->toDateString(),
            'items_count' => (int) $this->items_count,
            'pending_count' => (int) $this->pending_count,
        ];
    }
}
