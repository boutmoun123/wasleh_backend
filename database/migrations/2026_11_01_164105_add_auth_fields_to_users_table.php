
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {

            // Allow registration by email or phone
            $table->string('email')->nullable()->change();

            // Phone number
            $table->string('phone', 20)
                ->nullable()
                ->unique();

            // User role
            $table->string('role', 20)
                ->default('seeker');

            // Account verification
            $table->boolean('is_verified')
                ->default(false);

            // Hashed OTP code
            $table->string('otp_code')
                ->nullable();

            // OTP expiration
            $table->timestamp('otp_expires_at')
                ->nullable();

            // OTP attempts
            $table->unsignedTinyInteger('otp_attempts')
                ->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {

            $table->dropUnique(['phone']);

            $table->dropColumn([
                'phone',
                'role',
                'is_verified',
                'otp_code',
                'otp_expires_at',
                'otp_attempts',
            ]);

            $table->string('email')
                ->nullable(false)
                ->change();
        });
    }
};
