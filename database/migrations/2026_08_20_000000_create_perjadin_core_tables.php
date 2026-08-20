<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 20);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['document_type', 'year']);
        });

        Schema::create('spts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('unit_id');
            $table->unsignedSmallInteger('document_year');
            $table->unsignedInteger('sequence_number');
            $table->string('registration_number', 16);
            $table->string('document_number', 100);
            $table->text('dasar');
            $table->text('disposisi')->nullable();
            $table->text('dalam_rangka');
            $table->string('issued_place', 150);
            $table->date('issued_date');
            $table->unsignedInteger('assignment_revision')->default(0);
            $table->timestamp('assignment_updated_at')->nullable();
            $table->foreignId('assignment_updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['document_year', 'registration_number']);
            $table->unique(['document_year', 'document_number']);
        });

        Schema::create('spt_destinations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('spt_id')->unique();
            $table->string('transportation', 100);
            $table->string('departure_place', 150);
            $table->string('destination_place', 150);
            $table->unsignedSmallInteger('duration_days');
            $table->timestamps();

            $table->foreign('spt_id')->references('id')->on('spts')->cascadeOnDelete();
        });

        Schema::create('spt_assignees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('spt_id');
            $table->uuid('sikkepo_pegawai_id');
            $table->json('employee_snapshot');
            $table->unsignedInteger('assignment_revision');
            $table->timestamp('assigned_at');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('spt_id')->references('id')->on('spts')->cascadeOnDelete();
            $table->unique(['spt_id', 'sikkepo_pegawai_id']);
        });

        Schema::create('spt_signatories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('spt_id')->unique();
            $table->uuid('sikkepo_pegawai_id');
            $table->json('employee_snapshot');
            $table->string('behalf_of', 200)->nullable();
            $table->string('signatory_role', 200)->nullable();
            $table->boolean('is_acting')->default(false);
            $table->timestamps();

            $table->foreign('spt_id')->references('id')->on('spts')->cascadeOnDelete();
        });

        Schema::create('sppds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('spt_id');
            $table->uuid('unit_id');
            $table->uuid('sikkepo_pegawai_id');
            $table->json('employee_snapshot');
            $table->unsignedSmallInteger('document_year');
            $table->unsignedInteger('sequence_number');
            $table->string('registration_number', 16);
            $table->string('document_number', 100);
            $table->string('order_giver', 200);
            $table->string('travel_level', 100)->nullable();
            $table->string('travel_type', 100)->nullable();
            $table->date('departure_date');
            $table->date('return_date');
            $table->string('budget_agency', 200);
            $table->string('budget_account', 200)->nullable();
            $table->text('description')->nullable();
            $table->string('issued_place', 150);
            $table->date('issued_date');
            $table->string('status', 20)->default('draft');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('spt_id')->references('id')->on('spts')->restrictOnDelete();
            $table->index(['spt_id', 'sikkepo_pegawai_id']);
            $table->unique(['document_year', 'registration_number']);
            $table->unique(['document_year', 'document_number']);
        });

        Schema::create('sppd_followers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('sppd_id');
            $table->uuid('sikkepo_pegawai_id');
            $table->json('employee_snapshot');
            $table->timestamps();

            $table->foreign('sppd_id')->references('id')->on('sppds')->cascadeOnDelete();
            $table->unique(['sppd_id', 'sikkepo_pegawai_id']);
        });

        Schema::create('sppd_signatories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('sppd_id')->unique();
            $table->uuid('sikkepo_pegawai_id');
            $table->json('employee_snapshot');
            $table->string('behalf_of', 200)->nullable();
            $table->string('signatory_role', 200)->nullable();
            $table->boolean('is_acting')->default(false);
            $table->timestamps();

            $table->foreign('sppd_id')->references('id')->on('sppds')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sppd_signatories');
        Schema::dropIfExists('sppd_followers');
        Schema::dropIfExists('sppds');
        Schema::dropIfExists('spt_signatories');
        Schema::dropIfExists('spt_assignees');
        Schema::dropIfExists('spt_destinations');
        Schema::dropIfExists('spts');
        Schema::dropIfExists('document_sequences');
    }
};
