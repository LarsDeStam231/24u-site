<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('created_at', 'previous_created_at');
            $table->renameColumn('updated_at', 'previous_updated_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->nullable()->change();
            $table->string('password')->nullable()->change();
            $table->string('student_number')->unique()->nullable();
            $table->enum('role', ['admin', 'student', 'organisatie', 'vrijwilliger', 'donateur', 'gast'])->default('gast');
            $table->enum('registration_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->string('login_token_hash', 64)->unique()->nullable()->comment('SHA-256 hash of the login QR token');
            $table->string('attendance_token')->unique()->nullable();
            $table->string('donation_token')->unique()->nullable();
            $table->boolean('is_present')->default(false);
            $table->timestamps();
        });

        DB::table('users')->update([
            'created_at' => DB::raw('previous_created_at'),
            'updated_at' => DB::raw('previous_updated_at'),
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['previous_created_at', 'previous_updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('users')->whereNull('name')->orWhereNull('password')->exists()) {
            throw new RuntimeException('Complete all user names and passwords before rolling back registration support.');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['student_number']);
            $table->dropUnique(['login_token_hash']);
            $table->dropUnique(['attendance_token']);
            $table->dropUnique(['donation_token']);
            $table->dropColumn([
                'student_number',
                'role',
                'registration_status',
                'login_token_hash',
                'attendance_token',
                'donation_token',
                'is_present',
            ]);
            $table->string('name')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
        });
    }
};
