<?php

namespace Tests\Feature;

use App\Models\Erp\ErpModel;
use App\Models\User;
use Tests\TestCase;

class ErpModelConnectionTest extends TestCase
{
    public function test_app_models_use_the_configured_table_prefix(): void
    {
        $user = new User;

        $this->assertSame('ct_', $user->getConnection()->getTablePrefix());
    }

    public function test_erp_models_use_the_unprefixed_erp_connection(): void
    {
        $erpModel = new class extends ErpModel
        {
            protected $table = 'Articole';
        };

        $this->assertSame('erp', $erpModel->getConnectionName());
        $this->assertSame('', $erpModel->getConnection()->getTablePrefix());
        $this->assertSame('select * from [Articole]', $erpModel->newQuery()->toSql());
    }
}
