<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('description')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'position']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            // Código curto para o PDV (ex.: "101"). Único entre os produtos não excluídos.
            $table->string('code', 20)->nullable();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->bigInteger('price_cents');
            $table->boolean('is_active')->default(true);
            // Esgotado temporariamente: continua no cardápio, mas não pode ser pedido.
            $table->boolean('is_available')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['category_id', 'position']);
        });

        DB::statement('alter table products add constraint products_price_check check (price_cents >= 0)');
        DB::statement('create unique index products_code_unique on products (lower(code)) where deleted_at is null and code is not null');
        // Busca rápida por nome no PDV (ILIKE '%calab%'). gin_trgm_ops vem do schema "extensions".
        DB::statement('create index products_name_trgm on products using gin (name gin_trgm_ops)');

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->index(['product_id', 'position']);
        });

        Schema::create('addon_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            // Ex.: "Escolha a borda" (mín. 1, máx. 1) ou "Adicionais" (mín. 0, máx. 5).
            $table->unsignedSmallInteger('min_choices')->default(0);
            $table->unsignedSmallInteger('max_choices')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::statement('alter table addon_groups add constraint addon_groups_choices_check check (max_choices >= 1 and max_choices >= min_choices)');

        Schema::create('addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('addon_group_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->bigInteger('price_cents')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        DB::statement('alter table addons add constraint addons_price_check check (price_cents >= 0)');

        Schema::create('product_addon_groups', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('addon_group_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->primary(['product_id', 'addon_group_id']);
        });

        Schema::create('combos', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->bigInteger('price_cents');
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('alter table combos add constraint combos_price_check check (price_cents >= 0)');

        Schema::create('combo_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('combo_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->unique(['combo_id', 'product_id']);
        });

        DB::statement('alter table combo_items add constraint combo_items_quantity_check check (quantity >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('combo_items');
        Schema::dropIfExists('combos');
        Schema::dropIfExists('product_addon_groups');
        Schema::dropIfExists('addons');
        Schema::dropIfExists('addon_groups');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
    }
};
