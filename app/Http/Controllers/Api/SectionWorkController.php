<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectItemResource;
use App\Http\Resources\SectionProjectResource;
use App\Http\Resources\SectionResource;
use App\Models\Project;
use App\Models\ProjectContainer;
use App\Models\ProjectItem;
use App\Models\Role;
use App\Models\Section;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Read side for section operators: their sections, the open projects with work in a section,
 * and that section's items of one project. Closed projects are left out of the lists.
 */
class SectionWorkController extends Controller
{
    /**
     * Sections the user works on (admins: all), with the number of unfinished items.
     */
    public function sections(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $sections = Section::query()
            ->when(! $user->hasRole(Role::ADMIN), fn (Builder $query) => $query
                ->whereHas('users', fn (Builder $users) => $users->whereKey($user->id)))
            ->withCount(['items as pending_count' => fn (Builder $items) => $items->pending()->inOpenProjects()])
            ->orderBy('id')
            ->get();

        return SectionResource::collection($sections);
    }

    /**
     * Open projects that have items in the section, newest first.
     */
    public function projects(Section $section): AnonymousResourceCollection
    {
        $inSection = fn (Builder $items) => $items->where('project_items.section_id', $section->id);

        $projects = Project::query()
            ->where('status', '!=', Project::STATUS_CLOSED)
            ->whereHas('items', $inSection)
            ->withCount([
                'items as items_count' => $inSection,
                'items as pending_count' => fn (Builder $items) => $inSection($items)->pending(),
            ])
            ->orderByDesc('id')
            ->get();

        return SectionProjectResource::collection($projects);
    }

    /**
     * The section's items in one project, with their container and comments. The project's containers
     * are included so operators can add materials to them.
     */
    public function items(Section $section, Project $project): AnonymousResourceCollection
    {
        $items = ProjectItem::query()
            ->where('section_id', $section->id)
            ->whereHas('container', fn (Builder $container) => $container->where('project_id', $project->id))
            ->with(ProjectItem::WORK_RELATIONS)
            ->orderBy('id')
            ->get();

        return ProjectItemResource::collection($items)->additional(['project' => [
            'id' => $project->id,
            'name' => $project->name,
            'code' => $project->contract_number,
            'client' => $project->client_name,
            'status' => $project->status,
            'containers' => $project->containers->map(fn (ProjectContainer $container): array => [
                'id' => $container->id,
                'code' => $container->code,
                'type' => $container->type,
            ]),
        ]]);
    }
}
