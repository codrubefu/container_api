<?php

namespace App\Http\Resources;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Project
 */
class ProjectResource extends JsonResource
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
            'status' => $this->status,
            'deadline' => $this->deadline?->toDateString(),
            'contract' => [
                'id' => $this->contract_id,
                'number' => $this->contract_number,
                'client' => $this->client_name,
            ],
            'containers' => $this->whenLoaded('containers', fn () => $this->containers->map(fn ($container): array => [
                'id' => $container->id,
                'code' => $container->code,
                'type' => $container->type,
            ])),
            'items' => ProjectItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
