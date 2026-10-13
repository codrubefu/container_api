<?php

namespace Tests\Feature;

use App\Models\Erp\Contract;
use App\Models\Erp\ContractDetail;
use Tests\TestCase;

class ErpContractTest extends TestCase
{
    public function test_contract_maps_to_the_unprefixed_erp_table(): void
    {
        $contract = new Contract;

        $this->assertSame('erp', $contract->getConnectionName());
        $this->assertSame('trzctr', $contract->getTable());
        $this->assertSame('idtrzctr', $contract->getKeyName());
        $this->assertFalse($contract->usesTimestamps());
        $this->assertSame('select * from [trzctr]', $contract->newQuery()->toSql());
    }

    public function test_contract_detail_maps_to_the_unprefixed_erp_table(): void
    {
        $contractDetail = new ContractDetail;

        $this->assertSame('erp', $contractDetail->getConnectionName());
        $this->assertSame('trzdetctr', $contractDetail->getTable());
        $this->assertSame('idtrzdetctr', $contractDetail->getKeyName());
        $this->assertFalse($contractDetail->usesTimestamps());
    }

    public function test_contract_has_many_details_by_idtrzctr(): void
    {
        $details = (new Contract)->details();

        $this->assertSame('idtrzctr', $details->getForeignKeyName());
        $this->assertSame('idtrzctr', $details->getLocalKeyName());
        $this->assertInstanceOf(ContractDetail::class, $details->getRelated());
    }

    public function test_contract_detail_belongs_to_contract_by_idtrzctr(): void
    {
        $contract = (new ContractDetail)->contract();

        $this->assertSame('idtrzctr', $contract->getForeignKeyName());
        $this->assertSame('idtrzctr', $contract->getOwnerKeyName());
        $this->assertInstanceOf(Contract::class, $contract->getRelated());
    }
}
