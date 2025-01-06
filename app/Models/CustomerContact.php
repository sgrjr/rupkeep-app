<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Organization;

class CustomerContact extends Model
{
    public $timestamps = true;

    public $fillable = [
        'customer_id', 'name', 'phone','memo','organization_id','email'
    ];

    public function organization(){
        return $this->belongsTo(Organization::class);
    }
}
