<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The user format shared with the UI. The full phone number is visible only to users with `users.manage`.
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->when($request->user()?->hasPermission('users.manage') ?? false, $this->phone),
            'phone_masked' => $this->maskedPhone(),
            'role' => $this->roleSlug(),
            'sectionIds' => $this->sections->pluck('id')->values(),
            'permissions' => $this->permissionSlugs(),
            'is_active' => $this->is_active,
        ];
    }
}
