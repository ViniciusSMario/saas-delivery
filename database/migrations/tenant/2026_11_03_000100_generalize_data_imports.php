<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // O histórico de importação deixa de ser só do cardápio: clientes, bairros, estoque e fichas.
        Schema::rename('menu_imports', 'data_imports');
        Schema::rename('menu_import_items', 'data_import_items');

        Schema::table('data_imports', function (Blueprint $table) {
            $table->string('kind', 20)->default('menu');
            $table->index(['kind', 'id']);
        });

        Schema::table('data_import_items', function (Blueprint $table) {
            $table->renameColumn('menu_import_id', 'data_import_id');
        });
    }

    public function down(): void
    {
        Schema::table('data_import_items', fn (Blueprint $table) => $table->renameColumn('data_import_id', 'menu_import_id'));
        Schema::table('data_imports', function (Blueprint $table) {
            $table->dropIndex(['kind', 'id']);
            $table->dropColumn('kind');
        });
        Schema::rename('data_import_items', 'menu_import_items');
        Schema::rename('data_imports', 'menu_imports');
    }
};
