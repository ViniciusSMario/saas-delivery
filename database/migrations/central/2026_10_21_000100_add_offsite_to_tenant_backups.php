<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Quando a cópia fora do servidor (bucket) foi concluída.
        Schema::table('tenant_backups', function (Blueprint $table) {
            $table->timestamp('offsite_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenant_backups', function (Blueprint $table) {
            $table->dropColumn('offsite_at');
        });
    }
};
