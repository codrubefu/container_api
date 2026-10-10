<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Roles\SyncRolePermissionsRequest;
use App\Http\Resources\RoleResource;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RoleController extends Controller
{
    /**
     * All roles with their permissions; `meta.permissions` lists every available permission.
     */
    public function index(): AnonymousResourceCollection
    {
        return RoleResource::collection(Role::query()->with('permissions')->orderBy('id')->get())
            ->additional([
                'meta' => [
                    'permissions' => Permission::query()->orderBy('id')->get(['slug', 'description']),
                ],
            ]);
    }

    /**
     * Replace the role's permissions.
     */
    public function updatePermissions(SyncRolePermissionsRequest $request, Role $role): RoleResource
    {
        $role->permissions()->sync(
            Permission::query()->whereIn('slug', $request->validated('permissions'))->pluck('id'),
        );

        return new RoleResource($role->load('permissions'));
    }
}
