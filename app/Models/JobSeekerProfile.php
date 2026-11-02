<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobSeekerProfile extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array
    {
        return [
            'basic_info'=>'array', 'skills'=>'array', 'experience'=>'array', 'education'=>'array',
            'languages'=>'array', 'preferences'=>'array', 'is_public'=>'boolean', 'show_contact'=>'boolean',
        ];
    }
    public function user() { return $this->belongsTo(User::class); }
}
