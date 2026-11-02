<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\JobPost;
use App\Models\JobSeekerProfile;
use App\Services\MatchEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class RecommendationController extends Controller {
    public function index(Request $request, MatchEngine $engine): JsonResponse {
        $profile=JobSeekerProfile::where('user_id',$request->user()->id)->first();
        if (! $profile) { return response()->json(['message'=>'Complete profile first.'],422); }
        $prefs=$profile->preferences ?? [];
        $query=JobPost::with('company:id,name,verification_status')->where('status','active')
            ->where(function($q){$q->whereNull('expires_at')->orWhereDate('expires_at','>=',today());});
        if (! empty($prefs['city'])) { $query->where('city',$prefs['city']); }
        if (! empty($prefs['job_type'])) { $query->where('job_type',$prefs['job_type']); }
        if (! empty($prefs['min_salary'])) { $query->where(function($q)use($prefs){$q->whereNull('salary_max')->orWhere('salary_max','>=',$prefs['min_salary']);}); }
        $list=$query->latest()->limit(100)->get()->map(function($job) use($profile,$engine) {
            return ['job'=>$job,'match'=>$engine->evaluate($profile,$job)];
        })->sortByDesc(fn($v)=>$v['match']['score'])->take(20)->values();
        return response()->json(['status'=>'success','data'=>$list,'note'=>'Top 20 from most recent 100 matching preference filters.']);
    }
    public function evaluate(Request $request, JobPost $job, MatchEngine $engine): JsonResponse {
        abort_unless($job->status==='active',404);
        $profile=JobSeekerProfile::where('user_id',$request->user()->id)->first();
        if (! $profile) { return response()->json(['message'=>'Complete profile first.'],422); }
        return response()->json(['status'=>'success','data'=>$engine->evaluate($profile,$job)]);
    }
}
