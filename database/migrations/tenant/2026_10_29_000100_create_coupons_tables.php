<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cupons de desconto do cardápio online (código digitado pelo cliente no checkout).
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30);
            $table->string('description', 160)->nullable();
            $table->string('type', 20); // percent | fixed | free_delivery
            $table->unsignedSmallInteger('percent')->nullable();
            $table->bigInteger('amount_cents')->nullable();
            $table->bigInteger('max_discount_cents')->nullable(); // teto do desconto em %
            $table->bigInteger('min_order_cents')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('usage_limit')->nullable(); // usos no total
            $table->unsignedSmallInteger('per_customer_limit')->nullable(); // usos por telefone
            $table->boolean('first_order_only')->default(false);
            $table->boolean('is_public')->default(false); // aparece no cardápio
            $table->boolean('show_after_order')->default(false); // "volte e ganhe": oferecido no pedido entregue
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Código único entre os cupons não excluídos (sempre gravado em maiúsculas).
        DB::statement('create unique index coupons_code_unique on coupons (code) where deleted_at is null');
        DB::statement("alter table coupons add constraint coupons_type_check check (type in ('percent','fixed','free_delivery'))");

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('customer_phone', 15)->nullable()->index();
            $table->bigInteger('discount_cents');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('coupon_code', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('coupon_code');
        });
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('coupons');
    }
};
