<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Existing WASLA installation already has these fields. Only add missing columns.
        $columns = [
            'phone' => fn (Blueprint $t) => $t->string('phone', 20)->nullable()->unique(),
            'role' => fn (Blueprint $t) => $t->string('role', 20)->default('seeker'),
            'is_verified' => fn (Blueprint $t) => $t->boolean('is_verified')->default(false),
            'otp_code' => fn (Blueprint $t) => $t->string('otp_code')->nullable(),
            'otp_expires_at' => fn (Blueprint $t) => $t->timestamp('otp_expires_at')->nullable(),
            'otp_attempts' => fn (Blueprint $t) => $t->unsignedTinyInteger('otp_attempts')->default(0),
        ];
        foreach ($columns as $name => $create) {
            if (! Schema::hasColumn('users', $name)) {
                Schema::table('users', $create);
            }
        }
        if (! Schema::hasTable('companies')) {
            Schema::create('companies', function (Blueprint $t) {
                $t->id(); $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
                $t->string('name'); $t->string('industry')->nullable(); $t->string('city')->nullable();
                $t->text('description')->nullable(); $t->string('website')->nullable();
                $t->string('verification_status')->default('pending'); $t->timestamp('verified_at')->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('job_seeker_profiles')) {
            Schema::create('job_seeker_profiles', function (Blueprint $t) {
                $t->id(); $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
                $t->json('basic_info')->nullable(); $t->json('skills')->nullable();
                $t->json('experience')->nullable(); $t->json('education')->nullable();
                $t->json('languages')->nullable(); $t->json('preferences')->nullable();
                $t->boolean('is_public')->default(false); $t->boolean('show_contact')->default(false);
                $t->string('cv_disk')->nullable(); $t->string('cv_path')->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('quotas')) {
            Schema::create('quotas', function (Blueprint $t) {
                $t->id(); $t->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
                $t->unsignedInteger('available_posts')->default(0); $t->timestamp('last_renewed')->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('company_documents')) {
            Schema::create('company_documents', function (Blueprint $t) {
                $t->id(); $t->foreignId('company_id')->constrained()->cascadeOnDelete();
                $t->string('original_name'); $t->string('disk'); $t->string('path');
                $t->string('mime', 128); $t->unsignedBigInteger('size');
                $t->string('review_status')->default('pending');
                $t->text('review_note')->nullable(); $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('company_documents'); Schema::dropIfExists('quotas');
        Schema::dropIfExists('job_seeker_profiles'); Schema::dropIfExists('companies');
        // Never drop pre-existing auth fields on rollback.
    }
};
