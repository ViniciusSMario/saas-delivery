<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_users', function (Blueprint $table) {
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_secret');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('platform_users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_confirmed_at', 'is_active', 'last_login_at']);
        });
    }
};
