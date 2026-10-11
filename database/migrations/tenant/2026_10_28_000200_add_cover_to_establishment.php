<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Foto de capa do cardápio público (topo da página). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('establishment', function (Blueprint $table) {
            $table->string('cover_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('establishment', function (Blueprint $table) {
            $table->dropColumn('cover_path');
        });
    }
};
