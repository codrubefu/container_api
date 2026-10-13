<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MaterialResource;
use App\Models\Erp\Material;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Read-only access to the ERP production catalogue, used to add materials to project containers.
 */
class MaterialController extends Controller
{
    /**
     * Production materials by article, optionally filtered by name/code and category.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:20'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $search = trim($validated['search'] ?? '');
        $category = trim($validated['category'] ?? '');

        $materials = Material::query()
            ->forProduction()
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('desc1', 'like', "%{$search}%")
                ->orWhere('art', 'like', "%{$search}%")))
            ->when($category !== '', fn ($query) => $query->where('grupa', $category))
            ->orderBy('art')
            ->orderBy('idmp')
            ->paginate($validated['per_page'] ?? 50)
            ->withQueryString();

        return MaterialResource::collection($materials);
    }

    /**
     * Distinct categories (ERP `grupa`) of the production catalogue.
     */
    public function categories(): JsonResponse
    {
        $categories = Material::query()
            ->forProduction()
            ->whereNotNull('grupa')
            ->distinct()
            ->orderBy('grupa')
            ->pluck('grupa')
            ->map(fn (string $category): string => trim($category))
            ->filter()
            ->values();

        return response()->json(['data' => $categories]);
    }
}
