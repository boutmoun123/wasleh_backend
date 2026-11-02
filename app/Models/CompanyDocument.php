<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CompanyDocument extends Model {
    protected $guarded = ['id'];
    public function company() { return $this->belongsTo(Company::class); }
}
