<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Only the security-relevant event (auth.failed) needs request context,
     * so these columns stay nullable: every other action keeps writing rows
     * exactly as it does today.
     *
     * The IP is stored as a salted hash rather than in the clear. That keeps
     * the useful property — grouping repeated attempts from one source — while
     * the stored value is no longer personal data by itself. The salt lives in
     * APP_KEY, so the hashes cannot be reversed by comparing them against a
     * list of candidate addresses.
     */
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->string('ip_hash', 64)->nullable()->after('subject_name');
            $table->string('user_agent', 255)->nullable()->after('ip_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropColumn(['ip_hash', 'user_agent']);
        });
    }
};
