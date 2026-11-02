<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'phone', 'password'];
    protected $hidden = ['password', 'remember_token', 'otp_code', 'otp_expires_at', 'otp_attempts'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed', 'is_verified' => 'boolean',
            'otp_expires_at' => 'datetime', 'email_verified_at' => 'datetime',
        ];
    }

    public function company() { return $this->hasOne(Company::class); }
    public function seekerProfile() { return $this->hasOne(JobSeekerProfile::class); }
    public function applications() { return $this->hasMany(Application::class, 'seeker_user_id'); }
}
