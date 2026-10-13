<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\StoreProjectItemCommentRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Models\ProjectItem;
use Illuminate\Http\JsonResponse;

class ProjectItemCommentController extends Controller
{
    /**
     * Adds a comment to the item and returns the whole updated project.
     */
    public function store(StoreProjectItemCommentRequest $request, ProjectItem $projectItem): JsonResponse
    {
        $projectItem->addComment($request->user(), $request->validated('body'));

        return (new ProjectResource($projectItem->container->project->load(Project::FULL_RELATIONS)))
            ->response()
            ->setStatusCode(201);
    }
}
