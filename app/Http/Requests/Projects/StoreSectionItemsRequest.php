<?php

namespace App\Http\Requests\Projects;

use App\Models\Section;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A section operator adds a material to containers of a project; the section comes from the URL
 * (only sections the operator works on, see the `work` gate on the route).
 */
class StoreSectionItemsRequest extends StoreProjectItemsRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return collect(parent::rules())->except('section_id')->all();
    }

    public function section(): Section
    {
        return $this->route('section');
    }
}
