<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\PilotCarJob;
use App\Models\Organization;
use App\Models\CustomerContact;

class Customer extends Model
{
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
}
