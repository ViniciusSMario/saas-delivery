<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fidelidade: o prêmio é um cupom pessoal (só vale para aquele cliente).
        Schema::table('coupons', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::create('loyalty_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            // Pedido que completou o cartão.
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            // Pedidos "gastos" neste prêmio (a meta pode mudar depois).
            $table->unsignedSmallInteger('stamps');
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['customer_id', 'created_at']);
        });

        // Pedido pela mesa: QR code próprio de cada mesa e o chamado do garçom.
        Schema::table('tables', function (Blueprint $table) {
            $table->string('qr_token', 40)->nullable()->unique();
            $table->timestamp('called_at')->nullable();
            $table->string('call_reason', 20)->nullable();
        });

        DB::statement("update tables set qr_token = substr(md5(random()::text || id::text || clock_timestamp()::text), 1, 24)");

        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('via_qr')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('via_qr'));
        Schema::table('tables', fn (Blueprint $table) => $table->dropColumn(['qr_token', 'called_at', 'call_reason']));
        Schema::dropIfExists('loyalty_rewards');
        Schema::table('coupons', fn (Blueprint $table) => $table->dropConstrainedForeignId('customer_id'));
    }
};
