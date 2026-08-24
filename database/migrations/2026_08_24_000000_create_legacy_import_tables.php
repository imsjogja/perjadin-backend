<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_import_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('source_connection', 100);
            $table->string('source_database', 100);
            $table->boolean('dry_run')->default(false);
            $table->string('status', 30);
            $table->json('summary')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('legacy_import_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('batch_id')->nullable();
            $table->string('source_database', 100);
            $table->string('source_table', 100);
            $table->unsignedBigInteger('source_id');
            $table->string('target_table', 100)->nullable();
            $table->uuid('target_id')->nullable();
            $table->string('source_checksum', 64);
            $table->string('status', 30);
            $table->text('message')->nullable();
            $table->timestamps();

            $table->index('batch_id', 'legacy_import_records_batch_index');
            $table->unique(
                ['source_database', 'source_table', 'source_id'],
                'legacy_import_source_row_unique'
            );
        });

        Schema::create('legacy_import_issues', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('batch_id')->nullable();
            $table->uuid('record_id')->nullable();
            $table->string('entity_type', 100);
            $table->string('source_table', 100);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('code', 100);
            $table->text('message');
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['batch_id', 'code'], 'legacy_import_issues_batch_code_index');
            $table->index(['source_table', 'source_id'], 'legacy_import_issues_source_row_index');
        });

        Schema::create('legacy_unit_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('source_database', 100);
            $table->unsignedBigInteger('legacy_unit_id');
            $table->uuid('sikkepo_unit_id');
            $table->json('unit_snapshot')->nullable();
            $table->timestamps();

            $table->unique(
                ['source_database', 'legacy_unit_id'],
                'legacy_unit_mappings_source_unit_unique'
            );
        });

        Schema::create('legacy_employee_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('source_database', 100);
            $table->unsignedBigInteger('legacy_employee_id');
            $table->string('nip', 25);
            $table->uuid('sikkepo_pegawai_id');
            $table->json('employee_snapshot')->nullable();
            $table->timestamps();

            $table->unique(
                ['source_database', 'legacy_employee_id'],
                'legacy_employee_mappings_source_employee_unique'
            );
            $table->index(['source_database', 'nip'], 'legacy_employee_mappings_source_nip_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_employee_mappings');
        Schema::dropIfExists('legacy_unit_mappings');
        Schema::dropIfExists('legacy_import_issues');
        Schema::dropIfExists('legacy_import_records');
        Schema::dropIfExists('legacy_import_batches');
    }
};
