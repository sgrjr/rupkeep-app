<?php

namespace App\Support;

/**
 * A notification address is either a mailbox or a carrier email-to-SMS
 * gateway address (2075551234@vtext.com). People type the phone part the
 * way they write phone numbers -- "(207) 416-8659@mms.uscc.net" -- and that
 * used to be stored and sent as typed, which no carrier accepts (TASK-468).
 */
class NotificationAddress
{
    /**
     * Trim, lower-case the domain, and reduce a phone-shaped local part to
     * its digits (dropping a leading US country code). A mailbox is left as
     * typed apart from trimming.
     */
    public static function normalize(?string $address): ?string
    {
        $address = trim((string) $address);

        if ($address === '') {
            return null;
        }

        $at = strrpos($address, '@');

        if ($at === false) {
            return $address;
        }

        $local = substr($address, 0, $at);
        $domain = strtolower(trim(substr($address, $at + 1)));

        $digits = preg_replace('/\D/', '', $local);
        $looksLikePhone = $digits !== '' && preg_match('/^[\d\s().+-]+$/', $local) === 1;

        if ($looksLikePhone) {
            if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
                $digits = substr($digits, 1);
            }

            $local = $digits;
        } else {
            $local = trim($local);
        }

        return $local.'@'.$domain;
    }

    /**
     * Whether the normalised address can be delivered to: a syntactically
     * valid mailbox, and for a gateway domain a 10-digit phone number.
     */
    public static function isValid(?string $address): bool
    {
        $normalized = static::normalize($address);

        if ($normalized === null) {
            return false;
        }

        if (filter_var($normalized, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        if (static::isGateway($normalized)) {
            return preg_match('/^\d{10}@/', $normalized) === 1;
        }

        return true;
    }

    /**
     * Whether the address's domain is one of the configured carrier gateways.
     */
    public static function isGateway(?string $address): bool
    {
        $address = (string) $address;
        $at = strrchr($address, '@');

        if ($at === false) {
            return false;
        }

        $domain = strtolower(substr($at, 1));

        foreach (config('sms_gateways.providers', []) as $provider) {
            foreach ([$provider['sms'] ?? null, $provider['mms'] ?? null] as $suffix) {
                $suffix = strtolower(ltrim((string) $suffix, '@'));

                if ($suffix !== '' && str_ends_with($domain, $suffix)) {
                    return true;
                }
            }
        }

        return false;
    }
}
