<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Quota extends Model {
    protected $guarded = ['id'];
    protected function casts(): array { return ['last_renewed'=>'datetime']; }
    public function company() { return $this->belongsTo(Company::class); }
}
