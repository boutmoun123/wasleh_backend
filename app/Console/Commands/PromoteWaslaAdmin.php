<?php
namespace App\Console\Commands;
use App\Models\User;
use Illuminate\Console\Command;
class PromoteWaslaAdmin extends Command {
    protected $signature='wasla:promote-admin {email}';
    protected $description='Promote an existing verified user to WASLA admin (console only).';
    public function handle(): int {
        $user=User::where('email',$this->argument('email'))->first();
        if (! $user || ! $user->is_verified) { $this->error('Verified user not found.'); return self::FAILURE; }
        if (! $this->confirm('Grant system-admin privileges to '.$user->email.'?')) { return self::FAILURE; }
        $user->forceFill(['role'=>'admin'])->save();
        // Existing tokens can be revoked; issue a fresh token on next login.
        $user->tokens()->delete();
        $this->info('Admin role granted.'); return self::SUCCESS;
    }
}
