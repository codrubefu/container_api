<?php

namespace App\Models;

use Database\Factories\ProjectContainerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Snapshot of one container line (dbo.trzdetctr) of the project's contract.
 */
#[Fillable(['contract_line_id', 'code', 'type'])]
class ProjectContainer extends Model
{
    /** @use HasFactory<ProjectContainerFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'project_id' => 'integer',
            'contract_line_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return HasMany<ProjectItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ProjectItem::class);
    }
}
