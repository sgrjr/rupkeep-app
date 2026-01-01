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
        //
        // IMPORTANT: SMS Gateway Delivery Notes
        // - Messages sent via Brevo to SMS gateways (e.g., vtext.com, tmomail.net) may show
        //   "452 server temporarily unavailable AUP#MXRT" errors in Brevo's dashboard
        // - This is a TEMPORARY rejection by the carrier's SMS gateway (not your code)
        // - Brevo automatically retries these messages - most deliver successfully after retry
        // - Monitor Brevo dashboard for delivery statistics and bounce reports
        // - See docs/SMS_GATEWAY_TROUBLESHOOTING.md for detailed troubleshooting guidance
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
                        'note' => 'Message accepted by Brevo. Monitor Brevo dashboard for delivery status. Carrier SMS gateways may temporarily reject messages (452 errors), but Brevo will automatically retry.',
                    ]);
                    return; // Success, exit early
                } catch (\Brevo\Client\ApiException $e) {
                    // Brevo-specific API exception - log detailed error
                    $errorBody = $e->getResponseBody();
                    $errorCode = $e->getCode();
                    
                    Log::warning('SendUserNotification: Brevo API failed, falling back to Laravel Mail', [
                        'user_id' => $user->id,
                        'recipient' => $recipient,
                        'error_message' => $e->getMessage(),
                        'error_code' => $errorCode,
                        'error_body' => $errorBody,
                        'api_key_configured' => !empty($api_key),
                        'api_key_length' => $api_key ? strlen($api_key) : 0,
                        'sender_email' => $sender_email,
                    ]);
                    
                    // If the error is "API Key is not enabled", log a helpful message
                    if (str_contains($e->getMessage(), 'API Key is not enabled') || 
                        (is_string($errorBody) && str_contains($errorBody, 'API Key is not enabled'))) {
                        Log::error('SendUserNotification: Brevo API Key is not enabled. Please check your Brevo dashboard:', [
                            'instructions' => [
                                '1. Log into your Brevo account',
                                '2. Go to Settings > API Keys',
                                '3. Ensure you have a "Transactional Email API" key (not just any API key)',
                                '4. Make sure the API key is enabled/active',
                                '5. Verify the API key in your .env file matches the one in Brevo',
                            ],
                        ]);
                    }
                    // Fall through to Laravel Mail fallback below
                } catch (\Exception $e) {
                    Log::warning('SendUserNotification: Brevo failed with unexpected error, falling back to Laravel Mail', [
                        'user_id' => $user->id,
                        'recipient' => $recipient,
                        'error' => $e->getMessage(),
                        'error_class' => get_class($e),
                        'trace' => $e->getTraceAsString(),
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
            $defaultMailer = config('mail.default');
            $mailerConfig = config("mail.mailers.{$defaultMailer}");
            
            // Check if mailer is properly configured
            if ($defaultMailer === 'smtp' && empty(config('mail.mailers.smtp.username'))) {
                Log::error('SendUserNotification: SMTP mailer is configured but missing credentials', [
                    'user_id' => $user->id,
                    'recipient' => $recipient,
                    'mailer' => $defaultMailer,
                    'issue' => 'SMTP username/password not configured. Set MAIL_USERNAME and MAIL_PASSWORD in .env',
                ]);
                throw new \Exception('SMTP mailer is not properly configured. Missing MAIL_USERNAME or MAIL_PASSWORD.');
            }
            
            Mail::to($recipient)->send(new UserNotification($message, $subject));
            
            Log::info('SendUserNotification: Sent successfully via Laravel Mail', [
                'user_id' => $user->id,
                'recipient' => $recipient,
                'subject' => $subject,
                'mailer' => $defaultMailer,
            ]);
        } catch (\Exception $e) {
            // Check if it's a mail transport exception
            $isTransportException = str_contains(get_class($e), 'TransportException') || 
                                   str_contains($e->getMessage(), 'transport') ||
                                   str_contains($e->getMessage(), 'mailer');
            
            Log::error('SendUserNotification: Failed to send via Laravel Mail', [
                'user_id' => $user->id,
                'recipient' => $recipient,
                'mailer' => config('mail.default'),
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'is_transport_error' => $isTransportException,
                'previous_error' => $e->getPrevious() ? $e->getPrevious()->getMessage() : null,
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Final fallback: try using 'log' mailer if default mailer failed
            if (config('mail.default') !== 'log') {
                try {
                    Log::warning('SendUserNotification: Attempting final fallback to log mailer', [
                        'user_id' => $user->id,
                        'recipient' => $recipient,
                    ]);
                    Mail::mailer('log')->to($recipient)->send(new UserNotification($message, $subject));
                    Log::info('SendUserNotification: Notification logged (could not be sent)', [
                        'user_id' => $user->id,
                        'recipient' => $recipient,
                        'subject' => $subject,
                        'note' => 'Check storage/logs/laravel.log for notification details',
                    ]);
                } catch (\Exception $logException) {
                    Log::error('SendUserNotification: Even log mailer failed - notification completely failed', [
                        'user_id' => $user->id,
                        'recipient' => $recipient,
                        'error' => $logException->getMessage(),
                    ]);
                }
            }
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
