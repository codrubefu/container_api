<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Default permission slugs for each role. `*` grants every permission.
     *
     * @var array<string, array{name: string, permissions: list<string>}>
     */
    public const ROLES = [
        Role::ADMIN => [
            'name' => 'Administrator',
            'permissions' => ['*'],
        ],
        Role::DESIGNER => [
            'name' => 'Proiectant',
            'permissions' => [
                'projects.view',
                'projects.create',
                'projects.close',
                'project_items.manage',
                'comments.create',
                'sections.view',
            ],
        ],
        Role::EXECUTANT => [
            'name' => 'Executant',
            'permissions' => [
                'projects.view',
                'project_items.work',
                'comments.create',
                'sections.view',
            ],
        ],
    ];

    /**
     * Seed the permissions and the default roles. Safe to run repeatedly.
     */
    public function run(): void
    {
        foreach (Permission::DEFINITIONS as $slug => $description) {
            Permission::query()->updateOrCreate(['slug' => $slug], ['description' => $description]);
        }

        $permissionIds = Permission::query()->pluck('id', 'slug');

        foreach (self::ROLES as $slug => $definition) {
            $role = Role::query()->updateOrCreate(['slug' => $slug], ['name' => $definition['name']]);

            $slugs = $definition['permissions'] === ['*']
                ? $permissionIds->keys()->all()
                : $definition['permissions'];

            $role->permissions()->sync($permissionIds->only($slugs)->values());
        }
    }
}
