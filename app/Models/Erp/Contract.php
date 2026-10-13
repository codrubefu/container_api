<?php

namespace App\Models\Erp;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ERP contract header (dbo.trzctr).
 */
#[Table(name: 'trzctr', key: 'idtrzctr', timestamps: false)]
#[Fillable([
    'nrcontr', 'tipctr', 'tipctr2', 'datasign', 'datast', 'dataend', 'idcl', 'tzile', 'penalitati', 'tva',
    'valbo', 'valutabo', 'maturare', 'valmaturare', 'curatire', 'valcuratire', 'transport', 'valtransport',
    'manipulare', 'valmanipulare', 'taxa', 'valtaxa', 'locstat', 'locpredare', 'vanzmarfa', 'valvanzmarfa',
    'data', 'idfirma', 'utilizator', 'autoc', 'proiect',
])]
class Contract extends ErpModel
{
    /**
     * Contract line classes that are accessories or services, not container units.
     *
     * @var list<string>
     */
    public const NON_CONTAINER_CLASSES = ['COMPONENTS', 'SERVICES'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'idtrzctr' => 'integer',
            'datasign' => 'datetime',
            'datast' => 'datetime',
            'dataend' => 'datetime',
            'idcl' => 'integer',
            'tzile' => 'integer',
            'penalitati' => 'decimal:2',
            'tva' => 'decimal:5',
            'valbo' => 'decimal:2',
            'maturare' => 'decimal:2',
            'curatire' => 'decimal:2',
            'transport' => 'decimal:2',
            'manipulare' => 'decimal:2',
            'taxa' => 'decimal:2',
            'vanzmarfa' => 'decimal:2',
            'data' => 'datetime',
            'idfirma' => 'integer',
            'autoc' => 'boolean',
        ];
    }

    /**
     * @return HasMany<ContractDetail, $this>
     */
    public function details(): HasMany
    {
        return $this->hasMany(ContractDetail::class, 'idtrzctr', 'idtrzctr');
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'idcl', 'idcl');
    }

    /**
     * Contract lines that are container units (one line per container).
     *
     * @return HasMany<ContractDetail, $this>
     */
    public function containers(): HasMany
    {
        return $this->details()
            ->where(fn ($query) => $query->whereNull('clasa')->orWhereNotIn('clasa', self::NON_CONTAINER_CLASSES))
            ->orderBy('idtrzdetctr');
    }
}
