<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Importação do cardápio por planilha: histórico e o necessário para desfazer.
        Schema::create('menu_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('file_name', 200);
            $table->string('photos_file_name', 200)->nullable();
            // update = atualiza o que já existe; skip = só cria o que é novo.
            $table->string('mode', 10);
            $table->string('status', 10); // applied | undone
            // Totais: categorias, produtos e grupos criados/atualizados/pulados, fotos.
            $table->jsonb('summary');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('undone_at')->nullable();
            $table->foreignId('undone_by')->nullable()->constrained('users')->nullOnDelete();
        });

        // Cada registro que a importação criou ou alterou (com os valores de antes, para desfazer).
        Schema::create('menu_import_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_import_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type', 20); // category | product | addon_group | addon | image
            $table->unsignedBigInteger('subject_id');
            $table->string('action', 10); // created | updated
            $table->jsonb('before')->nullable();
            $table->index(['menu_import_id', 'subject_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_import_items');
        Schema::dropIfExists('menu_imports');
    }
};
