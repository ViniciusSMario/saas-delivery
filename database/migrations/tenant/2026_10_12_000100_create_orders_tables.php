<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            // Só dígitos (DDD + número). Identifica o cliente do pedido online.
            $table->string('phone', 15)->unique();
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('last_order_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('zip_code', 9)->nullable();
            $table->string('street');
            $table->string('number', 20);
            $table->string('complement')->nullable();
            $table->string('district');
            $table->string('city')->nullable();
            $table->string('reference')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 60);
            $table->boolean('is_active')->default(true);
            // Dinheiro: o cliente pode pedir troco.
            $table->boolean('accepts_change')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        // Número do pedido: sequência própria do schema, então cada estabelecimento começa do 1.
        DB::statement('create sequence order_number_seq');

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('number')->unique();
            // Link de acompanhamento do cliente (não adivinhável).
            $table->string('public_token', 40)->unique();
            $table->string('source', 20);
            $table->string('fulfillment', 20);
            $table->string('status', 20)->index();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name', 120)->nullable();
            $table->string('customer_phone', 15)->nullable();
            // Endereço copiado no momento do pedido.
            $table->string('address_zip_code', 9)->nullable();
            $table->string('address_street')->nullable();
            $table->string('address_number', 20)->nullable();
            $table->string('address_complement')->nullable();
            $table->string('address_district')->nullable();
            $table->string('address_city')->nullable();
            $table->string('address_reference')->nullable();
            $table->text('notes')->nullable();
            $table->bigInteger('subtotal_cents');
            $table->bigInteger('discount_cents')->default(0);
            $table->bigInteger('delivery_fee_cents')->default(0);
            $table->bigInteger('total_cents');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancel_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index('created_at');
        });

        DB::statement("alter table orders alter column number set default nextval('order_number_seq')");
        DB::statement('alter sequence order_number_seq owned by orders.number');
        DB::statement("alter table orders add constraint orders_source_check check (source in ('online','counter'))");
        DB::statement("alter table orders add constraint orders_fulfillment_check check (fulfillment in ('delivery','pickup','counter'))");
        DB::statement("alter table orders add constraint orders_status_check check (status in ('received','preparing','ready','dispatched','completed','cancelled'))");
        DB::statement('alter table orders add constraint orders_amounts_check check (subtotal_cents >= 0 and discount_cents >= 0 and delivery_fee_cents >= 0 and total_cents >= 0 and discount_cents <= subtotal_cents)');

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('combo_id')->nullable()->constrained()->restrictOnDelete();
            // Retrato do item no momento da venda.
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->bigInteger('unit_price_cents');
            $table->unsignedSmallInteger('quantity');
            $table->bigInteger('addons_cents')->default(0);
            $table->bigInteger('total_cents');
            $table->string('notes', 255)->nullable();
        });

        DB::statement('alter table order_items add constraint order_items_quantity_check check (quantity >= 1)');
        DB::statement('alter table order_items add constraint order_items_kind_check check ((product_id is null) <> (combo_id is null))');

        Schema::create('order_item_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('addon_id')->nullable()->constrained()->nullOnDelete();
            $table->string('group_name', 80);
            $table->string('name', 80);
            $table->bigInteger('unit_price_cents');
        });

        Schema::create('order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('order_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount_cents');
            $table->bigInteger('change_for_cents')->nullable();
            $table->string('status', 20);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        DB::statement("alter table order_payments add constraint order_payments_status_check check (status in ('pending','paid','refunded'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('order_payments');
        Schema::dropIfExists('order_status_history');
        Schema::dropIfExists('order_item_addons');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        DB::statement('drop sequence if exists order_number_seq');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('customer_addresses');
        Schema::dropIfExists('customers');
    }
};
