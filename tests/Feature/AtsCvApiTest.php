<?php

namespace Tests\Feature;

use App\Models\JobSeekerProfile;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AtsCvApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_seeker_can_analyze_saved_profile_for_ats(): void
    {
        $user = User::factory()->create(['phone' => '+966500000000']);
        $user->forceFill(['role' => 'seeker', 'is_verified' => true])->save();
        JobSeekerProfile::create([
            'user_id' => $user->id,
            'basic_info' => [
                'job_title_en' => 'Backend Developer',
                'summary_en' => 'Developed secure services and improved response time by 30 percent.',
            ],
            'skills' => ['PHP', 'Laravel', 'MySQL'],
            'experience' => [['title' => 'Developer', 'company' => 'WASLA', 'years' => 3]],
            'education' => [['degree' => 'Computer Science', 'institution' => 'University']],
            'languages' => ['Arabic', 'English'],
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/seeker/profile/ats-analysis')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.method', 'rule_based_ats_v1')
            ->assertJsonStructure(['data' => ['score', 'grade', 'breakdown', 'detected_sections', 'recommendations']]);
    }

    public function test_ats_analysis_requires_seeker_role(): void
    {
        $employer = User::factory()->create();
        $employer->forceFill(['role' => 'employer', 'is_verified' => true])->save();

        $this->actingAs($employer, 'sanctum')
            ->getJson('/api/seeker/profile/ats-analysis')
            ->assertForbidden();
    }

    public function test_pdf_upload_is_parsed_and_returns_ats_analysis(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'seeker', 'is_verified' => true])->save();
        $pdf = Pdf::loadHTML(implode('<br>', [
            'candidate@example.com +966500000000',
            'Professional Summary',
            'Skills PHP Laravel MySQL',
            'Work Experience Developed 20 projects',
            'Education Computer Science',
            'Languages Arabic English',
        ]))->output();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/seeker/profile/parse-cv', [
            'cv_file' => UploadedFile::fake()->createWithContent('resume.pdf', $pdf),
        ]);

        $response->assertOk()
            ->assertJsonPath('parsed_data.ats.method', 'rule_based_ats_v1')
            ->assertJsonPath('parsed_data.email', 'candidate@example.com')
            ->assertJsonStructure(['parsed_data' => ['skills', 'ats' => ['score', 'grade', 'recommendations']]]);
    }

    public function test_saved_profile_generates_ats_friendly_pdf(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'seeker', 'is_verified' => true])->save();
        JobSeekerProfile::create([
            'user_id' => $user->id,
            'basic_info' => ['job_title_en' => 'Backend Developer', 'summary_en' => 'Professional summary.'],
            'skills' => ['PHP', 'Laravel'],
        ]);

        $this->actingAs($user, 'sanctum')
            ->get('/api/seeker/profile/generate-cv')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_ats_resume_template_escapes_user_content(): void
    {
        $user = User::factory()->make(['name' => '<script>alert(1)</script>']);
        $profile = new JobSeekerProfile([
            'basic_info' => ['job_title_en' => 'Developer'],
            'skills' => ['PHP'],
        ]);

        $html = view('cv.profile', compact('user', 'profile'))->render();

        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }
}
