<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The ERP tables are external; tests point the `erp` connection at an in-memory SQLite copy
 * of the columns the app reads (contracts, contract lines, clients, materials).
 */
trait FakeErpDatabase
{
    protected function fakeErpDatabase(): void
    {
        config(['database.connections.erp' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('erp');

        $schema = Schema::connection('erp');
        $schema->create('client', function (Blueprint $table) {
            $table->integer('idcl')->primary();
            $table->string('den');
            $table->boolean('activ')->default(true);
        });
        $schema->create('trzctr', function (Blueprint $table) {
            $table->integer('idtrzctr')->primary();
            $table->string('nrcontr');
            $table->string('tipctr')->nullable();
            $table->integer('idcl');
            $table->dateTime('datast')->nullable();
            $table->dateTime('dataend')->nullable();
        });
        $schema->create('trzdetctr', function (Blueprint $table) {
            $table->integer('idtrzdetctr')->primary();
            $table->integer('idtrzctr');
            $table->string('clasa')->nullable();
            $table->string('grupa')->nullable();
            $table->string('art')->nullable();
            $table->decimal('cant', 10, 3)->default(1);
        });
        $schema->create('genmp', function (Blueprint $table) {
            $table->integer('idmp')->primary();
            $table->string('clasa')->nullable();
            $table->string('grupa')->nullable();
            $table->string('art')->nullable();
            $table->string('desc1')->nullable();
            $table->string('um')->nullable();
            $table->boolean('activ')->default(true);
        });
    }
}
