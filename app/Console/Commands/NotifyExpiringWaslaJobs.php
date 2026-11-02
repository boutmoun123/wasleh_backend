<?php
namespace App\Console\Commands;
use App\Models\JobPost;
use App\Notifications\WaslaAlert;
use Illuminate\Console\Command;
class NotifyExpiringWaslaJobs extends Command {
    protected $signature='wasla:notify-expiring-jobs';
    protected $description='Notify employers whose job closing date is tomorrow.';
    public function handle(): int {
        $sent=0;
        JobPost::with('company.user')->where('status','active')->whereDate('expires_at',today()->addDay())
            ->chunkById(100,function($jobs)use(&$sent){
                foreach($jobs as $job) {
                    if (!$job->company?->user) { continue; }
                    $job->company->user->notify(new WaslaAlert('Job closes tomorrow: '.$job->title,'job_deadline',$job->id));
                    $sent++;
                }
            });
        $this->info('Notified '.$sent.' employers.'); return self::SUCCESS;
    }
}
