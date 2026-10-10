<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pedido de mesa pronto: o garçom que tocou em "Vou levar" (só ele marca como servido).
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('runner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('runner_claimed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('runner_id');
            $table->dropColumn('runner_claimed_at');
        });
    }
};
