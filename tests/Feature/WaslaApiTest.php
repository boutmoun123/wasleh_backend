<?php

namespace Tests\Feature;

use App\Mail\VerifyAccountOtp;
use App\Models\Company;
use App\Models\JobPost;
use App\Models\JobSeekerProfile;
use App\Models\Quota;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class WaslaApiTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        $u = User::factory()->create();
        $u->forceFill(['role' => $role, 'is_verified' => true])->save();

        return $u;
    }

    public function test_health_endpoint(): void
    {
        $this->getJson('/api/health')->assertOk()->assertJsonPath('status', 'success');
    }

    public function test_registration_sends_hashed_otp_and_forbids_admin_role(): void
    {
        Mail::fake();
        $data = ['name' => 'Candidate', 'email' => 'candidate@example.com', 'password' => 'Password123!', 'role' => 'seeker'];
        $this->postJson('/api/auth/register', $data)->assertCreated()->assertJsonPath('status', 'success');
        $user = User::where('email', $data['email'])->firstOrFail();
        $this->assertFalse((bool) $user->is_verified);
        $this->assertNotNull($user->otp_code);
        $this->assertTrue(Hash::check('Password123!', $user->password));
        Mail::assertSent(VerifyAccountOtp::class, 1);
        $this->postJson('/api/auth/register', array_merge($data, ['email' => 'admin@example.com', 'role' => 'admin']))->assertUnprocessable();
    }

    public function test_verify_otp_returns_sanctum_token(): void
    {
        $user = $this->user('seeker');
        $user->forceFill(['is_verified' => false, 'otp_code' => Hash::make('123456'), 'otp_expires_at' => now()->addMinutes(15)])->save();
        $this->postJson('/api/auth/verify-otp', ['email' => $user->email, 'otp' => '999999'])->assertStatus(400);
        $this->postJson('/api/auth/verify-otp', ['email' => $user->email, 'otp' => '123456'])
            ->assertOk()->assertJsonStructure(['token', 'user' => ['id', 'role']]);
        $this->assertTrue((bool) $user->fresh()->is_verified);
    }

    public function test_employer_cannot_publish_without_verification_or_quota(): void
    {
        $employer = $this->user('employer');
        $company = Company::create(['user_id' => $employer->id, 'name' => 'My Company']);
        Quota::create(['company_id' => $company->id, 'available_posts' => 0]);
        $job = JobPost::create(['company_id' => $company->id, 'title' => 'Laravel Developer', 'description' => 'Backend role',
            'skills_required' => ['Laravel'], 'status' => 'draft']);
        $this->actingAs($employer, 'sanctum')->postJson('/api/employer/jobs/'.$job->id.'/publish')->assertForbidden();
        $company->update(['verification_status' => 'verified', 'verified_at' => now()]);
        $employer->unsetRelation('company');
        $this->actingAs($employer, 'sanctum')->postJson('/api/employer/jobs/'.$job->id.'/publish')->assertUnprocessable();
        $company->quota()->first()->update(['available_posts' => 1]);
        $employer->unsetRelation('company');
        $this->actingAs($employer, 'sanctum')->postJson('/api/employer/jobs/'.$job->id.'/publish')->assertOk();
        $this->assertEquals(0, $company->quota->fresh()->available_posts);
    }

    public function test_contact_details_blocked_in_job_description(): void
    {
        $employer = $this->user('employer');
        Company::create(['user_id' => $employer->id, 'name' => 'My Company']);
        $this->actingAs($employer, 'sanctum')->postJson('/api/employer/jobs', [
            'title' => 'Test', 'description' => 'Call 0933123456', 'skills_required' => ['Laravel'],
        ])->assertUnprocessable();
    }

    public function test_seeker_can_apply_once_and_match_is_explained(): void
    {
        $seeker = $this->user('seeker');
        $employer = $this->user('employer');
        $company = Company::create(['user_id' => $employer->id, 'name' => 'Company', 'verification_status' => 'verified', 'verified_at' => now()]);
        $job = JobPost::create(['company_id' => $company->id, 'title' => 'Backend', 'description' => 'Build services',
            'skills_required' => ['PHP', 'Laravel'], 'city' => 'Damascus', 'status' => 'active', 'published_at' => now()]);
        JobSeekerProfile::create(['user_id' => $seeker->id, 'basic_info' => ['job_title_ar' => 'مطور', 'city' => 'Damascus'],
            'skills' => ['PHP', 'Laravel'], 'experience' => [['title' => 'Dev', 'years' => 3]]]);
        $result = $this->actingAs($seeker, 'sanctum')->postJson('/api/seeker/jobs/'.$job->id.'/apply')
            ->assertCreated()->assertJsonPath('data.match_score', 100);
        $this->actingAs($seeker, 'sanctum')->postJson('/api/seeker/jobs/'.$job->id.'/apply')->assertStatus(409);
    }

    public function test_seeker_is_forbidden_from_employer_routes(): void
    {
        $this->actingAs($this->user('seeker'), 'sanctum')->getJson('/api/employer/jobs')->assertForbidden();
    }
}
