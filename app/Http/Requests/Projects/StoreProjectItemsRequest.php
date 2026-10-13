<?php

namespace App\Http\Requests\Projects;

use App\Http\Resources\MaterialResource;
use App\Models\Erp\Material;
use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Adds one material to one or more containers of the project (one item per container).
 */
class StoreProjectItemsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'container_ids' => ['required', 'array', 'min:1'],
            'container_ids.*' => ['integer', 'distinct', Rule::exists('project_containers', 'id')->where('project_id', $this->project()->id)],
            'material_id' => ['required', 'integer'],
            'planned_qty' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'section_id' => ['required', 'integer', 'exists:sections,id'],
        ];
    }

    public function project(): Project
    {
        return $this->route('project');
    }

    /**
     * Snapshot of the requested ERP production material.
     *
     * @return array{id: int, code: string, name: string, unit: string, category: string}
     *
     * @throws ValidationException when the material is not in the production catalogue
     */
    public function material(): array
    {
        $material = Material::query()->forProduction()->find($this->integer('material_id'));

        if (! $material) {
            throw ValidationException::withMessages(['material_id' => 'Materialul nu există în nomenclatorul de producție.']);
        }

        return (new MaterialResource($material))->resolve();
    }
}
