<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Backups de cada estabelecimento: dump do schema + arquivos, numa pasta própria.
        Schema::create('tenant_backups', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('status', 20)->default('running');
            $table->string('path')->nullable();
            $table->bigInteger('size_bytes')->default(0);
            $table->string('schema_version')->nullable();
            $table->text('error')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->index(['tenant_id', 'created_at']);
        });

        DB::statement("alter table tenant_backups add constraint tenant_backups_kind_check check (kind in ('daily','manual','pre_restore','final'))");
        DB::statement("alter table tenant_backups add constraint tenant_backups_status_check check (status in ('running','done','failed'))");

        Schema::table('tenants', function (Blueprint $table) {
            // Exclusão com carência: até essa data dá para cancelar.
            $table->timestamp('deletion_scheduled_at')->nullable()->index();
            $table->string('deletion_previous_status', 20)->nullable();
            // Por que está suspenso ("billing" = falta de pagamento, reativa sozinho ao pagar).
            $table->string('suspension_reason', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['deletion_scheduled_at', 'deletion_previous_status', 'suspension_reason']);
        });

        Schema::dropIfExists('tenant_backups');
    }
};
