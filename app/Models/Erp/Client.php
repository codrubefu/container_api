<?php

namespace App\Models\Erp;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ERP client / partner (dbo.client). Read-only from this app.
 */
#[Table(name: 'client', key: 'idcl', timestamps: false)]
class Client extends ErpModel
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'idcl' => 'integer',
            'activ' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Contract, $this>
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'idcl', 'idcl');
    }
}
