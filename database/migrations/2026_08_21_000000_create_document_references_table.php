<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_references', function (Blueprint $table) {
            $table->id();
            $table->string('category', 50);
            $table->string('value', 200);
            $table->timestamps();

            $table->unique(['category', 'value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_references');
    }
};
