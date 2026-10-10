<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cadastro self-service: o cardápio só vai ao ar depois que o dono confirma o e-mail.
        // Estabelecimentos existentes e os criados pela equipe já entram confirmados (padrão: agora).
        Schema::table('tenants', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable()->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('email_verified_at');
        });
    }
};
