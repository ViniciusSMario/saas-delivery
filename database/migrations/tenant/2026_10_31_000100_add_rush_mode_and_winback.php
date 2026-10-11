<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Hora do sufoco: minutos somados à previsão quando o pedido entrou (a previsão
        // do pedido não muda quando a gerência volta ao normal).
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedSmallInteger('eta_extra_minutes')->default(0);
        });

        // Reativar clientes sumidos: quem foi chamado (e com qual cupom) e quem pediu para não ser chamado.
        Schema::table('customers', function (Blueprint $table) {
            $table->timestamp('winback_opt_out_at')->nullable();
        });

        Schema::create('customer_winbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('coupon_code', 40)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_winbacks');
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn('winback_opt_out_at'));
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('eta_extra_minutes'));
    }
};
