<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\AbuseReport;
use App\Models\Company;
use App\Models\CompanyDocument;
use App\Models\JobPost;
use App\Models\Quota;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminController extends Controller
{
    public function companies(): JsonResponse
    {
        return response()->json(['status'=>'success','data'=>Company::with('documents:id,company_id,original_name,review_status','quota')->latest()->paginate(20)]);
    }
    public function verifyCompany(Request $request, Company $company): JsonResponse
    {
        $data=$request->validate(['status'=>['required',Rule::in(['verified','rejected'])], 'note'=>['nullable','string','max:500']]);
        if ($data['status']==='verified' && ! $company->documents()->exists()) {
            return response()->json(['message'=>'Upload company verification documents before approval.'],422);
        }
        $company->update(['verification_status'=>$data['status'],'verified_at'=>$data['status']==='verified'?now():null]);
        if ($data['status']==='rejected' && isset($data['note'])) {
            $company->documents()->where('review_status','pending')->update(['review_status'=>'rejected','review_note'=>$data['note']]);
        }
        if ($data['status']==='verified') { $company->documents()->where('review_status','pending')->update(['review_status'=>'accepted']); }
        return response()->json(['status'=>'success','data'=>$company->fresh()]);
    }
    public function downloadDocument(CompanyDocument $document)
    {
        abort_unless(Storage::disk($document->disk)->exists($document->path),404);
        return Storage::disk($document->disk)->download($document->path,$document->original_name);
    }
    public function grantQuota(Request $request, Company $company): JsonResponse
    {
        $data=$request->validate(['amount'=>['required','integer','between:1,100000']]);
        DB::transaction(function () use($company,$data) {
            $quota=Quota::firstOrCreate(['company_id'=>$company->id],['available_posts'=>0]);
            $quota=Quota::whereKey($quota->id)->lockForUpdate()->firstOrFail();
            $quota->increment('available_posts',(int)$data['amount'],['last_renewed'=>now()]);
        });
        return response()->json(['status'=>'success','data'=>$company->quota()->first()]);
    }
    public function reports(): JsonResponse
    {
        return response()->json(['status'=>'success','data'=>AbuseReport::latest()->paginate(25)]);
    }
    public function reviewReport(Request $request, AbuseReport $report): JsonResponse
    {
        $data=$request->validate(['status'=>['required',Rule::in(['reviewing','resolved','dismissed'])],
                                  'admin_note'=>['nullable','string','max:1000'], 'hide_job'=>['nullable','boolean']]);
        $report->update(['status'=>$data['status'],'admin_note'=>$data['admin_note']??null]);
        if (($data['hide_job']??false) && $report->job_post_id) { JobPost::whereKey($report->job_post_id)->where('status','active')->update(['status'=>'paused']); }
        return response()->json(['status'=>'success','data'=>$report]);
    }
    public function exportApplicants(JobPost $job): StreamedResponse
    {
        return response()->streamDownload(function () use($job) {
            $stream=fopen('php://output','w'); fwrite($stream,"\xEF\xBB\xBF");
            fputcsv($stream,['application_id','job_id','seeker_id','seeker_name','status','match_score','applied_at']);
            $job->applications()->with('seeker:id,name')->orderBy('id')->chunk(250,function ($apps) use($stream) {
                foreach ($apps as $app) {
                    // CSV formula injection protection for spreadsheet consumers.
                    $name=$app->seeker?->name ?? '';
                    if (preg_match('/^[\s]*[=+@\-]/u',$name)) { $name="'".$name; }
                    fputcsv($stream,[$app->id,$app->job_post_id,$app->seeker_user_id,$name,$app->status,$app->match_score,$app->applied_at]);
                }
            });
            fclose($stream);
        },'wasla-job-'.$job->id.'-applicants.csv',['Content-Type'=>'text/csv; charset=UTF-8']);
    }
}
