<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wp_packages', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('project_id')->nullable()->index();
            $t->string('slug')->unique();
            $t->string('type', 10); // plugin|theme
            $t->string('name');
            $t->string('repo');
            $t->string('git_provider', 20)->default('github');
            $t->boolean('is_active')->default(true);
            $t->boolean('is_paid')->default(false);
            $t->timestamps();
        });

        Schema::create('wp_package_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('wp_package_id')->constrained('wp_packages')->cascadeOnDelete();
            $t->string('version', 64);
            $t->string('file_path');
            $t->string('checksum', 64);
            $t->longText('changelog')->nullable();
            $t->string('requires_wp', 20)->nullable();
            $t->string('tested_wp', 20)->nullable();
            $t->string('requires_php', 20)->nullable();
            $t->timestamp('released_at')->nullable();
            $t->boolean('is_stable')->default(true);
            $t->timestamps();
            $t->unique(['wp_package_id', 'version']);
        });

        Schema::create('wp_sites', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('project_id')->nullable()->index();
            $t->string('domain')->unique();
            $t->string('wp_path')->nullable();
            $t->string('api_key_hash', 64);
            $t->string('wp_version', 20)->nullable();
            $t->string('php_version', 20)->nullable();
            $t->timestamp('last_check_at')->nullable();
            $t->timestamps();
        });

        Schema::create('wp_site_packages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('wp_site_id')->constrained('wp_sites')->cascadeOnDelete();
            $t->foreignId('wp_package_id')->constrained('wp_packages')->cascadeOnDelete();
            $t->string('installed_version', 64)->nullable();
            $t->string('target_version', 64)->nullable();
            $t->boolean('auto_update')->default(true);
            $t->timestamp('last_updated_at')->nullable();
            $t->string('status', 10)->default('pending'); // ok|failed|pending
            $t->text('last_error')->nullable();
            $t->timestamps();
            $t->unique(['wp_site_id', 'wp_package_id']);
        });

        Schema::create('wp_update_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('wp_site_id')->nullable()->constrained('wp_sites')->nullOnDelete();
            $t->foreignId('wp_package_id')->nullable()->constrained('wp_packages')->nullOnDelete();
            $t->string('from_version', 64)->nullable();
            $t->string('to_version', 64)->nullable();
            $t->string('method', 10); // pull|push|sync
            $t->string('status', 12); // ok|failed|downloaded
            $t->text('message')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wp_update_logs');
        Schema::dropIfExists('wp_site_packages');
        Schema::dropIfExists('wp_sites');
        Schema::dropIfExists('wp_package_versions');
        Schema::dropIfExists('wp_packages');
    }
};
