<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('job_posts', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->string('title'); $t->text('description'); $t->string('city')->nullable();
            $t->string('job_type')->nullable(); $t->string('employment_type')->nullable();
            $t->unsignedInteger('salary_min')->nullable(); $t->unsignedInteger('salary_max')->nullable();
            $t->json('skills_required')->nullable(); $t->json('requirements')->nullable();
            $t->unsignedSmallInteger('min_experience_years')->default(0);
            $t->string('status')->default('draft')->index(); $t->date('expires_at')->nullable();
            $t->timestamp('published_at')->nullable(); $t->timestamp('closed_at')->nullable();
            $t->timestamps(); $t->index(['city', 'status']);
        });
        Schema::create('applications', function (Blueprint $t) {
            $t->id(); $t->foreignId('job_post_id')->constrained()->cascadeOnDelete();
            $t->foreignId('seeker_user_id')->constrained('users')->cascadeOnDelete();
            $t->decimal('match_score', 5, 2)->default(0); $t->json('match_details')->nullable();
            $t->string('status')->default('new')->index(); $t->string('final_outcome')->nullable();
            $t->timestamp('final_at')->nullable(); $t->timestamp('applied_at')->useCurrent();
            $t->timestamps(); $t->unique(['job_post_id', 'seeker_user_id']);
        });
        Schema::create('application_status_histories', function (Blueprint $t) {
            $t->id(); $t->foreignId('application_id')->constrained()->cascadeOnDelete();
            $t->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            $t->string('from_status'); $t->string('to_status'); $t->text('note')->nullable();
            $t->timestamps();
        });
        Schema::create('abuse_reports', function (Blueprint $t) {
            $t->id(); $t->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('job_post_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('reported_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('reason'); $t->text('details')->nullable();
            $t->string('status')->default('new')->index(); $t->text('admin_note')->nullable();
            $t->timestamps();
        });
        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $t) {
                $t->uuid('id')->primary(); $t->string('type'); $t->morphs('notifiable');
                $t->text('data'); $t->timestamp('read_at')->nullable(); $t->timestamps();
            });
        }
    }
    public function down(): void
    {
        Schema::dropIfExists('abuse_reports'); Schema::dropIfExists('application_status_histories');
        Schema::dropIfExists('applications'); Schema::dropIfExists('job_posts');
        // notifications may have existed independently; preserve it.
    }
};
