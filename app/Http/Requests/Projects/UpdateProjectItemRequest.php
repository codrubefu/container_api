<?php

namespace App\Http\Requests\Projects;

use App\Models\ProjectItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectItemRequest extends FormRequest
{
    /**
     * Only users who may work on the item's section (see ProjectItemPolicy).
     */
    public function authorize(): bool
    {
        return $this->user()->can('work', $this->route('projectItem'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'required', 'string', Rule::in(ProjectItem::STATUSES)],
            'actual_qty' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999999'],
        ];
    }
}
