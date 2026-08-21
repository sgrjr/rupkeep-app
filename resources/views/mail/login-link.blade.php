<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject }}</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f3f4f6;">
    <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #f3f4f6;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table role="presentation" style="max-width: 600px; width: 100%; border-collapse: collapse; background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #f9b104 0%, #ff8c00 100%); padding: 30px; text-align: center; border-radius: 12px 12px 0 0;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: 700;">{{ __('Sign in to Rupkeep') }}</h1>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 30px;">
                            <p style="margin: 0 0 20px 0; color: #374151; font-size: 16px; line-height: 1.6;">
                                {{ __('Hello :name,', ['name' => $user->name]) }}
                            </p>

                            <p style="margin: 0 0 20px 0; color: #374151; font-size: 16px; line-height: 1.6;">
                                {{ __('Click the button below to sign in. No password needed.') }}
                            </p>

                            <!-- CTA Button -->
                            <table role="presentation" style="width: 100%; margin: 30px 0; border-collapse: collapse;">
                                <tr>
                                    <td align="center" style="padding: 0;">
                                        <a href="{{ $url }}" style="display: inline-block; background-color: #f9b104; color: #ffffff; text-decoration: none; padding: 14px 28px; border-radius: 8px; font-weight: 600; font-size: 16px; box-shadow: 0 2px 4px rgba(249, 177, 4, 0.3);">
                                            {{ __('Sign in to Rupkeep') }}
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 0 0 20px 0; color: #6b7280; font-size: 14px; line-height: 1.6;">
                                {{ __('If the button does not work, paste this address into your browser:') }}<br>
                                <a href="{{ $url }}" style="color: #b45309; font-size: 13px; word-break: break-all;">{{ $url }}</a>
                            </p>

                            <!-- Typed-code fallback -->
                            <div style="background-color: #f9fafb; border-left: 4px solid #f9b104; padding: 20px; margin: 20px 0; border-radius: 4px;">
                                <p style="margin: 0 0 8px 0; color: #6b7280; font-size: 12px; font-weight: 600; text-transform: uppercase;">
                                    {{ __('Or enter this code') }}
                                </p>
                                <p style="margin: 0 0 12px 0; color: #111827; font-size: 28px; font-weight: 700; letter-spacing: 4px;">
                                    {{ $code }}
                                </p>
                                <p style="margin: 0; color: #374151; font-size: 14px; line-height: 1.6;">
                                    {{ __('Enter it at') }}
                                    <a href="{{ $verifyUrl }}" style="color: #b45309;">{{ $verifyUrl }}</a>
                                </p>
                            </div>

                            <p style="margin: 20px 0 0 0; color: #374151; font-size: 16px; line-height: 1.6;">
                                {{ $expirySentence }} {{ __('It can only be used once.') }}
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding: 20px 30px; background-color: #f9fafb; border-top: 1px solid #e5e7eb; border-radius: 0 0 12px 12px; text-align: center;">
                            <p style="margin: 0; color: #6b7280; font-size: 12px;">
                                {{ __('If you did not request this, you can safely ignore this email — no one can sign in without the link or code above.') }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
