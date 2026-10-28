<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class RegistrationSchemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_user_timestamps_are_the_last_columns_after_registration_fields(): void
    {
        $this->assertSame([
            'id',
            'name',
            'email',
            'email_verified_at',
            'password',
            'remember_token',
            'student_number',
            'role',
            'registration_status',
            'login_token_hash',
            'attendance_token',
            'donation_token',
            'is_present',
            'created_at',
            'updated_at',
        ], Schema::getColumnListing('users'));
    }

    public function test_email_only_requests_start_pending_without_qr_tokens(): void
    {
        $user = User::create(['email' => 'applicant@example.com']);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => null,
            'password' => null,
            'registration_status' => 'pending',
            'role' => 'gast',
            'student_number' => null,
            'login_token_hash' => null,
            'attendance_token' => null,
            'is_present' => false,
        ]);
    }

    public function test_students_can_have_a_separate_role_and_a_student_number(): void
    {
        $student = User::factory()->create([
            'email' => 'P551251@example.com',
            'role' => 'student',
            'student_number' => 'P551251',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'role' => 'student',
            'student_number' => 'P551251',
        ]);
    }

    /** @return array<string, array{string}> */
    public static function qrTokenColumns(): array
    {
        return [
            'login QR hash' => ['login_token_hash'],
            'attendance QR' => ['attendance_token'],
        ];
    }

    #[DataProvider('qrTokenColumns')]
    public function test_qr_tokens_cannot_be_shared_by_different_users(string $column): void
    {
        $token = str_repeat('a', 64);
        User::factory()->create([$column => $token]);

        $this->expectException(QueryException::class);

        User::factory()->create([$column => $token]);
    }

    public function test_scan_history_is_preserved_after_the_scanner_is_deleted(): void
    {
        $attendee = User::factory()->create();
        $scanner = User::factory()->create(['role' => 'vrijwilliger']);
        $logId = DB::table('attendance_logs')->insertGetId([
            'user_id' => $attendee->id,
            'scanned_by' => $scanner->id,
            'action' => 'check-in',
        ]);

        $scanner->delete();

        $this->assertDatabaseHas('attendance_logs', [
            'id' => $logId,
            'user_id' => $attendee->id,
            'scanned_by' => null,
            'action' => 'check-in',
        ]);
        $this->assertModelExists($attendee);
    }

    public function test_registration_migration_can_be_rolled_back_and_reapplied_without_losing_existing_accounts(): void
    {
        $user = User::factory()->create([
            'created_at' => '2026-10-01 08:00:00',
            'updated_at' => '2026-10-02 09:00:00',
        ]);
        $originalAttributes = $user->getAttributes();
        $migration = require database_path('migrations/2026_10_20_090740_update_users_table.php');

        $migration->down();

        try {
            $this->assertFalse(Schema::hasColumn('users', 'registration_status'));
            $this->assertDatabaseHas('users', $originalAttributes);
        } finally {
            $migration->up();
        }

        $this->assertDatabaseHas('users', [
            ...$originalAttributes,
            'registration_status' => 'pending',
            'login_token_hash' => null,
        ]);
    }

    /** @return array<string, array{string}> */
    public static function incompleteAccountColumns(): array
    {
        return [
            'missing name' => ['name'],
            'missing password' => ['password'],
        ];
    }

    #[DataProvider('incompleteAccountColumns')]
    public function test_rollback_refuses_to_change_incomplete_accounts(string $column): void
    {
        $user = User::factory()->create([$column => null]);
        $migration = require database_path('migrations/2026_10_20_090740_update_users_table.php');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Complete all user names and passwords before rolling back registration support.');

        try {
            $migration->down();
        } finally {
            $this->assertDatabaseHas('users', [
                'id' => $user->id,
                $column => null,
                'registration_status' => 'pending',
            ]);
        }
    }
}
