<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** `passport:purge` filters on revoked / expires_at; without these it scans the whole token tables. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oauth_access_tokens', function (Blueprint $table) {
            $table->index('expires_at');
            $table->index('revoked');
        });
        Schema::table('oauth_refresh_tokens', function (Blueprint $table) {
            $table->index('expires_at');
            $table->index('revoked');
        });
    }

    public function down(): void
    {
        Schema::table('oauth_access_tokens', function (Blueprint $table) {
            $table->dropIndex(['expires_at']);
            $table->dropIndex(['revoked']);
        });
        Schema::table('oauth_refresh_tokens', function (Blueprint $table) {
            $table->dropIndex(['expires_at']);
            $table->dropIndex(['revoked']);
        });
    }
};
