<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\StoreProjectItemsRequest;
use App\Http\Requests\Projects\UpdateProjectItemRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Models\ProjectItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Materials planned on project containers. Every action returns the whole updated project.
 */
class ProjectItemController extends Controller
{
    /**
     * Adds the material to each selected container (one item per container) and starts production.
     */
    public function store(StoreProjectItemsRequest $request, Project $project): JsonResponse
    {
        $this->ensureOpen($project);

        $project->addMaterial(
            $request->material(),
            $request->validated('container_ids'),
            $request->integer('section_id'),
            $request->validated('planned_qty'),
            $request->user(),
        );

        return (new ProjectResource($project->load(Project::FULL_RELATIONS)))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Status / actual quantity (see ProjectItem::recordWork()).
     */
    public function update(UpdateProjectItemRequest $request, ProjectItem $projectItem): ProjectResource
    {
        $projectItem->recordWork($request->validated(), $request->user());

        return $this->projectOf($projectItem);
    }

    public function destroy(ProjectItem $projectItem): ProjectResource
    {
        $project = $projectItem->container->project;
        $this->ensureOpen($project);

        $projectItem->delete();

        return new ProjectResource($project->load(Project::FULL_RELATIONS));
    }

    private function projectOf(ProjectItem $projectItem): ProjectResource
    {
        return new ProjectResource($projectItem->container->project->load(Project::FULL_RELATIONS));
    }

    private function ensureOpen(Project $project): void
    {
        if ($project->isClosed()) {
            throw ValidationException::withMessages(['project' => 'Proiectul este închis. Redeschide-l pentru modificări.']);
        }
    }
}
