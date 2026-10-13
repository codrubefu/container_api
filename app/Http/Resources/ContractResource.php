<?php

namespace App\Http\Resources;

use App\Models\Erp\Contract;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Contract
 */
class ContractResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->idtrzctr,
            'number' => trim((string) $this->nrcontr),
            'type' => trim((string) $this->tipctr),
            'client' => $this->whenLoaded('client', fn (): ?array => $this->client ? [
                'id' => $this->client->idcl,
                'name' => trim((string) $this->client->den),
            ] : null),
            'starts_at' => $this->datast?->toDateString(),
            'ends_at' => $this->dataend?->toDateString(),
            'containers_count' => $this->whenCounted('containers', fn (mixed $count): int => (int) $count),
            'containers' => ContainerResource::collection($this->whenLoaded('containers')),
        ];
    }
}
