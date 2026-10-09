<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mesas do salão.
        Schema::create('tables', function (Blueprint $table) {
            $table->id();
            $table->string('name', 20)->unique();
            $table->unsignedSmallInteger('seats')->default(4);
            $table->string('area', 40)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        DB::statement('create sequence command_number_seq');

        // Comanda: conta de uma mesa (ou avulsa, ex.: "Cartão 15"). Os itens são pedidos com command_id.
        Schema::create('commands', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('number')->unique();
            $table->foreignId('table_id')->nullable()->constrained('tables')->nullOnDelete();
            $table->string('label', 40)->nullable();
            $table->string('status', 20)->index();
            $table->unsignedSmallInteger('people')->default(1);
            $table->boolean('service_fee_enabled')->default(true);
            $table->bigInteger('subtotal_cents')->default(0);
            $table->bigInteger('service_fee_cents')->default(0);
            $table->bigInteger('discount_cents')->default(0);
            $table->bigInteger('total_cents')->default(0);
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();
        });

        DB::statement("alter table commands alter column number set default nextval('command_number_seq')");
        DB::statement('alter sequence command_number_seq owned by commands.number');
        DB::statement("alter table commands add constraint commands_status_check check (status in ('open','closing','closed','cancelled'))");
        DB::statement('alter table commands add constraint commands_target_check check (table_id is not null or label is not null)');
        // Uma comanda aberta por mesa.
        DB::statement("create unique index commands_one_open_per_table on commands (table_id) where status in ('open','closing') and table_id is not null");

        // Pedidos lançados numa comanda vão para a cozinha como qualquer outro pedido.
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('command_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
        });

        DB::statement('alter table orders drop constraint orders_source_check');
        DB::statement("alter table orders add constraint orders_source_check check (source in ('online','counter','table'))");
        DB::statement('alter table orders drop constraint orders_fulfillment_check');
        DB::statement("alter table orders add constraint orders_fulfillment_check check (fulfillment in ('delivery','pickup','counter','table'))");

        Schema::create('cash_registers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_register_id')->constrained()->restrictOnDelete();
            $table->string('status', 10)->index();
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->bigInteger('opening_cents');
            // No fechamento: esperado e contado por forma de pagamento (código => centavos).
            $table->jsonb('expected')->nullable();
            $table->jsonb('counted')->nullable();
            $table->bigInteger('difference_cents')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
        });

        DB::statement("alter table cash_sessions add constraint cash_sessions_status_check check (status in ('open','closed'))");
        // Um caixa físico só tem uma sessão aberta, e cada operador só abre um caixa por vez.
        DB::statement("create unique index cash_sessions_one_open_per_register on cash_sessions (cash_register_id) where status = 'open'");
        DB::statement("create unique index cash_sessions_one_open_per_user on cash_sessions (opened_by) where status = 'open'");

        // Livro do caixa: toda entrada e saída, com a forma de pagamento.
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_session_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            // Sempre positivo; o tipo diz se entra ou sai.
            $table->bigInteger('amount_cents');
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('command_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['cash_session_id', 'type']);
        });

        DB::statement("alter table cash_movements add constraint cash_movements_type_check check (type in ('opening','sale','withdrawal','deposit','refund'))");
        DB::statement('alter table cash_movements add constraint cash_movements_amount_check check (amount_cents > 0)');

        Schema::table('order_payments', function (Blueprint $table) {
            $table->foreignId('cash_session_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_payments', fn (Blueprint $table) => $table->dropConstrainedForeignId('cash_session_id'));
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('cash_sessions');
        Schema::dropIfExists('cash_registers');

        DB::statement("delete from orders where source = 'table'");
        DB::statement('alter table orders drop constraint orders_source_check');
        DB::statement("alter table orders add constraint orders_source_check check (source in ('online','counter'))");
        DB::statement('alter table orders drop constraint orders_fulfillment_check');
        DB::statement("alter table orders add constraint orders_fulfillment_check check (fulfillment in ('delivery','pickup','counter'))");
        Schema::table('orders', fn (Blueprint $table) => $table->dropConstrainedForeignId('command_id'));

        Schema::dropIfExists('commands');
        Schema::dropIfExists('tables');
    }
};
