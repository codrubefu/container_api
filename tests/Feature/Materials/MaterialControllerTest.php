<?php

namespace Tests\Feature\Materials;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeErpDatabase;
use Tests\TestCase;

class MaterialControllerTest extends TestCase
{
    use FakeErpDatabase, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeErpDatabase();

        DB::connection('erp')->table('genmp')->insert([
            ['idmp' => 237, 'clasa' => 'Productie Continere', 'grupa' => 'Materiale Consumab', 'art' => 'sarma sudura 1.0 mm   ', 'desc1' => '   ', 'um' => 'KGR', 'activ' => true],
            ['idmp' => 25707, 'clasa' => 'Productie Continere', 'grupa' => 'Structura Container', 'art' => 'tabla striata 4x1500x6000', 'desc1' => 'tabla striata 4x1500x6000', 'um' => 'KGR', 'activ' => true],
            ['idmp' => 283, 'clasa' => 'Productie Continere', 'grupa' => 'Dotari suplimentare', 'art' => 'AC-12000', 'desc1' => 'aer conditionat 12000 btu', 'um' => 'BUC', 'activ' => true],
            ['idmp' => 900, 'clasa' => 'Productie Continere', 'grupa' => 'Structura Container', 'art' => 'teava veche', 'desc1' => 'teava veche', 'um' => 'M', 'activ' => false],
            ['idmp' => 901, 'clasa' => 'Manopera', 'grupa' => 'Manopera', 'art' => 'ora manopera', 'desc1' => 'ora manopera', 'um' => 'ORA', 'activ' => true],
        ]);
    }

    public function test_lists_active_production_materials_by_article_falling_back_to_it_for_blank_names(): void
    {
        Sanctum::actingAs(User::factory()->designer()->create());

        $this->getJson('/api/materials')
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data', [
                ['id' => 283, 'code' => 'AC-12000', 'name' => 'aer conditionat 12000 btu', 'unit' => 'BUC', 'category' => 'Dotari suplimentare'],
                ['id' => 237, 'code' => 'sarma sudura 1.0 mm', 'name' => 'sarma sudura 1.0 mm', 'unit' => 'KGR', 'category' => 'Materiale Consumab'],
                ['id' => 25707, 'code' => 'tabla striata 4x1500x6000', 'name' => 'tabla striata 4x1500x6000', 'unit' => 'KGR', 'category' => 'Structura Container'],
            ]);
    }

    public function test_filters_by_name_or_code_and_by_category(): void
    {
        Sanctum::actingAs(User::factory()->designer()->create());

        $this->getJson('/api/materials?search=ac-120')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', 283);
        $this->getJson('/api/materials?search=sudura')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', 237);
        $this->getJson('/api/materials?category=Structura Container')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', 25707);
    }

    public function test_lists_the_production_categories(): void
    {
        Sanctum::actingAs(User::factory()->designer()->create());

        $this->getJson('/api/material-categories')
            ->assertOk()
            ->assertExactJson(['data' => ['Dotari suplimentare', 'Materiale Consumab', 'Structura Container']]);
    }

    public function test_requires_the_project_items_manage_permission(): void
    {
        $this->getJson('/api/materials')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->executant()->create());
        $this->getJson('/api/materials')->assertForbidden();
        $this->getJson('/api/material-categories')->assertForbidden();
    }
}
