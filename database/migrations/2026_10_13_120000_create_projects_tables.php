<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Projects are created from an ERP contract (dbo.trzctr) and keep a snapshot of the contract, its
 * container lines (dbo.trzdetctr) and the planned materials (dbo.genmp), so they do not change when the ERP does.
 *
 * SQL Server rejects multiple cascade paths, so cascades only follow project → container → item → comment;
 * user and section references use no action (users are deactivated, never deleted).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contract_id')->unique();
            $table->string('contract_number', 100);
            $table->string('client_name')->nullable();
            $table->string('name');
            $table->date('deadline')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('project_containers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('contract_line_id');
            $table->string('code', 100);
            $table->string('type', 50)->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'contract_line_id']);
        });

        Schema::create('project_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_container_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained();
            $table->unsignedBigInteger('material_id');
            $table->string('material_code', 100);
            $table->string('material_name');
            $table->string('material_unit', 10)->nullable();
            $table->decimal('planned_qty', 12, 3);
            $table->decimal('actual_qty', 12, 3)->nullable();
            $table->string('status', 20)->default('neinceput')->index();
            $table->foreignId('worker_id')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('project_item_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->string('author_name');
            $table->text('body');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_item_comments');
        Schema::dropIfExists('project_items');
        Schema::dropIfExists('project_containers');
        Schema::dropIfExists('projects');
    }
};
