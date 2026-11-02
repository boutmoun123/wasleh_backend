<?php
namespace App\Listeners;
use App\Events\ApplicationSubmitted;
use App\Models\Application;
use App\Notifications\WaslaAlert;
use Illuminate\Contracts\Queue\ShouldQueue;
class NotifyCompanyNewApplication implements ShouldQueue {
    public function handle(ApplicationSubmitted $event): void {
        $app=Application::with('jobPost.company.user')->find($event->applicationId);
        $app?->jobPost?->company?->user?->notify(new WaslaAlert('New job application received','new_application',$event->applicationId));
    }
}
