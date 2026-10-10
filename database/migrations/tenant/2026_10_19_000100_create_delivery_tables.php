<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Taxa de entrega por bairro (quando a configuração "taxa por bairro" está ligada).
        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->bigInteger('fee_cents')->default(0);
            $table->bigInteger('min_order_cents')->nullable();
            $table->unsignedSmallInteger('eta_minutes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::statement('create unique index delivery_zones_name_unique on delivery_zones (lower(name))');

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('delivery_zone_id')->nullable()->constrained('delivery_zones')->nullOnDelete();
            $table->foreignId('courier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dispatched_at')->nullable();
            $table->index(['courier_id', 'status']);
        });

        // Nova permissão: entregador e gerente já existentes passam a fazer entregas.
        $roles = DB::table('roles')->whereIn('slug', ['entregador', 'gerente'])->pluck('id');
        DB::table('role_permissions')->insertOrIgnore($roles->map(fn ($id) => ['role_id' => $id, 'permission' => 'deliveries.operate'])->all());
    }

    public function down(): void
    {
        DB::table('role_permissions')->where('permission', 'deliveries.operate')->delete();

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['courier_id', 'status']);
            $table->dropConstrainedForeignId('courier_id');
            $table->dropConstrainedForeignId('delivery_zone_id');
            $table->dropColumn('dispatched_at');
        });

        Schema::dropIfExists('delivery_zones');
    }
};
