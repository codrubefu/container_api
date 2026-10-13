<?php

namespace App\Http\Resources;

use App\Models\Erp\Material;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Material
 */
class MaterialResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $code = trim((string) $this->art);

        return [
            'id' => $this->idmp,
            'code' => $code,
            'name' => trim((string) $this->desc1) ?: $code,
            'unit' => trim((string) $this->um),
            'category' => trim((string) $this->grupa),
        ];
    }
}
