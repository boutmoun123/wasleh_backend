<?php
namespace App\Listeners;
use App\Events\ApplicationStatusChanged;
use App\Models\Application;
use App\Notifications\WaslaAlert;
use Illuminate\Contracts\Queue\ShouldQueue;
class NotifyApplicant implements ShouldQueue {
    public function handle(ApplicationStatusChanged $event): void {
        $app=Application::with('seeker')->find($event->applicationId);
        $app?->seeker?->notify(new WaslaAlert('Application status changed to: '.$event->to,'application_status',$event->applicationId));
    }
}
