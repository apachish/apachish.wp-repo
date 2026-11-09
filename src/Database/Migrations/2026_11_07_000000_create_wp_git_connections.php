<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wp_git_connections', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();                 // used in the webhook URL
            $t->unsignedBigInteger('project_id')->nullable()->index();
            $t->string('name');
            $t->string('provider', 20);                 // github|gitlab|gitea
            $t->string('api_url')->nullable();          // self-hosted GitLab/Gitea (https only)
            $t->text('token');                          // encrypted cast
            $t->text('webhook_secret');                 // encrypted cast
            $t->timestamps();
        });

        Schema::table('wp_packages', function (Blueprint $t) {
            $t->foreignId('wp_git_connection_id')->nullable()->after('git_provider')
                ->constrained('wp_git_connections')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('wp_packages', function (Blueprint $t) {
            $t->dropConstrainedForeignId('wp_git_connection_id');
        });

        Schema::dropIfExists('wp_git_connections');
    }
};
