<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\StoreProjectItemCommentRequest;
use App\Http\Requests\Projects\StoreSectionItemsRequest;
use App\Http\Requests\Projects\UpdateProjectItemRequest;
use App\Http\Resources\ProjectItemResource;
use App\Models\Project;
use App\Models\ProjectItem;
use App\Models\Section;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Write side for section operators. Returns only the changed items (with container and comments),
 * not the whole project.
 */
class WorkItemController extends Controller
{
    /**
     * Operators add materials they need to a project, but only for their own section (from the URL).
     * Returns the new items, one per container.
     */
    public function store(StoreSectionItemsRequest $request, Section $section, Project $project): JsonResponse
    {
        if ($project->isClosed()) {
            throw ValidationException::withMessages(['project' => 'Proiectul este închis.']);
        }

        $items = $project->addMaterial(
            $request->material(),
            $request->validated('container_ids'),
            $section->id,
            $request->validated('planned_qty'),
            $request->user(),
        );

        return ProjectItemResource::collection($items->load(ProjectItem::WORK_RELATIONS))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateProjectItemRequest $request, ProjectItem $projectItem): ProjectItemResource
    {
        $projectItem->recordWork($request->validated(), $request->user());

        return new ProjectItemResource($projectItem->load(ProjectItem::WORK_RELATIONS));
    }

    /**
     * Operators comment only on items of the sections they work on.
     */
    public function comment(StoreProjectItemCommentRequest $request, ProjectItem $projectItem): JsonResponse
    {
        Gate::authorize('work', $projectItem);

        $projectItem->addComment($request->user(), $request->validated('body'));

        return (new ProjectItemResource($projectItem->load(ProjectItem::WORK_RELATIONS)))
            ->response()
            ->setStatusCode(201);
    }
}
