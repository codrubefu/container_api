<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\Section;
use App\Models\User;

class SectionPolicy
{
    /**
     * Whether the user may work on project items of the section (change status / actual quantity).
     * Executants need `project_items.work` AND the section must be assigned to them; admins may
     * work on any section. A future ProjectItemPolicy should delegate here with `$item->section`.
     */
    public function work(User $user, Section $section): bool
    {
        if ($user->hasRole(Role::ADMIN)) {
            return true;
        }

        return $user->hasPermission('project_items.work') && $user->belongsToSection($section);
    }
}
