<?php

namespace App\Http\Resources;

use App\Models\ProjectItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProjectItem
 */
class ProjectItemResource extends JsonResource
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
            'container_id' => $this->project_container_id,
            'section_id' => $this->section_id,
            'material' => [
                'id' => $this->material_id,
                'code' => $this->material_code,
                'name' => $this->material_name,
                'unit' => $this->material_unit,
            ],
            'planned_qty' => (float) $this->planned_qty,
            'actual_qty' => $this->actual_qty === null ? null : (float) $this->actual_qty,
            'status' => $this->status,
            'worker_id' => $this->worker_id,
            'created_by' => $this->whenLoaded('creator', fn (): ?array => $this->creator?->only(['id', 'name'])),
            'updated_by' => $this->whenLoaded('editor', fn (): ?array => $this->editor?->only(['id', 'name'])),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'container' => $this->whenLoaded('container', fn (): array => [
                'id' => $this->container->id,
                'code' => $this->container->code,
                'type' => $this->container->type,
            ]),
            'comments' => ProjectItemCommentResource::collection($this->whenLoaded('comments')),
        ];
    }
}
