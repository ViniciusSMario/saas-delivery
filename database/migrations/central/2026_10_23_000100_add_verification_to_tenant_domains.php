<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Domínio próprio: o dono prova que é dele com um registro TXT contendo este código.
        Schema::table('tenant_domains', function (Blueprint $table) {
            $table->string('verification_token', 64)->nullable();
            $table->timestamp('last_checked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenant_domains', function (Blueprint $table) {
            $table->dropColumn(['verification_token', 'last_checked_at']);
        });
    }
};
