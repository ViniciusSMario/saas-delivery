<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Avisos de WhatsApp enviados (ou tentados) ao cliente.
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained('orders')->cascadeOnDelete();
            $table->string('event', 30);
            $table->string('phone', 15);
            $table->text('body');
            $table->string('provider', 20);
            $table->string('status', 20)->default('queued');
            $table->string('provider_message_id')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'event']);
        });

        DB::statement("alter table whatsapp_messages add constraint whatsapp_messages_status_check check (status in ('queued','sent','failed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
