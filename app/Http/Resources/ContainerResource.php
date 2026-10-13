<?php

namespace App\Http\Resources;

use App\Models\Erp\ContractDetail;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A container unit on an ERP contract (one dbo.trzdetctr line).
 *
 * @mixin ContractDetail
 */
class ContainerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->idtrzdetctr,
            'code' => $this->containerCode(),
            'article' => trim((string) $this->art),
            'type' => trim((string) $this->grupa),
            'class' => trim((string) $this->clasa),
            'quantity' => (float) $this->cant,
        ];
    }
}
