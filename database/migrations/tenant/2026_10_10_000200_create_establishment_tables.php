<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Linha única com os dados cadastrais do estabelecimento.
        Schema::create('establishment', function (Blueprint $table) {
            $table->id();
            $table->string('trade_name');
            $table->string('legal_name')->nullable();
            $table->string('document', 18)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('zip_code', 9)->nullable();
            $table->string('street')->nullable();
            $table->string('number', 20)->nullable();
            $table->string('complement')->nullable();
            $table->string('district')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();
            $table->string('logo_path')->nullable();
            $table->timestamps();
        });

        DB::statement('create unique index establishment_single_row on establishment ((true))');

        Schema::create('opening_hours', function (Blueprint $table) {
            $table->id();
            // 0 = domingo ... 6 = sábado
            $table->unsignedSmallInteger('weekday')->unique();
            $table->boolean('is_closed')->default(false);
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->timestamps();
        });

        DB::statement('alter table opening_hours add constraint opening_hours_weekday_check check (weekday between 0 and 6)');
    }

    public function down(): void
    {
        Schema::dropIfExists('opening_hours');
        Schema::dropIfExists('establishment');
    }
};
