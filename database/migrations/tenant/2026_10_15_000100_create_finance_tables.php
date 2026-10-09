<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // LGPD: cliente anonimizado a pedido dele.
        Schema::table('customers', function (Blueprint $table) {
            $table->timestamp('anonymized_at')->nullable();
        });

        // Onde o dinheiro está: caixa da loja, banco, Pix, maquininha.
        Schema::create('financial_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->string('type', 20);
            $table->bigInteger('opening_balance_cents')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::statement("alter table financial_accounts add constraint financial_accounts_type_check check (type in ('cash','bank','card','other'))");

        // Forma de pagamento → conta onde o dinheiro cai (usado no fechamento do caixa).
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->foreignId('financial_account_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::create('financial_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->string('kind', 10);
            $table->unique(['name', 'kind']);
        });

        DB::statement("alter table financial_categories add constraint financial_categories_kind_check check (kind in ('payable','receivable'))");

        // Contas a pagar e a receber.
        Schema::create('financial_entries', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 10);
            $table->string('description', 160);
            $table->string('counterparty', 120)->nullable();
            $table->foreignId('financial_category_id')->nullable()->constrained()->nullOnDelete();
            $table->bigInteger('amount_cents');
            $table->date('due_date');
            $table->string('status', 10);
            $table->date('paid_on')->nullable();
            $table->foreignId('financial_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recurrence', 10)->default('none');
            // Lançamentos automáticos (fechamento de caixa, compra de insumo): evita duplicar.
            $table->string('source_ref', 80)->nullable()->unique();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'due_date']);
            $table->index(['kind', 'paid_on']);
        });

        DB::statement("alter table financial_entries add constraint financial_entries_kind_check check (kind in ('payable','receivable'))");
        DB::statement("alter table financial_entries add constraint financial_entries_status_check check (status in ('open','paid','cancelled'))");
        DB::statement("alter table financial_entries add constraint financial_entries_recurrence_check check (recurrence in ('none','monthly'))");
        DB::statement('alter table financial_entries add constraint financial_entries_amount_check check (amount_cents > 0)');
        DB::statement("alter table financial_entries add constraint financial_entries_paid_check check ((status = 'paid') = (paid_on is not null))");
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_entries');
        Schema::dropIfExists('financial_categories');
        Schema::table('payment_methods', fn (Blueprint $table) => $table->dropConstrainedForeignId('financial_account_id'));
        Schema::dropIfExists('financial_accounts');
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn('anonymized_at'));
    }
};
