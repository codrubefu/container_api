<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\UpdateUserRoleRequest;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;

class UserRoleController extends Controller
{
    /**
     * Set the user's role (a user has one role; the pivot allows more in the future).
     */
    public function update(UpdateUserRoleRequest $request, User $user): UserResource
    {
        $user->roles()->sync([Role::query()->where('slug', $request->validated('role'))->value('id')]);

        return new UserResource($user->load('roles.permissions', 'sections'));
    }
}
