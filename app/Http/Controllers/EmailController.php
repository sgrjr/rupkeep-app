<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PilotCarJob as Job;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Models\Customer;
use Illuminate\Support\Str;

use Google\Client;
use Google\Service\Gmail;
use Google\Service\Gmail\Message;

class EmailController extends Controller
{
    private $client;

    public function __construct()
    {
        $this->client = new Client();
        $this->client->setClientId(env('GOOGLE_CLIENT_ID'));
        $this->client->setClientSecret(env('GOOGLE_CLIENT_SECRET'));
        $this->client->setRedirectUri(env('GOOGLE_REDIRECT_URI'));
        $this->client->addScope(Gmail::GMAIL_SEND);
    }

    
    public function handleCallback(Request $request)
    {
        if (!$request->has('code')) {
            return redirect('/')->with('error', 'Authorization code not available');
        }

        $token = $this->client->fetchAccessTokenWithAuthCode($request->query('code'));
        $this->client->setAccessToken($token);
        // Store the token in the user's session or database for making authenticated calls later

        $this->sendEmailToMultipleRecipients();
    }

    public function redirectToAuthUrl()
    {
        $authUrl = $this->client->createAuthUrl();
        return redirect($authUrl);
    }

    public function sendEmailToMultipleRecipients()
    {
        $to = ['recipient1@example.com', 'recipient2@example.com'];
        $subject = 'Hello from Gmail API';
        $messageText = 'This is a test email sent to multiple recipients using the Gmail API from a Laravel application.';

        $message = new Message();

        $rawMessageString = "To: " . implode(', ', $to) . "\r\n";
        $rawMessageString .= "Subject: {$subject}\r\n";
        $rawMessageString .= "MIME-Version: 1.0\r\n";
        $rawMessageString .= "Content-Type: text/html; charset=utf-8\r\n";
        $rawMessageString .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $rawMessageString .= "<p>{$messageText}</p>";

        // URL-safe base64 encode the message
        $rawMessage = base64_encode($rawMessageString);
        $rawMessage = str_replace(['+', '/', '='], ['-', '_', ''], $rawMessage); // URL-safe

        $message->setRaw($rawMessage);

        $service = new Gmail($this->client);
        try {
            $service->users_messages->send('me', $message);
            return 'Email sent successfully to multiple recipients.';
        } catch (\Exception $e) {
            return 'An error occurred: ' . $e->getMessage();
        }
    }

    public function sendEmailWithAttachments()
    {
        $subject = 'Subject with Attachments';
        $to = 'recipient@example.com';
        $messageText = 'This is a test email with attachments sent through the Gmail API from a Laravel application.';

        // Construct the MIME message with attachment
        $boundary = uniqid(rand(), true);
        $subjectCharset = $charset = 'utf-8';

        $messageBody = "--{$boundary}\r\n";
        $messageBody .= "Content-Type: text/plain; charset={$charset}\r\n";
        $messageBody .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $messageBody .= "{$messageText}\r\n";

        // Attachments
        $filePath = '/path/to/your/file.pdf'; // Example file path
        $fileName = 'example.pdf'; // Example file name
        $fileData = file_get_contents($filePath);
        $base64File = base64_encode($fileData);

        $messageBody .= "--{$boundary}\r\n";
        $messageBody .= "Content-Type: application/pdf; name={$fileName}\r\n";
        $messageBody .= "Content-Description: {$fileName}\r\n";
        $messageBody .= "Content-Disposition: attachment; filename={$fileName}; size=".filesize($filePath)."\r\n";
        $messageBody .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $messageBody .= "{$base64File}\r\n";
        $messageBody .= "--{$boundary}--";

        $rawMessage = "To: {$to}\r\n";
        $rawMessage .= "Subject: =?{$subjectCharset}?B?" . base64_encode($subject) . "?=\r\n";
        $rawMessage .= "MIME-Version: 1.0\r\n";
        $rawMessage .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n\r\n";
        $rawMessage .= $messageBody;

        $rawMessage = base64_encode($rawMessage);
        $rawMessage = str_replace(['+', '/', '='], ['-', '_', ''], $rawMessage); // URL-safe

        $gmailMessage = new Message();
        $gmailMessage->setRaw($rawMessage);

        $service = new Gmail($this->client);
        try {
            $service->users_messages->send('me', $gmailMessage);
            return 'Email with attachments sent successfully.';
        } catch (\Exception $e) {
            return 'An error occurred: ' . $e->getMessage();
        }
    }

    public function sendHtmlEmail()
    {
        $to = 'recipient@example.com';
        $subject = 'Hello, HTML Email!';
        $htmlContent = '<h1>Welcome to Our Service</h1><p>This is a <strong>HTML</strong> email, sent via the <em>Gmail API</em>.</p>';

        // MIME Type message
        $boundary = uniqid(rand(), true);
        $subjectCharset = $charset = 'utf-8';

        $messageBody = "--{$boundary}\r\n";
        $messageBody .= "Content-Type: text/html; charset={$charset}\r\n";
        $messageBody .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $messageBody .= "{$htmlContent}\r\n";
        $messageBody .= "--{$boundary}--";

        $rawMessage = "To: {$to}\r\n";
        $rawMessage .= "Subject: =?{$subjectCharset}?B?" . base64_encode($subject) . "?=\r\n";
        $rawMessage .= "MIME-Version: 1.0\r\n";
        $rawMessage .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n\r\n";
        $rawMessage .= $messageBody;

        $rawMessage = base64_encode($rawMessage);
        $rawMessage = str_replace(['+', '/', '='], ['-', '_', ''], $rawMessage); // URL-safe

        $gmailMessage = new Message();
        $gmailMessage->setRaw($rawMessage);

        $service = new Gmail($this->client);
        try {
            $service->users_messages->send('me', $gmailMessage);
            return 'HTML email sent successfully.';
        } catch (\Exception $e) {
            return 'An error occurred: ' . $e->getMessage();
        }
    }
}