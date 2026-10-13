<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContractResource;
use App\Models\Erp\Contract;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Read-only access to ERP contracts, used to populate new projects.
 */
class ContractController extends Controller
{
    /**
     * Newest contracts that have at least one container, optionally filtered by contract number or client name.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $search = trim($validated['search'] ?? '');

        $contracts = Contract::query()
            ->has('containers')
            ->with('client')
            ->withCount('containers')
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('nrcontr', 'like', "%{$search}%")
                ->orWhereHas('client', fn ($query) => $query->where('den', 'like', "%{$search}%"))))
            ->orderByDesc('idtrzctr')
            ->paginate($validated['per_page'] ?? 25)
            ->withQueryString();

        return ContractResource::collection($contracts);
    }

    /**
     * A contract with its client and container units.
     */
    public function show(Contract $contract): ContractResource
    {
        return new ContractResource($contract->load(['client', 'containers']));
    }
}
