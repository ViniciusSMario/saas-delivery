<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cada vez que a equipe do SaaS entra num estabelecimento como suporte.
        Schema::create('support_accesses', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('platform_user_id')->nullable()->constrained('platform_users')->nullOnDelete();
            $table->string('platform_email');
            $table->unsignedBigInteger('tenant_user_id');
            $table->string('tenant_user_name')->nullable();
            $table->string('reason', 300);
            // Link de uso único, válido por poucos minutos.
            $table->string('token_hash', 64)->unique();
            $table->string('ip', 45)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_accesses');
    }
};
