<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoUsersSeeder extends Seeder
{
    /**
     * The demo accounts from the UI (ui/src/data/mockData.js), so "Vezi conturi demo" keeps working.
     *
     * @var list<array{name: string, email: string, password: string, phone: string, role: string, sections: list<string>}>
     */
    public const USERS = [
        ['name' => 'Andrei Popescu', 'email' => 'andrei.popescu@befu-containere.ro', 'password' => 'admin123', 'phone' => '+40 722 104 551', 'role' => Role::ADMIN, 'sections' => []],
        ['name' => 'Ioana Marin', 'email' => 'ioana.marin@befu-containere.ro', 'password' => 'design123', 'phone' => '+40 733 218 902', 'role' => Role::DESIGNER, 'sections' => []],
        ['name' => 'Vasile Ionescu', 'email' => 'vasile.ionescu@befu-containere.ro', 'password' => 'exec123', 'phone' => '+40 744 556 213', 'role' => Role::EXECUTANT, 'sections' => ['Sudură']],
        ['name' => 'Mihai Radu', 'email' => 'mihai.radu@befu-containere.ro', 'password' => 'exec123', 'phone' => '+40 755 902 187', 'role' => Role::EXECUTANT, 'sections' => ['Sudură', 'Vopsitorie']],
        ['name' => 'Elena Dumitru', 'email' => 'elena.dumitru@befu-containere.ro', 'password' => 'exec123', 'phone' => '+40 766 341 029', 'role' => Role::EXECUTANT, 'sections' => ['Asamblare']],
        ['name' => 'Cristian Neagu', 'email' => 'cristian.neagu@befu-containere.ro', 'password' => 'exec123', 'phone' => '+40 777 488 650', 'role' => Role::EXECUTANT, 'sections' => ['Control calitate']],
    ];

    /**
     * Seed the demo accounts. Requires roles and sections to be seeded first.
     */
    public function run(): void
    {
        $roles = Role::query()->pluck('id', 'slug');
        $sections = Section::query()->pluck('id', 'name');

        foreach (self::USERS as $demo) {
            $user = User::query()->updateOrCreate(['email' => $demo['email']], [
                'name' => $demo['name'],
                'phone' => $demo['phone'],
                'password' => $demo['password'],
                'is_active' => true,
            ]);

            $user->roles()->sync([$roles[$demo['role']]]);
            $user->sections()->sync($sections->only($demo['sections'])->values());
        }
    }
}
