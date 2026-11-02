<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobSeekerProfile;
use App\Services\AtsCvAnalyzer;
use App\Services\CvParser;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

class ProfileController extends Controller
{
    private function profile(Request $request): JobSeekerProfile
    {
        return JobSeekerProfile::firstOrCreate(['user_id' => $request->user()->id]);
    }

    public function show(Request $request): JsonResponse
    {
        $p = $this->profile($request);

        return response()->json(['status' => 'success', 'data' => [
            'basic_info' => $p->basic_info ?? [], 'skills' => $p->skills ?? [],
            'experience' => $p->experience ?? [], 'education' => $p->education ?? [],
            'languages' => $p->languages ?? [], 'preferences' => $p->preferences ?? [],
            'is_public' => $p->is_public, 'show_contact' => $p->show_contact,
            'cv_uploaded' => (bool) $p->cv_path,
        ]]);
    }

    public function updateSection(Request $request, string $section): JsonResponse
    {
        if (! in_array($section, ['basic-info', 'skills', 'experience', 'education', 'languages', 'preferences', 'privacy'], true)) {
            return response()->json(['status' => 'error', 'message' => 'Unknown profile section.'], 404);
        }
        $rules = match ($section) {
            'basic-info' => [
                'first_name' => ['required', 'string', 'max:100'], 'last_name' => ['nullable', 'string', 'max:100'],
                'country' => ['nullable', 'string', 'max:100'], 'city' => ['nullable', 'string', 'max:100'],
                'gender' => ['nullable', Rule::in(['male', 'female', 'other', 'prefer_not_to_say'])],
                'job_title_ar' => ['required', 'string', 'max:255'], 'job_title_en' => ['nullable', 'string', 'max:255'],
                'summary_ar' => ['nullable', 'string', 'max:3000'], 'summary_en' => ['nullable', 'string', 'max:3000'],
            ],
            'skills' => ['skills' => ['required', 'array', 'max:100'], 'skills.*' => ['required', 'string', 'max:100']],
            'experience' => ['experience' => ['required', 'array', 'max:40'], 'experience.*.title' => ['required', 'string', 'max:255'],
                'experience.*.company' => ['nullable', 'string', 'max:255'], 'experience.*.years' => ['required', 'numeric', 'min:0', 'max:70'],
                'experience.*.start_date' => ['nullable', 'date_format:Y-m'], 'experience.*.end_date' => ['nullable', 'date_format:Y-m'],
                'experience.*.description' => ['nullable', 'string', 'max:2000'],
                'experience.*.achievements' => ['nullable', 'array', 'max:10'],
                'experience.*.achievements.*' => ['string', 'max:300']],
            'education' => ['education' => ['required', 'array', 'max:30'], 'education.*.degree' => ['required', 'string', 'max:255'],
                'education.*.institution' => ['nullable', 'string', 'max:255'], 'education.*.field' => ['nullable', 'string', 'max:255'],
                'education.*.year' => ['nullable', 'integer', 'between:1950,2100']],
            'languages' => ['languages' => ['required', 'array', 'max:30'], 'languages.*' => ['required', 'string', 'max:100']],
            'preferences' => ['preferences' => ['required', 'array'], 'preferences.city' => ['nullable', 'string', 'max:100'],
                'preferences.job_title' => ['nullable', 'string', 'max:255'], 'preferences.min_salary' => ['nullable', 'integer', 'min:0'],
                'preferences.alerts' => ['nullable', 'boolean'], 'preferences.job_type' => ['nullable', 'string', 'max:100']],
            'privacy' => ['is_public' => ['required', 'boolean'], 'show_contact' => ['required', 'boolean']],
        };
        $data = $request->validate($rules);
        $p = $this->profile($request);
        if ($section === 'privacy') {
            $p->fill($data);
        } else {
            $p->{str_replace('-', '_', $section)} = $section === 'basic-info' ? $data : $data[$section];
        }
        $p->save();

        return response()->json(['status' => 'success', 'message' => 'تم حفظ القسم بنجاح.']);
    }

    public function parseCv(Request $request, CvParser $parser): JsonResponse
    {
        $request->validate(['cv_file' => ['required', 'file', 'mimes:pdf,docx', 'max:5120']]);
        try {
            $data = $parser->parse($request->file('cv_file'));
        } catch (Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        // Parsing returns suggestions only. Do not auto-approve extracted personal data.
        return response()->json(['status' => 'success', 'message' => 'راجع البيانات وصححها قبل الحفظ.', 'parsed_data' => $data]);
    }

    public function atsAnalysis(Request $request, AtsCvAnalyzer $analyzer): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $analyzer->analyzeProfile($request->user(), $this->profile($request)),
        ]);
    }

    public function uploadCv(Request $request): JsonResponse
    {
        $request->validate(['cv_file' => ['required', 'file', 'mimes:pdf,docx', 'max:5120']]);
        $profile = $this->profile($request);
        $disk = config('wasla.private_disk', 'local');
        $path = $request->file('cv_file')->store('wasla/cv/'.$request->user()->id, $disk);
        if ($profile->cv_path) {
            Storage::disk($profile->cv_disk ?? $disk)->delete($profile->cv_path);
        }
        $profile->update(['cv_disk' => $disk, 'cv_path' => $path]);

        return response()->json(['status' => 'success', 'message' => 'CV saved privately.'], 201);
    }

    public function downloadCv(Request $request)
    {
        $p = $this->profile($request);
        abort_unless($p->cv_path && Storage::disk($p->cv_disk)->exists($p->cv_path), 404);

        return Storage::disk($p->cv_disk)->download($p->cv_path, 'wasla-cv.'.pathinfo($p->cv_path, PATHINFO_EXTENSION));
    }

    public function generatePdf(Request $request)
    {
        if (! class_exists(Pdf::class)) {
            return response()->json(['message' => 'Install barryvdh/laravel-dompdf.'], 503);
        }
        $p = $this->profile($request);
        $pdf = Pdf::loadView('cv.profile', ['user' => $request->user(), 'profile' => $p]);

        return $pdf->download('wasla-resume.pdf');
    }
}
