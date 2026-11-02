<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class JobPost extends Model {
    protected $guarded = ['id'];
    protected function casts(): array {
        return ['skills_required'=>'array','requirements'=>'array','published_at'=>'datetime',
                'closed_at'=>'datetime','expires_at'=>'date'];
    }
    public function company() { return $this->belongsTo(Company::class); }
    public function applications() { return $this->hasMany(Application::class); }
}
