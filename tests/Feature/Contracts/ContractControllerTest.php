<?php

namespace Tests\Feature\Contracts;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeErpDatabase;
use Tests\TestCase;

class ContractControllerTest extends TestCase
{
    use FakeErpDatabase, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeErpDatabase();

        $erp = DB::connection('erp');
        $erp->table('client')->insert([
            ['idcl' => 34160, 'den' => 'ROM CONTAINER RENT & SALE SRL   '],
            ['idcl' => 500, 'den' => 'BauTec GmbH'],
        ]);
        $erp->table('trzctr')->insert([
            ['idtrzctr' => 15194, 'nrcontr' => '116 - BUCURESTI', 'tipctr' => 'INCHIRIERE          ', 'idcl' => 34160, 'datast' => '2023-02-23 14:38:17', 'dataend' => '2025-05-31 14:38:17'],
            ['idtrzctr' => 15200, 'nrcontr' => '120 - CLUJ', 'tipctr' => 'VANZARE', 'idcl' => 500, 'datast' => null, 'dataend' => null],
            ['idtrzctr' => 15300, 'nrcontr' => '130 - FARA CONTAINERE', 'tipctr' => 'INCHIRIERE', 'idcl' => 500, 'datast' => null, 'dataend' => null],
        ]);
        $erp->table('trzdetctr')->insert([
            ['idtrzdetctr' => 47278, 'idtrzctr' => 15194, 'clasa' => 'CONTAINERS', 'grupa' => '20DV', 'art' => 'Inchiriere MSCU6041657', 'cant' => 1],
            ['idtrzdetctr' => 47281, 'idtrzctr' => 15194, 'clasa' => 'OFFICE UNITS', 'grupa' => 'OFFICE 20', 'art' => 'Inchiriere CMAU0073545', 'cant' => 1],
            ['idtrzdetctr' => 47290, 'idtrzctr' => 15194, 'clasa' => 'COMPONENTS', 'grupa' => 'AUXILIARE', 'art' => 'Inchiriere Scara Acces', 'cant' => 2],
            ['idtrzdetctr' => 48000, 'idtrzctr' => 15200, 'clasa' => 'CONTAINERS', 'grupa' => '40DV', 'art' => 'ECMU4644483', 'cant' => 1],
            ['idtrzdetctr' => 48100, 'idtrzctr' => 15300, 'clasa' => 'SERVICES', 'grupa' => 'TRANSPORT', 'art' => 'Transport', 'cant' => 1],
        ]);
    }

    public function test_lists_newest_contracts_with_containers_and_container_counts(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/contracts')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.id', 15200)
            ->assertJsonPath('data.0.containers_count', 1)
            ->assertJsonPath('data.1', [
                'id' => 15194,
                'number' => '116 - BUCURESTI',
                'type' => 'INCHIRIERE',
                'client' => ['id' => 34160, 'name' => 'ROM CONTAINER RENT & SALE SRL'],
                'starts_at' => '2023-02-23',
                'ends_at' => '2025-05-31',
                'containers_count' => 2,
            ]);
    }

    public function test_searches_by_contract_number_or_client_name(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/contracts?search=cluj')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', 15200);
        $this->getJson('/api/contracts?search=rom container')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', 15194);
    }

    public function test_shows_a_contract_with_its_container_units_only(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/contracts/15194')
            ->assertOk()
            ->assertJsonPath('data.containers', [
                ['id' => 47278, 'code' => 'MSCU6041657', 'article' => 'Inchiriere MSCU6041657', 'type' => '20DV', 'class' => 'CONTAINERS', 'quantity' => 1],
                ['id' => 47281, 'code' => 'CMAU0073545', 'article' => 'Inchiriere CMAU0073545', 'type' => 'OFFICE 20', 'class' => 'OFFICE UNITS', 'quantity' => 1],
            ]);
    }

    public function test_requires_the_projects_create_permission(): void
    {
        $this->getJson('/api/contracts')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->executant()->create());
        $this->getJson('/api/contracts')->assertForbidden();
        $this->getJson('/api/contracts/15194')->assertForbidden();

        Sanctum::actingAs(User::factory()->designer()->create());
        $this->getJson('/api/contracts/15194')->assertOk();
    }
}
