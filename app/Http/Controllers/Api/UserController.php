<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\IndexUserRequest;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    /**
     * Paginated list, filterable by role, section_id, search (name/email/phone) and is_active.
     */
    public function index(IndexUserRequest $request): AnonymousResourceCollection
    {
        $users = User::query()
            ->with(['roles.permissions', 'sections'])
            ->when($request->validated('role'), fn (Builder $query, string $role) => $query
                ->whereHas('roles', fn (Builder $roles) => $roles->where('slug', $role)))
            ->when($request->validated('section_id'), fn (Builder $query, int|string $sectionId) => $query
                ->whereHas('sections', fn (Builder $sections) => $sections->whereKey($sectionId)))
            ->when($request->validated('search'), fn (Builder $query, string $search) => $query
                ->where(fn (Builder $query) => $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")))
            ->when($request->has('is_active'), fn (Builder $query) => $query
                ->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request): User {
            $user = User::query()->create($request->safe()->only(['name', 'email', 'phone', 'password', 'is_active']));

            $user->roles()->sync([Role::query()->where('slug', $request->validated('role'))->value('id')]);
            $user->sections()->sync($request->validated('section_ids', []));

            return $user;
        });

        return (new UserResource($user->load('roles.permissions', 'sections')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user->load('roles.permissions', 'sections'));
    }

    /**
     * Changing the password or deactivating the user revokes all their tokens.
     */
    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $user->update($request->safe()->only(['name', 'email', 'phone', 'password', 'is_active']));

        if ($user->wasChanged('password') || ($user->wasChanged('is_active') && ! $user->is_active)) {
            $user->tokens()->delete();
        }

        return new UserResource($user->load('roles.permissions', 'sections'));
    }

    /**
     * Soft delete: deactivates the user and revokes their tokens.
     */
    public function destroy(User $user): UserResource
    {
        $this->authorize('delete', $user);

        $user->update(['is_active' => false]);
        $user->tokens()->delete();

        return new UserResource($user->load('roles.permissions', 'sections'));
    }
}
