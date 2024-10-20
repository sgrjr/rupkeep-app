<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Customer;
use Illuminate\Support\Facades\Hash;

class Organization extends Model
{
    protected $fillable = [
        'name',
        'primary_contact',
        'telephone',
        'fax',
        'email',
        'street',
        'city',
        'state',
        'zip',
        'user_id',
        'logo_url',
        'website_url',
    ];

    public $appends = ['logo','is_super'];

    public function getLogoAttribute(){
        if(empty($this->logo_url)){
            return url('/storage/images/simple-logo.png');
        }else if(str_starts_with($this->logo_url, 'http')){
            return $this->logo_url;
        }else{
            return url('storage/uploads/'. $this->logo_url);
        }
        
    }

    public function getIsSuperAttribute(){
        return $this->name == 'Reynolds Upkeep';
    }

    public function getOwnerEmailAttribute(){
        return $this->owner?->email;
    }

    public function owner(){
        return $this->belongsTo(User::class, 'user_id','id');
    }

    public function users(){
        return $this->hasMany(User::class, 'organization_id','id');
    }

    public function customers(){
        return $this->hasMany(Customer::class, 'organization_id','id');
    }

    public function jobs(){
        return $this->hasMany(PilotCarJob::class, 'organization_id','id');
    }

    public function createUser($input){
        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
            'organization_id' => $this->id,
            'organization_role' => array_key_exists('organization_role', $input)? $input['organization_role']:'administrator'
        ]);

        return $user;
    }

    public static function roles(){
       return [[
        'id'=>'admin', 
        'name'=>'Administrator', 
        'permissions'=>[
            'create',
            'read',
            'update',
            'delete',
       ],
       'description'=> 'Administrator users can perform any action.'
    ],[
        'id'=>'editor', 
        'name'=>'Editor', 
        'permissions'=>[
            'read',
            'create',
            'update',
       ],
       'description'=> 'Editor users have the ability to read, create, and update.'
    ]];
    
    }
}
