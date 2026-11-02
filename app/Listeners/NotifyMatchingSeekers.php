<?php
namespace App\Listeners;
use App\Events\JobPublished;
use App\Models\JobPost;
use App\Models\JobSeekerProfile;
use App\Notifications\WaslaAlert;
use App\Services\MatchEngine;
use Illuminate\Contracts\Queue\ShouldQueue;
class NotifyMatchingSeekers implements ShouldQueue {
    public function handle(JobPublished $event): void {
        $job=JobPost::find($event->jobId);
        if (! $job || $job->status!=='active') { return; }
        JobSeekerProfile::with('user')->chunkById(100,function ($profiles) use($job) {
            foreach ($profiles as $profile) {
                if (! $profile->user?->is_verified || $profile->user->role!=='seeker') { continue; }
                $preferences=$profile->preferences ?? [];
                if (! ($preferences['alerts']??false)) { continue; }
                if (! empty($preferences['city']) && ! empty($job->city) && mb_strtolower(trim($preferences['city']))!==mb_strtolower(trim($job->city))) { continue; }
                if ((app(MatchEngine::class)->evaluate($profile,$job)['score'] ?? 0)<50) { continue; }
                $profile->user->notify(new WaslaAlert('New matching job: '.$job->title,'matching_job',$job->id));
            }
        });
    }
}
