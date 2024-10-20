<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Organization;

class Attachment extends Model
{
    public $fillable = [
        'attachable_id',
        'attachable_type',
        'location',
        'organization_id'
    ];

    public function organization(){
        return $this->belongsTo(Organization::class);
    }
}
