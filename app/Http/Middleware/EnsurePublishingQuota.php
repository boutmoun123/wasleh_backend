<?php
namespace App\Http\Middleware;
use App\Models\JobPost;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
class EnsurePublishingQuota {
    public function handle(Request $request, Closure $next): Response {
        $company=$request->user()?->company;
        if (! $company || ! $company->isVerified()) {
            return response()->json(['message'=>'A verified company profile is required.'],403);
        }
        $job=$request->route('job');
        if (! $job instanceof JobPost || $job->company_id !== $company->id) {
            return response()->json(['message'=>'Job does not belong to this company.'],403);
        }
        if ($job->status!=='active' && $job->published_at===null && ($company->quota?->available_posts ?? 0)<1) {
            return response()->json(['message'=>'Publishing quota exhausted.'],422);
        }
        return $next($request);
    }
}
