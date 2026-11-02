<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\AbuseReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class ReportController extends Controller {
    public function store(Request $request): JsonResponse {
        $data=$request->validate(['job_post_id'=>['nullable','integer','exists:job_posts,id'],
            'reported_user_id'=>['nullable','integer','exists:users,id'],
            'reason'=>['required','string','max:200'],'details'=>['nullable','string','max:2000']]);
        if (empty($data['job_post_id']) && empty($data['reported_user_id'])) {
            return response()->json(['message'=>'Select a job or user to report.'],422);
        }
        $report=AbuseReport::create($data+['reporter_id'=>$request->user()->id]);
        return response()->json(['status'=>'success','data'=>$report],201);
    }
}
