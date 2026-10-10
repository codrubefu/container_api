<?php

namespace Tests\Feature\Users;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SectionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_the_seeded_sections_in_ui_order(): void
    {
        Sanctum::actingAs(User::factory()->executant()->create());

        $this->getJson('/api/sections')
            ->assertOk()
            ->assertExactJson(['data' => [
                ['id' => 1, 'name' => 'Sudură'],
                ['id' => 2, 'name' => 'Vopsitorie'],
                ['id' => 3, 'name' => 'Asamblare'],
                ['id' => 4, 'name' => 'Control calitate'],
            ]]);
    }
}
