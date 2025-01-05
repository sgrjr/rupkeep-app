<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Organization;

class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'organization_id',
        'organization_role',
        'theme'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
        'dashboard_theme'
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function organization(){
        return $this->belongsTo(Organization::class);
    }

    public function getDashboardThemeAttribute(){
        return $this->theme? $this->theme:'default-theme';
    }

    public static function superUser(){
        $email = config('setup.super_user.email');
        return static::where('email', $email)->first();
    }

    public static function themes(){
        return [
            (Object)['title'=>'Bright (default)', 'value'=>'default-theme'],
            (Object)['title'=>'Dark', 'value'=>'dark-theme'],
        ];
    }

    public static function roles(){
        return [[
            'id'=>'admin', 
            'name'=>'Administrator', 
            'permissions'=>[
                'create',
                'read',
                'update',
                'delete'
           ],
           'description'=> 'Administrator users can perform any action.',
           'short_description' => 'can do anything+create users'
        ],[
            'id'=>'editor', 
            'name'=>'Editor', 
            'permissions'=>[
                'read',
                'create',
                'update',
           ],
           'description'=> 'Editor users have the ability to read, create, and update.',
           'short_description' => 'can read, write and update'
        ],[
            'id'=>'viewer', 
            'name'=>'Viewer', 
            'permissions'=>[
                'read'
           ],
           'description'=> 'Viewer users have the ability to read.',
           'short_description' => 'can only read'
        ],[
            'id'=>'driver', 
            'name'=>'Driver', 
            'permissions'=>[
                'work'
           ],
           'description'=> 'Driver users have the ability to complete jobs.',
           'short_description' => 'can only complete jobs'
        ]];
    }

    public function getOrganizationNameAttribute(){
        return $this->organization()->select('name')->first()->name;
    }

    public function getIsSuperAttribute(){
        return $this->organization_name == 'Reynolds Upkeep' && $this->organization_role === 'administrator';
    }
}