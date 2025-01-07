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
        Mail::to($user->notification_address)->send(new UserNotification($message, subject: $subject));

        /*$client = new Client();
        $client->setClientId(env('GOOGLE_CLIENT_ID'));
        $client->setClientSecret(env('GOOGLE_CLIENT_SECRET'));
        $client->setRedirectUri(env('GOOGLE_REDIRECT_URI'));
        $client->setAccessToken(auth()->user()->createToken('mail-login')->accessToken->token); // Ensure this method retrieves a valid token

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
