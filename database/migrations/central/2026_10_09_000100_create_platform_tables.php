<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->bigInteger('price_cents')->default(0);
            $table->jsonb('limits')->default('{}');
            $table->jsonb('features')->default('{}');
            $table->timestamps();
        });

        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug', 40)->unique();
            $table->string('name');
            $table->string('schema_name', 47)->unique();
            $table->string('status', 20)->default('provisioning')->index();
            $table->foreignId('plan_id')->nullable()->constrained('plans');
            $table->string('document', 20)->nullable();
            $table->string('schema_version')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement("alter table tenants add constraint tenants_status_check check (status in ('provisioning','active','suspended','pending_deletion','deleted','failed','restoring'))");
        DB::statement("alter table tenants add constraint tenants_schema_name_check check (schema_name ~ '^tenant_[a-z0-9]{6,40}$')");

        Schema::create('tenant_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('domain')->unique();
            $table->string('type', 20)->default('subdomain');
            $table->boolean('is_primary')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        DB::statement("alter table tenant_domains add constraint tenant_domains_type_check check (type in ('subdomain','custom'))");
        DB::statement('alter table tenant_domains add constraint tenant_domains_lower_check check (domain = lower(domain))');

        Schema::create('tenant_events', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('type', 50);
            $table->jsonb('payload')->default('{}');
            $table->string('actor')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('platform_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role', 30)->default('support');
            $table->text('two_factor_secret')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('reserved_slugs', function (Blueprint $table) {
            $table->string('slug', 40)->primary();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reserved_slugs');
        Schema::dropIfExists('platform_users');
        Schema::dropIfExists('tenant_events');
        Schema::dropIfExists('tenant_domains');
        Schema::dropIfExists('tenants');
        Schema::dropIfExists('plans');
    }
};
