<?php

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\DB;

/**
 * A production project for one ERP contract, with a snapshot of the contract and its containers.
 */
#[Fillable(['contract_id', 'contract_number', 'client_name', 'name', 'deadline', 'status', 'created_by'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_IN_PRODUCTION = 'in_productie';

    public const STATUS_CLOSED = 'finalizat';

    /**
     * Relations needed to render a full project (see ProjectResource).
     *
     * @var list<string>
     */
    public const FULL_RELATIONS = ['containers', 'items.comments', 'items.creator', 'items.editor'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'contract_id' => 'integer',
            'deadline' => 'date',
        ];
    }

    /**
     * @return HasMany<ProjectContainer, $this>
     */
    public function containers(): HasMany
    {
        return $this->hasMany(ProjectContainer::class)->orderBy('id');
    }

    /**
     * @return HasManyThrough<ProjectItem, ProjectContainer, $this>
     */
    public function items(): HasManyThrough
    {
        return $this->hasManyThrough(ProjectItem::class, ProjectContainer::class)->orderBy('project_items.id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    /**
     * Adds the material to each of the project's `$containerIds` (one item per container), assigned to
     * `$sectionId` and recorded as added by `$author`. A draft project goes into production.
     *
     * @param  array{id: int, code: string, name: string, unit: string}  $material  snapshot of the ERP material
     * @param  list<int>  $containerIds
     * @return Collection<int, ProjectItem>
     */
    public function addMaterial(array $material, array $containerIds, int $sectionId, float|int|string $plannedQty, User $author): Collection
    {
        return DB::transaction(function () use ($material, $containerIds, $sectionId, $plannedQty, $author): Collection {
            $items = new Collection;

            foreach ($containerIds as $containerId) {
                $items->push(ProjectItem::query()->create([
                    'project_container_id' => $containerId,
                    'section_id' => $sectionId,
                    'material_id' => $material['id'],
                    'material_code' => $material['code'],
                    'material_name' => $material['name'],
                    'material_unit' => $material['unit'] ?: null,
                    'planned_qty' => $plannedQty,
                    'status' => ProjectItem::STATUS_NOT_STARTED,
                    'created_by' => $author->id,
                ]));
            }

            if ($this->status === self::STATUS_DRAFT) {
                $this->update(['status' => self::STATUS_IN_PRODUCTION]);
            }

            return $items;
        });
    }
}
