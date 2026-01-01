<?php

namespace App\Actions;

use App\Models\CustomerContact;
use Illuminate\Support\Facades\Mail;

class SendCustomerContactNotification
{
    /**
     * Send notification to a customer contact (truck driver)
     * Supports SMS gateway addresses via email-to-SMS
     */
    public static function to(CustomerContact $contact, string $message, ?string $subject = null): void
    {
        $api_key = config('mail.mailers.brevo.key');
        $sender_email = config('mail.mailers.brevo.sender.email');
        $sender_name = config('mail.mailers.brevo.sender.name');

        // Configure API key authorization
        $config = \Brevo\Client\Configuration::getDefaultConfiguration()->setApiKey('api-key', $api_key);
        $config = \Brevo\Client\Configuration::getDefaultConfiguration()->setApiKey('partner-key', $api_key);

        $apiInstance = new \Brevo\Client\Api\TransactionalEmailsApi(
            new \GuzzleHttp\Client(),
            $config
        );

        // Determine recipient: prefer SMS gateway, fallback to email, then phone
        $recipient = $contact->getSmsGatewayAddress() ?? $contact->email;
        
        // If no email/gateway, try to construct SMS gateway from phone if we have provider info
        // For now, we'll just use what's available
        if (empty($recipient) && !empty($contact->phone)) {
            // Could construct gateway here if we had provider info
            // For now, skip if no notification_address or email
            return;
        }

        if (empty($recipient)) {
            // No valid recipient address
            return;
        }

        $sendSmtpEmail = new \Brevo\Client\Model\SendSmtpEmail([
            'subject' => $subject ?? 'Job Status Update',
            'sender' => ['name' => $sender_name, 'email' => $sender_email],
            'to' => [[ 'name' => $contact->name, 'email' => $recipient]],
            'textContent' => $message,
        ]);

        try {
            $result = $apiInstance->sendTransacEmail($sendSmtpEmail);
        } catch (\Exception $e) {
            // Log error but don't break the flow
            \Log::error('Failed to send notification to customer contact: ' . $e->getMessage());
        }
    }
}

