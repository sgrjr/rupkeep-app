<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Organization;

class CustomerContact extends Model
{
    public $timestamps = true;

    public $fillable = [
        'customer_id', 'name', 'phone','memo','organization_id','email',
        'is_main_contact', 'is_billing_contact', 'notification_address'
    ];

    protected $casts = [
        'is_main_contact' => 'boolean',
        'is_billing_contact' => 'boolean',
    ];

    public function organization(){
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the SMS gateway email address for this contact
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
     * Check if contact has SMS notification capability
     */
    public function hasSmsNotification(): bool
    {
        return $this->getSmsGatewayAddress() !== null;
    }
}
