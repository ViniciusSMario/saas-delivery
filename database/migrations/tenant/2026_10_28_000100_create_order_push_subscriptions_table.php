<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Celular do cliente que pediu para ser avisado do andamento de um pedido (sem conta: vale só para aquele pedido).
        Schema::create('order_push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->text('endpoint');
            $table->string('public_key');
            $table->string('auth_token');
            $table->string('content_encoding', 20)->default('aes128gcm');
            $table->string('page_url', 500);
            $table->string('icon_url', 500)->nullable();
            $table->timestamps();
            $table->unique(['order_id', 'endpoint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_push_subscriptions');
    }
};
