<?php

namespace App\Actions;

use App\Models\User;
use App\Mail\UserNotification;
use Illuminate\Support\Facades\Mail;

class SendUserNotification
{

    public static function to(User $user, String $message, String $subject = null): void
    {
        Mail::to($user->notification_address)->send(new UserNotification($message, subject: $subject));
    }
}
