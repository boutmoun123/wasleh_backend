<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['verified_at' => 'datetime']; }
    public function user() { return $this->belongsTo(User::class); }
    public function jobs() { return $this->hasMany(JobPost::class); }
    public function quota() { return $this->hasOne(Quota::class); }
    public function documents() { return $this->hasMany(CompanyDocument::class); }
    public function isVerified(): bool { return $this->verification_status === 'verified' && $this->verified_at !== null; }
}
