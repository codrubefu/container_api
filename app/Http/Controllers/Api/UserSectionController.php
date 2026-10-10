<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\SyncUserSectionsRequest;
use App\Http\Resources\UserResource;
use App\Models\User;

class UserSectionController extends Controller
{
    /**
     * Replace the sections assigned to the user.
     */
    public function update(SyncUserSectionsRequest $request, User $user): UserResource
    {
        $user->sections()->sync($request->validated('section_ids'));

        return new UserResource($user->load('roles.permissions', 'sections'));
    }
}
