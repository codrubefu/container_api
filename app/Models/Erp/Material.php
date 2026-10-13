<?php

namespace App\Models\Erp;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * ERP material / article catalogue (dbo.genmp). Read-only from this app.
 */
#[Table(name: 'genmp', key: 'idmp', timestamps: false)]
class Material extends ErpModel
{
    /**
     * Article class of the container production catalogue (spelled this way in the ERP).
     */
    public const PRODUCTION_CLASS = 'Productie Continere';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'idmp' => 'integer',
            'activ' => 'boolean',
            'serviciu' => 'boolean',
        ];
    }

    /**
     * Active articles that can be planned on a container.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function forProduction(Builder $query): void
    {
        $query->where('clasa', self::PRODUCTION_CLASS)->where('activ', true);
    }
}
