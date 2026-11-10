<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject }}</title>
</head>
{{-- Customer portal welcome (TASK-506). Same layout as mail/login-link, in the
     organization's name rather than the platform's. --}}
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f3f4f6;">
    <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #f3f4f6;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table role="presentation" style="max-width: 600px; width: 100%; border-collapse: collapse; background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                    <tr>
                        <td style="background: linear-gradient(135deg, #f9b104 0%, #ff8c00 100%); padding: 30px; text-align: center; border-radius: 12px 12px 0 0;">
                            <p style="margin: 0 0 6px 0; color: #ffffff; font-size: 13px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; opacity: 0.85;">{{ $brand }}</p>
                            <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: 700;">{{ $created ? __('Your customer portal is ready') : __('Signing in to your customer portal') }}</h1>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 30px;">
                            <p style="margin: 0 0 20px 0; color: #374151; font-size: 16px; line-height: 1.6;">
                                {{ __('Hello :name,', ['name' => $user->name]) }}
                            </p>

                            <p style="margin: 0 0 20px 0; color: #374151; font-size: 16px; line-height: 1.6;">
                                @if($created)
                                    {{ __(':brand has set up an account for you to see your invoices and their payment status online.', ['brand' => $brand]) }}
                                @else
                                    {{ __('Here is a reminder of how to see the invoices :brand has sent you.', ['brand' => $brand]) }}
                                @endif
                            </p>

                            <p style="margin: 0 0 20px 0; color: #374151; font-size: 16px; line-height: 1.6;">
                                {{ __('There is no password. Open the sign-in page, enter your email address, and we will email you a link that signs you in with one click.') }}
                            </p>

                            <table role="presentation" style="width: 100%; margin: 30px 0; border-collapse: collapse;">
                                <tr>
                                    <td align="center" style="padding: 0;">
                                        <a href="{{ $loginUrl }}" style="display: inline-block; background-color: #f9b104; color: #ffffff; text-decoration: none; padding: 14px 28px; border-radius: 8px; font-weight: 600; font-size: 16px; box-shadow: 0 2px 4px rgba(249, 177, 4, 0.3);">
                                            {{ __('Go to the sign-in page') }}
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <div style="background-color: #f9fafb; border-left: 4px solid #f9b104; padding: 16px 20px; margin: 20px 0; border-radius: 4px;">
                                <p style="margin: 0 0 6px 0; color: #6b7280; font-size: 12px; font-weight: 600; text-transform: uppercase;">{{ __('Sign in with') }}</p>
                                <p style="margin: 0; color: #111827; font-size: 16px; font-weight: 600;">{{ $user->email }}</p>
                            </div>

                            <p style="margin: 0; color: #6b7280; font-size: 14px; line-height: 1.6;">
                                {{ __('If the button does not work, paste this address into your browser:') }}<br>
                                <a href="{{ $loginUrl }}" style="color: #b45309; font-size: 13px; word-break: break-all;">{{ $loginUrl }}</a>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 20px 30px; background-color: #f9fafb; border-top: 1px solid #e5e7eb; border-radius: 0 0 12px 12px; text-align: center;">
                            <p style="margin: 0; color: #6b7280; font-size: 12px;">
                                {{ __('Questions about an invoice? Reply to :brand directly.', ['brand' => $brand]) }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
