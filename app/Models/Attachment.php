<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Organization;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Exception;

class Attachment extends Model
{
    use SoftDeletes;

    public $fillable = [
        'attachable_id',
        'attachable_type',
        'location',
        'is_public',
        'organization_id',
        'deleted_at'
    ];

    public function organization(){
        return $this->belongsTo(Organization::class);
    }

    public function attachable(){
        return $this->morphTo();
    }

    public function getFileNameAttribute(){
        $array = explode('/',$this->location);
        return $array[array_key_last($array)];
    }

    public function deleteFile(){
        if(file_exists($this->location)){
            try {
                unlink($this->location);
            }

            catch(Exception $exception){
                //dd($exception);
            }
        }
        return $this;
    }

}
