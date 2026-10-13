<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\StoreProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Erp\Contract;
use App\Models\Erp\ContractDetail;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectController extends Controller
{
    /**
     * All projects, newest first, with containers, items and comments.
     */
    public function index(): AnonymousResourceCollection
    {
        return ProjectResource::collection(
            Project::query()->with(Project::FULL_RELATIONS)->orderByDesc('id')->get()
        );
    }

    /**
     * Creates a draft project from an ERP contract, copying the contract and its containers.
     */
    public function store(StoreProjectRequest $request): JsonResponse
    {
        $contract = Contract::query()->with(['client', 'containers'])->find($request->integer('contract_id'));

        if (! $contract || $contract->containers->isEmpty()) {
            throw ValidationException::withMessages([
                'contract_id' => $contract ? 'Contractul nu are containere.' : 'Contractul nu există.',
            ]);
        }

        $project = DB::transaction(function () use ($request, $contract): Project {
            $project = Project::query()->create([
                'contract_id' => $contract->idtrzctr,
                'contract_number' => trim((string) $contract->nrcontr),
                'client_name' => $contract->client ? trim((string) $contract->client->den) : null,
                'name' => $request->validated('name'),
                'deadline' => $request->validated('deadline'),
                'status' => Project::STATUS_DRAFT,
                'created_by' => $request->user()->id,
            ]);

            $project->containers()->createMany($contract->containers->map(fn (ContractDetail $line): array => [
                'contract_line_id' => $line->idtrzdetctr,
                'code' => $line->containerCode(),
                'type' => trim((string) $line->grupa) ?: null,
            ])->all());

            return $project;
        });

        return (new ProjectResource($project->load(Project::FULL_RELATIONS)))
            ->response()
            ->setStatusCode(201);
    }

    public function close(Project $project): ProjectResource
    {
        $project->update(['status' => Project::STATUS_CLOSED]);

        return new ProjectResource($project->load(Project::FULL_RELATIONS));
    }

    /**
     * Reopens a closed project: back to production if it has items, otherwise draft.
     */
    public function reopen(Project $project): ProjectResource
    {
        $project->update([
            'status' => $project->items()->exists() ? Project::STATUS_IN_PRODUCTION : Project::STATUS_DRAFT,
        ]);

        return new ProjectResource($project->load(Project::FULL_RELATIONS));
    }
}
