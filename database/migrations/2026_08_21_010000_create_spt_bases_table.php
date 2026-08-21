<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spt_bases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('spt_id');
            $table->text('content');
            $table->unsignedSmallInteger('sort_order');
            $table->timestamps();

            $table->foreign('spt_id')->references('id')->on('spts')->cascadeOnDelete();
            $table->unique(['spt_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spt_bases');
    }
};
