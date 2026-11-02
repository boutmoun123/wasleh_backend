<?php
namespace App\Http\Controllers\Api;
use App\Events\ApplicationStatusChanged;
use App\Events\ApplicationSubmitted;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationStatusHistory;
use App\Models\JobPost;
use App\Models\JobSeekerProfile;
use App\Services\MatchEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ApplicationController extends Controller
{
    public function apply(Request $request, JobPost $job, MatchEngine $engine): JsonResponse
    {
        if ($job->status!=='active' || ($job->expires_at && $job->expires_at->startOfDay()->lt(today()))) {
            return response()->json(['message'=>'Job is not accepting applications.'],409);
        }
        $profile=JobSeekerProfile::where('user_id',$request->user()->id)->first();
        if (! $profile || empty($profile->basic_info['job_title_ar'] ?? $profile->basic_info['job_title_en'] ?? null)) {
            return response()->json(['message'=>'Complete your professional profile first.'],422);
        }
        if (Application::where('job_post_id',$job->id)->where('seeker_user_id',$request->user()->id)->exists()) {
            return response()->json(['message'=>'Already applied to this job.'],409);
        }
        $match=$engine->evaluate($profile,$job);
        $app=Application::create(['job_post_id'=>$job->id,'seeker_user_id'=>$request->user()->id,
            'match_score'=>$match['score'],'match_details'=>$match,'status'=>'new']);
        ApplicationSubmitted::dispatch($app->id);
        return response()->json(['status'=>'success','data'=>$app],201);
    }
    public function myApplications(Request $request): JsonResponse
    {
        $list=Application::with('jobPost:id,title,company_id,status,city')->where('seeker_user_id',$request->user()->id)->latest()->paginate(20);
        return response()->json(['status'=>'success','data'=>$list]);
    }
    public function applicants(Request $request, JobPost $job): JsonResponse
    {
        abort_unless($request->user()->company && $job->company_id===$request->user()->company->id,403);
        $apps=$job->applications()->with(['seeker:id,name','seeker.seekerProfile'])->orderByDesc('match_score')->paginate(25);
        // Never accidentally serialize email/phone: load seeker name only, then explicitly select public data.
        $apps->getCollection()->transform(function(Application $app) {
            $p=$app->seeker?->seekerProfile;
            $data=[ 'id'=>$app->id,'status'=>$app->status, 'match_score'=>$app->match_score,
                'match_details'=>$app->match_details,'applied_at'=>$app->applied_at,
                'seeker'=>['id'=>$app->seeker_user_id,'name'=>$app->seeker?->name,
                    'basic_info'=>array_intersect_key($p?->basic_info ?? [],array_flip(['job_title_ar','job_title_en','city','country'])), 'skills'=>$p?->skills ?? []]];
            // Contact details are intentionally not returned by this endpoint.
            return $data;
        });
        return response()->json(['status'=>'success','data'=>$apps]);
    }
    public function status(Request $request, Application $application): JsonResponse
    {
        $job=$application->jobPost;
        abort_unless($request->user()->company && $job->company_id===$request->user()->company->id,403);
        $data=$request->validate(['status'=>['required',Rule::in(['reviewing','interview','accepted','rejected'])], 'note'=>['nullable','string','max:500']]);
        $current=$application->status; $next=$data['status'];
        $allowed=[
            'new'=>['reviewing','rejected'], 'reviewing'=>['interview','accepted','rejected'],
            'interview'=>['accepted','rejected'], 'accepted'=>[], 'rejected'=>[], 'withdrawn'=>[],
        ];
        if (! in_array($next,$allowed[$current]??[],true)) {
            return response()->json(['message'=>'Invalid application status transition.'],409);
        }
        DB::transaction(function () use($application,$current,$next,$data,$request) {
            $application->update(['status'=>$next,'final_outcome'=>in_array($next,['accepted','rejected'])?$next:null,
                'final_at'=>in_array($next,['accepted','rejected'])?now():null]);
            ApplicationStatusHistory::create(['application_id'=>$application->id,'actor_id'=>$request->user()->id,
                'from_status'=>$current,'to_status'=>$next,'note'=>$data['note']??null]);
        });
        ApplicationStatusChanged::dispatch($application->id,$current,$next);
        return response()->json(['status'=>'success','data'=>$application->fresh()]);
    }
    public function withdraw(Request $request, Application $application): JsonResponse
    {
        abort_unless($application->seeker_user_id===$request->user()->id,403);
        if (in_array($application->status,['accepted','rejected','withdrawn'],true)) {
            return response()->json(['message'=>'Cannot withdraw a finalized application.'],409);
        }
        $old=$application->status;
        DB::transaction(function () use($application,$request,$old) {
            $application->update(['status'=>'withdrawn','final_outcome'=>'withdrawn','final_at'=>now()]);
            ApplicationStatusHistory::create(['application_id'=>$application->id,'actor_id'=>$request->user()->id,
                'from_status'=>$old,'to_status'=>'withdrawn']);
        });
        ApplicationStatusChanged::dispatch($application->id,$old,'withdrawn');
        return response()->json(['status'=>'success','data'=>$application->fresh()]);
    }
    public function contact(Request $request, Application $application): JsonResponse
    {
        abort_unless($request->user()->company && $application->jobPost->company_id===$request->user()->company->id,403);
        if (! $request->user()->company->isVerified()) { return response()->json(['message'=>'Company verification required.'],403); }
        $profile=$application->seeker->seekerProfile;
        if (! $profile?->show_contact) { return response()->json(['message'=>'Applicant has hidden contact details.'],403); }
        return response()->json(['status'=>'success','data'=>['email'=>$application->seeker->email,'phone'=>$application->seeker->phone]]);
    }
}
