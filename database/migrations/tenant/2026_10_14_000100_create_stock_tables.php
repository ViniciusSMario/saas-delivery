<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Unidades com fator para a unidade-base da dimensão (g, ml, un).
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name', 40);
            $table->string('dimension', 10);
            $table->decimal('factor', 14, 4);
            $table->timestamps();
        });

        DB::statement("alter table units add constraint units_dimension_check check (dimension in ('mass','volume','count'))");

        // Insumos. O saldo (quantity) é materializado: só muda junto com uma linha em stock_movements.
        Schema::create('stock_items', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 14, 3)->default(0);
            $table->decimal('min_quantity', 14, 3)->default(0);
            // Custo médio ponderado, em centavos por unidade do insumo (ex.: por kg).
            $table->bigInteger('average_cost_cents')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_item_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            // Positivo entra, negativo sai; sempre na unidade do insumo.
            $table->decimal('quantity', 14, 3);
            $table->decimal('balance_after', 14, 3);
            $table->bigInteger('unit_cost_cents')->nullable();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['stock_item_id', 'created_at']);
            $table->index(['type', 'created_at']);
        });

        DB::statement("alter table stock_movements add constraint stock_movements_type_check check (type in ('purchase','sale','adjustment','loss'))");
        DB::statement('alter table stock_movements add constraint stock_movements_quantity_check check (quantity <> 0)');

        // Ficha técnica: de um produto ou de um adicional.
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('addon_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        DB::statement('alter table recipes add constraint recipes_owner_check check ((product_id is null) <> (addon_id is null))');

        Schema::create('recipe_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_item_id')->constrained()->restrictOnDelete();
            // Quantidade na unidade escolhida na ficha (ex.: 200 g de um insumo controlado em kg).
            $table->decimal('quantity', 14, 3);
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->unique(['recipe_id', 'stock_item_id']);
        });

        DB::statement('alter table recipe_items add constraint recipe_items_quantity_check check (quantity > 0)');

        // Baixa idempotente: um pedido só baixa estoque uma vez.
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('stock_deducted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('stock_deducted_at'));
        Schema::dropIfExists('recipe_items');
        Schema::dropIfExists('recipes');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_items');
        Schema::dropIfExists('units');
    }
};
