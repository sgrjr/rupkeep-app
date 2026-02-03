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
use App\Models\LoginCode;
use App\Models\Customer;
use NotificationChannels\WebPush\HasPushSubscriptions;

class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;
    use SoftDeletes;
    use HasPushSubscriptions;

    public const ROLE_GUEST = 'guest';
    public const ROLE_EMPLOYEE_STANDARD = 'employee_standard';
    public const ROLE_EMPLOYEE_MANAGER = 'employee_manager';
    public const ROLE_CUSTOMER = 'customer';
    public const ROLE_ADMIN = 'admin';

    public const ROLE_LABELS = [
        self::ROLE_GUEST => 'Guest (No Access)',
        self::ROLE_EMPLOYEE_STANDARD => 'Employee: Standard',
        self::ROLE_EMPLOYEE_MANAGER => 'Employee: Manager',
        self::ROLE_CUSTOMER => 'Customer',
        self::ROLE_ADMIN => 'Admin / Super User',
    ];

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
        'customer_id',
        'organization_role',
        'theme',
        'notification_address',
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
        'dashboard_theme',
        'role_label',
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

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function loginCodes()
    {
        return $this->hasMany(LoginCode::class);
    }

    public function getDashboardThemeAttribute(){
        return 'default-theme';
        return $this->theme? $this->theme:'default-theme';
    }

    public function getRoleLabelAttribute(): string
    {
        return self::ROLE_LABELS[$this->organization_role] ?? ucfirst(str_replace('_', ' ', (string)$this->organization_role));
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
        return [
            [
                'id' => self::ROLE_ADMIN,
                'name' => self::ROLE_LABELS[self::ROLE_ADMIN],
                'permissions' => ['create', 'read', 'update', 'delete'],
                'description' => 'Administrators can perform any action and manage other users.',
                'short_description' => 'Full access + manage users',
            ],
            [
                'id' => self::ROLE_EMPLOYEE_MANAGER,
                'name' => self::ROLE_LABELS[self::ROLE_EMPLOYEE_MANAGER],
                'permissions' => ['create', 'read', 'update'],
                'description' => 'Managers can create and update jobs, logs, and customers.',
                'short_description' => 'Create, update, review',
            ],
            [
                'id' => self::ROLE_EMPLOYEE_STANDARD,
                'name' => self::ROLE_LABELS[self::ROLE_EMPLOYEE_STANDARD],
                'permissions' => ['work'],
                'description' => 'Standard employees (drivers) can work assigned jobs and submit logs.',
                'short_description' => 'Complete jobs & submit logs',
            ],
            [
                'id' => self::ROLE_CUSTOMER,
                'name' => self::ROLE_LABELS[self::ROLE_CUSTOMER],
                'permissions' => ['view_invoices'],
                'description' => 'Customers can access their invoices, comment, and flag issues.',
                'short_description' => 'View & comment on invoices',
            ],
        ];
    }

    public function getOrganizationNameAttribute(){
        return $this->organization?->name;
    }

    public function getIsSuperAttribute(){
        return $this->organization_name === 'Reynolds Upkeep' && $this->isAdmin();
    }

    public function isAdmin(): bool
    {
        return $this->organization_role === self::ROLE_ADMIN;
    }

    public function isManager(): bool
    {
        return $this->organization_role === self::ROLE_EMPLOYEE_MANAGER;
    }

    public function isStandardEmployee(): bool
    {
        return $this->organization_role === self::ROLE_EMPLOYEE_STANDARD;
    }

    public function isEmployee(): bool
    {
        return $this->isAdmin() || $this->isManager() || $this->isStandardEmployee();
    }

    public function isCustomer(): bool
    {
        return $this->organization_role === self::ROLE_CUSTOMER;
    }

    /**
     * Get the SMS gateway email address for this user
     * 
     * Uses notification_address if it's already a gateway address (contains @)
     * Otherwise returns null (will fall back to regular email)
     * 
     * @return string|null
     */
    public function getSmsGatewayAddress(): ?string
    {
        if (empty($this->notification_address)) {
            return null;
        }

        $address = trim($this->notification_address);
        
        // If it contains @, it's likely an email gateway address
        if (str_contains($address, '@')) {
            return $address;
        }

        return null;
    }

    /**
     * Get phone number from notification_address if it's a gateway address
     * 
     * @return string|null
     */
    public function getPhoneFromNotificationAddress(): ?string
    {
        if (empty($this->notification_address)) {
            return null;
        }

        $address = trim($this->notification_address);
        
        // Extract phone number from format: 2074168659@mms.uscc.net
        if (preg_match('/^(\d+)@/', $address, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Get SMS provider from notification_address if it's a gateway address
     * 
     * @return string|null Provider key (e.g., 'uscc', 'att', 'verizon')
     */
    public function getSmsProviderFromNotificationAddress(): ?string
    {
        if (empty($this->notification_address)) {
            return null;
        }

        $address = trim($this->notification_address);
        
        // Extract domain and match to provider
        if (preg_match('/@([^@]+)$/', $address, $matches)) {
            $domain = $matches[1];
            
            $providers = config('sms_gateways.providers', []);
            foreach ($providers as $key => $provider) {
                if (str_contains($domain, $provider['sms']) || str_contains($domain, $provider['mms'] ?? '')) {
                    return $key;
                }
            }
        }

        return null;
    }

    /**
     * Check if user has SMS notification capability
     */
    public function hasSmsNotification(): bool
    {
        return $this->getSmsGatewayAddress() !== null;
    }
}