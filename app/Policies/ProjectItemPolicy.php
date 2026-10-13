<?php

namespace App\Policies;

use App\Models\ProjectItem;
use App\Models\User;

class ProjectItemPolicy
{
    /**
     * Change the status / actual quantity of the item: same rule as working on its section.
     */
    public function work(User $user, ProjectItem $projectItem): bool
    {
        return (new SectionPolicy)->work($user, $projectItem->section);
    }
}
