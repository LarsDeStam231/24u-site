<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('discord_code', 16)->unique()->nullable();
            $table->string('discord_user_id', 32)->unique()->nullable();
            $table->timestamp('discord_verified_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['discord_code']);
            $table->dropUnique(['discord_user_id']);
            $table->dropColumn(['discord_code', 'discord_user_id', 'discord_verified_at']);
        });
    }
};
