<?php

namespace App\Actions;

use App\Models\User;
use App\Mail\UserNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Google\Client;
use Google\Service\Gmail;
use Google\Service\Gmail\Message;

class SendUserNotification
{

    public static function to(User $user, String $message, String $subject = null)
    {
        // Determine recipient: prefer SMS gateway, fallback to email
        $recipient = $user->getSmsGatewayAddress() ?? $user->email;
        $isSmsGateway = $user->getSmsGatewayAddress() !== null;
        
        Log::info('SendUserNotification: Preparing to send', [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'recipient' => $recipient,
            'subject' => $subject,
            'is_sms_gateway' => $isSmsGateway,
        ]);
        
        if (empty($recipient)) {
            Log::warning('SendUserNotification: No valid recipient address', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'notification_address' => $user->notification_address,
            ]);
            return;
        }

        // For SMS gateway addresses (phone numbers), try Brevo API first (proven working method)
        // Fall back to Laravel Mail if Brevo fails or is not configured (e.g., in development)
        // For regular emails, use standard Laravel Mail facade
        if ($isSmsGateway) {
            // Try Brevo API for SMS gateway addresses (proven working method from test notification)
            $api_key = config('mail.mailers.brevo.key');
            
            // If Brevo API key is configured, try using Brevo
            if (!empty($api_key)) {
                $sender_email = config('mail.mailers.brevo.sender.email');
                $sender_name = config('mail.mailers.brevo.sender.name');

                // Configure API key authorization: api-key
                $config = \Brevo\Client\Configuration::getDefaultConfiguration()->setApiKey('api-key', $api_key);
                // Configure API key authorization: partner-key
                $config = \Brevo\Client\Configuration::getDefaultConfiguration()->setApiKey('partner-key', $api_key);

                $apiInstance = new \Brevo\Client\Api\TransactionalEmailsApi(
                    new \GuzzleHttp\Client(),
                    $config
                );

                $sendSmtpEmail = new \Brevo\Client\Model\SendSmtpEmail([
                    'subject' => $subject,
                    'sender' => ['name' => $sender_name, 'email' => $sender_email],
                    'to' => [[ 'name' => $user->name, 'email' => $recipient]],
                    'textContent' => $message,
                    'params' => ['bodyMessage' => 'made just for you!']
                ]);

                try {
                    $result = $apiInstance->sendTransacEmail($sendSmtpEmail);
                    Log::info('SendUserNotification: Sent successfully via Brevo (SMS gateway)', [
                        'user_id' => $user->id,
                        'recipient' => $recipient,
                        'subject' => $subject,
                        'message_id' => $result->getMessageId() ?? 'unknown',
                    ]);
                    return; // Success, exit early
                } catch (\Exception $e) {
                    Log::warning('SendUserNotification: Brevo failed, falling back to Laravel Mail', [
                        'user_id' => $user->id,
                        'recipient' => $recipient,
                        'error' => $e->getMessage(),
                    ]);
                    // Fall through to Laravel Mail fallback below
                }
            } else {
                Log::info('SendUserNotification: Brevo API key not configured, using Laravel Mail', [
                    'user_id' => $user->id,
                    'recipient' => $recipient,
                ]);
                // Fall through to Laravel Mail fallback below
            }
        }
        
        // Use standard Laravel Mail facade (respects MAIL_MAILER config - will log in development)
        // This handles both regular emails and SMS gateway fallback
        try {
            Mail::to($recipient)->send(new UserNotification($message, $subject));
            
            Log::info('SendUserNotification: Sent successfully via Laravel Mail', [
                'user_id' => $user->id,
                'recipient' => $recipient,
                'subject' => $subject,
                'mailer' => config('mail.default'),
            ]);
        } catch (\Exception $e) {
            Log::error('SendUserNotification: Failed to send via Laravel Mail', [
                'user_id' => $user->id,
                'recipient' => $recipient,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
        /*
        $client = new Client();
        $client->setClientId(env('GOOGLE_CLIENT_ID'));
        $client->setClientSecret(env('GOOGLE_CLIENT_SECRET'));
        $client->setRedirectUri(env('GOOGLE_REDIRECT_URI'));
        $client->addScope(Gmail::GMAIL_SEND);

        $gmail = new Gmail($client);
        $gmailMessage = new Message();

        $rawMessageString = "To: {$user->notification_address}\r\n";
        $rawMessageString .= "Subject: {$subject}\r\n";
        $rawMessageString .= "Content-Type: text/plain; charset=utf-8\r\n\r\n";
        $rawMessageString .= $message;

        // Base64 encode and make it URL-safe
        $rawMessage = base64_encode($rawMessageString);
        $rawMessage = str_replace(['+', '/', '='], ['-', '_', ''], $rawMessage);
        $gmailMessage->setRaw($rawMessage);

        $result = $gmail->users_messages->send('me', $gmailMessage);
        return $result;
        */
    }
}
