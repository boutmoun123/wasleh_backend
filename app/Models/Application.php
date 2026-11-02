<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Application extends Model {
    protected $guarded = ['id'];
    protected function casts(): array { return ['match_details'=>'array','match_score'=>'float','applied_at'=>'datetime','final_at'=>'datetime']; }
    public function jobPost() { return $this->belongsTo(JobPost::class); }
    public function seeker() { return $this->belongsTo(User::class, 'seeker_user_id'); }
    public function history() { return $this->hasMany(ApplicationStatusHistory::class); }
}
