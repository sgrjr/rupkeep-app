<?php

namespace App\Actions;

use App\Models\User;
use App\Mail\UserNotification;
use Illuminate\Support\Facades\Mail;
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

        $api_key = config('mail.mailers.brevo.key');
        $sender_email = config('mail.mailers.brevo.sender.email');
        $sender_name = config('mail.mailers.brevo.sender.name');

       // Configure API key authorization: api-key
        $config = \Brevo\Client\Configuration::getDefaultConfiguration()->setApiKey('api-key', $api_key );
        // Uncomment below to setup prefix (e.g. Bearer) for API key, if needed
        // $config = Brevo\Client\Configuration::getDefaultConfiguration()->setApiKeyPrefix('api-key', 'Bearer');
        // Configure API key authorization: partner-key
        $config = \Brevo\Client\Configuration::getDefaultConfiguration()->setApiKey('partner-key', $api_key);
        // Uncomment below to setup prefix (e.g. Bearer) for API key, if needed
        // $config = Brevo\Client\Configuration::getDefaultConfiguration()->setApiKeyPrefix('partner-key', 'Bearer');

        $apiInstance = new \Brevo\Client\Api\TransactionalEmailsApi(
            // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
            // This is optional, `GuzzleHttp\Client` will be used as default.
            new \GuzzleHttp\Client(),
            $config
        );

        $sendSmtpEmail = new \Brevo\Client\Model\SendSmtpEmail([
            'subject' => $subject,
            'sender' => ['name' => $sender_name, 'email' => $sender_email],
            //'replyTo' => ['name' => 'Brevo', 'email' => 'contact@brevo.com'],
            'to' => [[ 'name' => $user->name, 'email' => $user->notification_address]],
            'textContent' => $message,
            'params' => ['bodyMessage' => 'made just for you!']
        ]); // \Brevo\Client\Model\SendSmtpEmail | Values to send a transactional email

        try {
            $result = $apiInstance->sendTransacEmail($sendSmtpEmail);
            //dd($result);
        } catch (\Exception $e) {
            dd('Exception when calling TransactionalEmailsApi->sendTransacEmail: ', $e->getMessage(), PHP_EOL);
        }

        //Mail::to($user->notification_address)->send(new UserNotification($message, subject: $subject));
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
