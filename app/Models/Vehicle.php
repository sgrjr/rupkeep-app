<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Organization;

class Vehicle extends Model
{
    use HasFactory;
    public $timestamps = true;

    public $fillable = [
        'name','odometer','odometer_updated_at','organization_id','user_id'
    ];

    public function organization(){
        return $this->belongsTo(Organization::class);
    }

    public function user(){
        return $this->belongsTo(User::class);
    }
}
