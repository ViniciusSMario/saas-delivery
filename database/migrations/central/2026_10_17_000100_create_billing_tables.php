<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->string('slug', 40)->nullable()->unique();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('trial_days')->default(14);
            $table->boolean('is_public')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
        });

        // Plano antigo de desenvolvimento: continua existindo, fora da vitrine.
        DB::table('plans')->whereNull('slug')->update(['slug' => DB::raw("'plano-'||id"), 'is_public' => false]);

        Schema::table('tenants', function (Blueprint $table) {
            // Sem assinatura (null) = cortesia/legado: sem cobrança nem bloqueio.
            $table->string('subscription_status', 20)->nullable()->index();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_ends_at')->nullable();
            $table->timestamp('past_due_since')->nullable();
            $table->string('billing_email')->nullable();
            $table->string('billing_customer_id')->nullable();
            $table->string('billing_subscription_id')->nullable()->index();
        });

        DB::statement("alter table tenants add constraint tenants_subscription_status_check check (subscription_status in ('trialing','active','past_due','canceled'))");

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('plans');
            $table->string('gateway', 20);
            $table->string('gateway_id')->nullable()->unique();
            $table->string('description')->nullable();
            $table->bigInteger('amount_cents');
            $table->date('due_date');
            $table->string('status', 20)->default('pending');
            $table->text('invoice_url')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });

        DB::statement("alter table invoices add constraint invoices_status_check check (status in ('pending','paid','overdue','canceled'))");

        // Webhooks recebidos: o id do evento garante que nada é processado duas vezes.
        Schema::create('billing_events', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 20);
            $table->string('event_id');
            $table->string('type', 50);
            $table->jsonb('payload')->default('{}');
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['gateway', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_events');
        Schema::dropIfExists('invoices');

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['subscription_status', 'trial_ends_at', 'current_period_ends_at', 'past_due_since', 'billing_email', 'billing_customer_id', 'billing_subscription_id']);
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['slug', 'description', 'trial_days', 'is_public', 'is_active', 'sort']);
        });
    }
};
