<?php

namespace App\Models;

use Database\Factories\ProjectItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A material (snapshot of dbo.genmp) planned on one project container and assigned to a section.
 */
#[Fillable([
    'project_container_id', 'section_id', 'material_id', 'material_code', 'material_name', 'material_unit',
    'planned_qty', 'actual_qty', 'status', 'worker_id', 'created_by', 'updated_by',
])]
class ProjectItem extends Model
{
    /** @use HasFactory<ProjectItemFactory> */
    use HasFactory;

    public const STATUS_NOT_STARTED = 'neinceput';

    public const STATUS_IN_PROGRESS = 'in_lucru';

    public const STATUS_DONE = 'finalizat';

    public const STATUSES = [self::STATUS_NOT_STARTED, self::STATUS_IN_PROGRESS, self::STATUS_DONE];

    /**
     * Relations shown with an item in the section work responses (see ProjectItemResource).
     *
     * @var list<string>
     */
    public const WORK_RELATIONS = ['container', 'comments', 'creator', 'editor'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'project_container_id' => 'integer',
            'section_id' => 'integer',
            'material_id' => 'integer',
            'planned_qty' => 'decimal:3',
            'actual_qty' => 'decimal:3',
            'worker_id' => 'integer',
            'created_by' => 'integer',
            'updated_by' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ProjectContainer, $this>
     */
    public function container(): BelongsTo
    {
        return $this->belongsTo(ProjectContainer::class, 'project_container_id');
    }

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function worker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'worker_id');
    }

    /**
     * The user who added the item.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The user who changed the item last (null until the first change).
     *
     * @return BelongsTo<User, $this>
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * @return HasMany<ProjectItemComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(ProjectItemComment::class)->orderBy('id');
    }

    /**
     * Items of projects that are not closed (closed projects drop out of the section work lists).
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function inOpenProjects(Builder $query): void
    {
        $query->whereHas('container.project', fn (Builder $project) => $project
            ->where($project->qualifyColumn('status'), '!=', Project::STATUS_CLOSED));
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->where($query->qualifyColumn('status'), '!=', self::STATUS_DONE);
    }

    /**
     * Applies a status / actual quantity change made by `$worker`. Starting or finishing records the
     * worker; going back to "not started" clears the worker and the actual quantity. Any actual change
     * records `$worker` as the last editor.
     *
     * @param  array{status?: string, actual_qty?: float|int|string|null}  $changes
     */
    public function recordWork(array $changes, User $worker): void
    {
        $this->fill($changes);

        if ($this->isDirty('status')) {
            if ($this->status === self::STATUS_NOT_STARTED) {
                $this->fill(['worker_id' => null, 'actual_qty' => null]);
            } else {
                $this->worker_id = $worker->id;
            }
        }

        if ($this->isDirty()) {
            $this->updated_by = $worker->id;
        }

        $this->save();
    }

    public function addComment(User $author, string $body): ProjectItemComment
    {
        return $this->comments()->create([
            'user_id' => $author->id,
            'author_name' => $author->name,
            'body' => trim($body),
        ]);
    }
}
