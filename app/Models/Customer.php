<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\PilotCarJob;
use App\Models\Organization;
use App\Models\CustomerContact;
use App\Models\User;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Customer extends Model
{
    use HasFactory;

    public $timestamps = true;

    public $fillable = [
        'name','street','city','state','zip','organization_id'
    ];

    public function jobs(){
        return $this->hasMany(PilotCarJob::class);
    }

    public function organization(){
        return $this->belongsTo(Organization::class);
    }

    public function contacts(){
        return $this->hasMany(CustomerContact::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
