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

    /**
     * Enforce a single main (and single billing) contact per customer: whenever
     * a contact is saved with one of these flags on, demote the customer's other
     * contacts. Uses a mass update, which bypasses model events, so there is no
     * recursion. See TASK-334.
     */
    protected static function booted(): void
    {
        static::saved(function (self $contact): void {
            if (! $contact->customer_id) {
                return;
            }

            foreach (['is_main_contact', 'is_billing_contact'] as $flag) {
                if ($contact->{$flag}) {
                    static::where('customer_id', $contact->customer_id)
                        ->where('id', '!=', $contact->id)
                        ->where($flag, true)
                        ->update([$flag => false]);
                }
            }
        });
    }

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
