<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Avaliação do cliente depois do pedido concluído (uma por pedido).
        Schema::create('order_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('rating');
            $table->jsonb('tags')->nullable();
            $table->text('comment')->nullable();
            // Retrato de quem entregou (para a média por entregador).
            $table->foreignId('courier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        DB::statement('alter table order_reviews add constraint order_reviews_rating_check check (rating between 1 and 5)');
    }

    public function down(): void
    {
        Schema::dropIfExists('order_reviews');
    }
};
