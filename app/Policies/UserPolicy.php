<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    /**
     * Deactivating a user ("delete" is a soft deactivation). Nobody can deactivate their own account.
     */
    public function delete(User $user, User $model): Response
    {
        if (! $user->hasPermission('users.manage')) {
            return Response::deny();
        }

        return $user->is($model)
            ? Response::deny('Nu vă puteți dezactiva propriul cont.')
            : Response::allow();
    }

    /**
     * Changing a user's role. Nobody can change their own role, so an admin cannot lock themselves out.
     */
    public function changeRole(User $user, User $model): Response
    {
        if (! $user->hasPermission('users.manage')) {
            return Response::deny();
        }

        return $user->is($model)
            ? Response::deny('Nu vă puteți schimba propriul rol.')
            : Response::allow();
    }
}
