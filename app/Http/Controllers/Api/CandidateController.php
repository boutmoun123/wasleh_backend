<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\JobSeekerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CandidateController extends Controller {
    public function index(Request $request): JsonResponse {
        $company=$request->user()->company;
        if (! $company || ! $company->isVerified()) {
            return response()->json(['message'=>'Verified company required.'],403);
        }
        $query=JobSeekerProfile::where('is_public',true);
        if ($request->filled('skill')) {
            $skill=(string) $request->input('skill');
            if (mb_strlen($skill)>100) { return response()->json(['message'=>'Skill filter too long.'],422); }
            $query->whereJsonContains('skills',$skill);
        }
        $paginator=$query->with('user:id,name')->latest()->paginate(15);
        $paginator->getCollection()->transform(function($p) {
            return ['id'=>$p->id,'user_id'=>$p->user_id, 'name'=>$p->user?->name,
                'basic_info'=>array_intersect_key($p->basic_info ?? [],array_flip(['job_title_ar','job_title_en','city','country'])),
                'skills'=>$p->skills ?? [], 'experience_count'=>count($p->experience ?? [])];
            // Email, phone, private CV path, and full profile summary are intentionally omitted.
        });
        return response()->json(['status'=>'success','data'=>$paginator]);
    }
}
