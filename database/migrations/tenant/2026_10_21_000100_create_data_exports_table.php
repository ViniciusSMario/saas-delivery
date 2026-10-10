<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Exportações dos dados do estabelecimento (LGPD / portabilidade), pedidas pelo dono.
        Schema::create('data_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->string('path')->nullable();
            $table->bigInteger('size_bytes')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        DB::statement("alter table data_exports add constraint data_exports_status_check check (status in ('pending','done','failed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('data_exports');
    }
};
