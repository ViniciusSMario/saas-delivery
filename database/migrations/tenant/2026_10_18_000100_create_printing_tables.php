<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Setores de produção (cozinha, bar, expedição), cada um com a sua impressora.
        Schema::create('print_stations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            // browser: estação de impressão aberta num computador; network: impressora na rede (ESC/POS).
            $table->string('mode', 20)->default('browser');
            $table->string('host', 120)->nullable();
            $table->unsignedSmallInteger('port')->default(9100);
            // Colunas do papel: 48 (80 mm) ou 32 (58 mm).
            $table->unsignedSmallInteger('columns')->default(48);
            $table->unsignedSmallInteger('copies')->default(1);
            // Recebe o pedido completo, com valores (expedição/caixa), além dos itens do setor.
            $table->boolean('prints_full_order')->default(false);
            // Itens sem setor definido na categoria vão para cá.
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::statement("alter table print_stations add constraint print_stations_mode_check check (mode in ('browser','network'))");

        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('print_station_id')->nullable()->constrained('print_stations')->nullOnDelete();
        });

        Schema::create('print_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('print_station_id')->constrained('print_stations')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('title', 120);
            // Linhas do cupom: [{text, style, align}], desenhadas em HTML ou ESC/POS.
            $table->jsonb('lines');
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('printed_at')->nullable();
            $table->timestamps();
            $table->index(['print_station_id', 'status']);
        });

        DB::statement("alter table print_jobs add constraint print_jobs_kind_check check (kind in ('production','receipt','test'))");
        DB::statement("alter table print_jobs add constraint print_jobs_status_check check (status in ('pending','printing','printed','failed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('print_jobs');

        Schema::table('categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('print_station_id');
        });

        Schema::dropIfExists('print_stations');
    }
};
