<?php
namespace App\Http\Controllers\Api;
use App\Events\JobPublished;
use App\Http\Controllers\Controller;
use App\Models\JobPost;
use App\Models\Quota;
use App\Services\ContactGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class JobController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query=JobPost::with('company:id,name,industry,city,verification_status')
            ->where('status','active')->where(function($q){$q->whereNull('expires_at')->orWhereDate('expires_at','>=',today());});
        foreach (['city','job_type','employment_type'] as $key) {
            if ($request->filled($key)) { $query->where($key,$request->string($key)->toString()); }
        }
        if ($request->filled('q')) { $term=mb_substr($request->string('q')->toString(),0,100); $query->where('title','like','%'.$term.'%'); }
        if ($request->filled('salary_min')) { $query->where('salary_max','>=',(int)$request->input('salary_min')); }
        return response()->json(['status'=>'success','data'=>$query->latest()->paginate(15)]);
    }
    public function publicShow(JobPost $job): JsonResponse
    {
        abort_unless($job->status==='active' && (! $job->expires_at || $job->expires_at->startOfDay()->gte(today())),404);
        return response()->json(['status'=>'success','data'=>$job->load('company:id,name,industry,city,verification_status')]);
    }
    private function mine(Request $request, JobPost $job): void
    {
        abort_unless($request->user()->company && $job->company_id===$request->user()->company->id,403);
    }
    private function rules(): array
    {
        return [
            'title'=>['required','string','max:255'], 'description'=>['required','string','max:8000'],
            'city'=>['nullable','string','max:120'],'job_type'=>['nullable',Rule::in(['onsite','remote','hybrid'])],
            'employment_type'=>['nullable',Rule::in(['full_time','part_time','contract','internship'])],
            'salary_min'=>['nullable','integer','min:0'],'salary_max'=>['nullable','integer','gte:salary_min'],
            'min_experience_years'=>['nullable','integer','between:0,60'],
            'skills_required'=>['required','array','max:60'],'skills_required.*'=>['required','string','max:100'],
            'requirements'=>['nullable','array','max:30'],'requirements.*'=>['string','max:300'],
            'expires_at'=>['nullable','date','after_or_equal:today'],
        ];
    }
    private function validateText(array $data, ContactGuard $guard): void
    {
        $text=($data['title']??'').' '.($data['description']??'').' '.($data['city']??'').' '.implode(' ', $data['requirements']??[]).' '.implode(' ', $data['skills_required']??[]);
        if ($guard->containsContact($text)) {
            throw ValidationException::withMessages(['description'=>['Contact details are not allowed in job text.']]);
        }
    }
    public function mineIndex(Request $request): JsonResponse
    {
        $company=$request->user()->company;
        return response()->json(['status'=>'success','data'=>$company ? $company->jobs()->latest()->paginate(15) : []]);
    }
    public function store(Request $request, ContactGuard $guard): JsonResponse
    {
        $company=$request->user()->company;
        if (! $company) { return response()->json(['message'=>'Create company profile first.'],422); }
        $data=$request->validate($this->rules()); $this->validateText($data,$guard);
        $job=$company->jobs()->create($data + ['status'=>'draft']);
        return response()->json(['status'=>'success','data'=>$job],201);
    }
    public function update(Request $request, JobPost $job, ContactGuard $guard): JsonResponse
    {
        $this->mine($request,$job);
        abort_if($job->status==='closed',409,'Closed jobs cannot be edited.');
        $data=$request->validate($this->rules()); $this->validateText($data,$guard);
        $job->update($data);
        return response()->json(['status'=>'success','data'=>$job]);
    }
    public function destroy(Request $request, JobPost $job): JsonResponse
    {
        $this->mine($request,$job);
        if ($job->status!=='draft' || $job->applications()->exists()) {
            return response()->json(['message'=>'Only unused draft jobs may be deleted.'],409);
        }
        $job->delete();
        return response()->json(['status'=>'success','message'=>'Draft job deleted.']);
    }

    public function publish(Request $request, JobPost $job): JsonResponse
    {
        $this->mine($request,$job);
        if ($job->status==='active') { return response()->json(['status'=>'success','data'=>$job]); }
        if ($job->status==='closed') { return response()->json(['message'=>'Cannot reopen a closed job.'],409); }
        if (! $request->user()->company->isVerified()) { return response()->json(['message'=>'Company verification required.'],403); }
        if ($job->expires_at && $job->expires_at->startOfDay()->lt(today())) { return response()->json(['message'=>'Job has expired.'],422); }
        $newPublication=$job->published_at === null;
        try {
            DB::transaction(function () use ($job,$newPublication) {
                $locked=JobPost::whereKey($job->id)->lockForUpdate()->firstOrFail();
                if ($locked->status==='active') { return; }
                if ($newPublication && $locked->published_at===null) {
                    $quota=Quota::where('company_id',$locked->company_id)->lockForUpdate()->first();
                    if (! $quota || $quota->available_posts<1) { throw ValidationException::withMessages(['quota'=>['No available publishing quota.']]); }
                    $quota->decrement('available_posts');
                    $locked->published_at=now();
                }
                $locked->status='active'; $locked->save();
            });
        } catch (ValidationException $e) { throw $e; }
        $job->refresh();
        if ($newPublication && $job->published_at) { JobPublished::dispatch($job->id); }
        return response()->json(['status'=>'success','data'=>$job]);
    }
    public function pause(Request $request, JobPost $job): JsonResponse
    {
        $this->mine($request,$job);
        abort_unless($job->status==='active',409);
        $job->update(['status'=>'paused']);
        return response()->json(['status'=>'success','data'=>$job]);
    }
    public function close(Request $request, JobPost $job): JsonResponse
    {
        $this->mine($request,$job);
        if ($job->status==='closed') { return response()->json(['status'=>'success','data'=>$job]); }
        if ($job->applications()->whereNotIn('status',['accepted','rejected','withdrawn'])->exists()) {
            return response()->json(['status'=>'error','message'=>'Record final outcome for every application before closing the job.'],422);
        }
        $job->update(['status'=>'closed','closed_at'=>now()]);
        return response()->json(['status'=>'success','data'=>$job]);
    }
}
