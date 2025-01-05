<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Organization;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use HasFactory;
    use SoftDeletes;

    public $timestamps = true;

    public $fillable = [
        'name','odometer','odometer_updated_at','organization_id','user_id','deleted_at'
    ];

    public function organization(){
        return $this->belongsTo(Organization::class);
    }

    public function user(){
        return $this->belongsTo(User::class);
    }

    public static function positionOptions(){
        return [
            ['name' => '(none selected)', 'value'=>null],
            ['name' => 'Lead', 'value'=>'lead'],
            ['name' => 'Chase', 'value'=>'chase'],
            ['name' => 'Mixed', 'value'=>'mixed'],
        ];
    }
}
