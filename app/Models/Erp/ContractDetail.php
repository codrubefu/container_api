<?php

namespace App\Models\Erp;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ERP contract line (dbo.trzdetctr).
 */
#[Table(name: 'trzdetctr', key: 'idtrzdetctr', timestamps: false)]
#[Fillable([
    'idtrzctr', 'tip', 'clasa', 'grupa', 'art', 'seria', 'descr', 'pret', 'valpret', 'descpret', 'val',
    'data', 'idfirma', 'utilizator', 'tva', 'cant',
])]
class ContractDetail extends ErpModel
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'idtrzdetctr' => 'integer',
            'idtrzctr' => 'integer',
            'pret' => 'decimal:2',
            'val' => 'decimal:2',
            'data' => 'datetime',
            'idfirma' => 'integer',
            'tva' => 'decimal:4',
            'cant' => 'decimal:3',
        ];
    }

    /**
     * @return BelongsTo<Contract, $this>
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'idtrzctr', 'idtrzctr');
    }

    /**
     * Container identifier from the free-text article, without the leading rental verb
     * (e.g. "Inchiriere MSCU6041657" -> "MSCU6041657").
     */
    public function containerCode(): string
    {
        return preg_replace('/^(inchiri\w*|inchirire|inhciri\w*)\s+/i', '', trim((string) $this->art));
    }
}
