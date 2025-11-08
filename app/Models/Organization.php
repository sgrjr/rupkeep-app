<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Customer;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Hash;

class Organization extends Model
{

    use SoftDeletes;
    use HasFactory;

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
        'deleted_at'
    ];

    public $appends = ['logo','is_super'];

    public function getLogoAttribute(){
        if(empty($this->logo_url)){
            return url('images/organization-logo-2.avif');
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

    public function vehicles(){
        return $this->hasMany(Vehicle::class, 'organization_id','id');
    }

    public function createUser($input){
        $role = $input['organization_role'] ?? $input['role'] ?? User::ROLE_ADMIN;

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
            'organization_id' => $this->id,
            'organization_role' => $role
        ]);

        return $user;
    }

    public static function roles(){
       return [
            [
                'id' => User::ROLE_ADMIN,
                'name' => User::ROLE_LABELS[User::ROLE_ADMIN],
                'permissions' => ['create', 'read', 'update', 'delete'],
                'description' => 'Administrators can perform any action.',
            ],
            [
                'id' => User::ROLE_EMPLOYEE_MANAGER,
                'name' => User::ROLE_LABELS[User::ROLE_EMPLOYEE_MANAGER],
                'permissions' => ['read', 'create', 'update'],
                'description' => 'Managers can read, create, and update records.',
            ],
            [
                'id' => User::ROLE_EMPLOYEE_STANDARD,
                'name' => User::ROLE_LABELS[User::ROLE_EMPLOYEE_STANDARD],
                'permissions' => ['work'],
                'description' => 'Standard employees/drivers can complete assigned jobs.',
            ],
            [
                'id' => User::ROLE_CUSTOMER,
                'name' => User::ROLE_LABELS[User::ROLE_CUSTOMER],
                'permissions' => ['view_invoices'],
                'description' => 'Customers can view and comment on their invoices.',
            ],
        ];
    }
}
