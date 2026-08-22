<?php

namespace App\Services;

use App\Models\PricingSetting;
use Illuminate\Support\Str;

/**
 * The single place that turns config/pricing.php plus an organization's
 * PricingSetting overrides into the arrays /my/pricing and the public /pricing
 * page render from.
 *
 * App\Livewire\ManagePricing and App\Http\Controllers\PricingController each
 * carried a near-identical copy of this. TASK-329 had to edit both; TASK-377
 * would have been the third such edit, and a merge that landed in only one copy
 * would publish a charge on the admin page and not the public one.
 *
 * Custom charges (TASK-377) let an org extend its own price sheet without a
 * deploy: `charges.custom` is a json registry row listing the org's own keys,
 * and each of those keys stores its fields as ordinary `charges.{key}.{field}`
 * rows, exactly like a config-backed charge.
 *
 * Custom entries are deliberately CHARGES ONLY, not rates. A rate code drives
 * invoice math through PilotCarJob::$rate_code, and pricing.blade.php drops any
 * rate whose type is neither per_mile nor flat, so a custom rate would be a code
 * an invoice cannot price and that may not even publish. Charges are safer:
 * invoice math reads exactly two of them by name (charges.wait_time.* and
 * charges.extra_stop.*) and treats the rest as price-sheet copy, so a custom
 * charge is published-price content and nothing else until someone wires it into
 * billing on purpose.
 */
class PricingResolver
{
    /** The registry row holding the list of an organization's custom charge keys. */
    public const CUSTOM_CHARGES_KEY = 'charges.custom';

    /**
     * Every numeric field a charge entry can carry, in the order the admin page
     * renders them. Also the write-side type map: anything here is a float,
     * anything else on a charge is a string.
     */
    public const CHARGE_NUMERIC_FIELDS = [
        'rate_per_hour',
        'rate_per_stop',
        'rate_per_mile',
        'flat_amount',
        'minimum_hours',
        'free_miles',
    ];

    /**
     * The unit a custom charge is quoted in, mapped to the field that holds its
     * amount. 'none' is an info-only entry like Tolls -- a named policy on the
     * price sheet with no number attached.
     */
    public const CUSTOM_UNITS = [
        'none' => null,
        'per_hour' => 'rate_per_hour',
        'per_stop' => 'rate_per_stop',
        'per_mile' => 'rate_per_mile',
        'flat' => 'flat_amount',
    ];

    /**
     * Everything both pricing pages need, for an organization or (null) for the
     * bare config defaults.
     */
    public static function all(?int $organizationId): array
    {
        return [
            'rates' => static::rates($organizationId),
            'charges' => static::charges($organizationId),
            'cancellation' => static::cancellation($organizationId),
            'payment_terms' => static::paymentTerms($organizationId),
        ];
    }

    public static function rates(?int $organizationId): array
    {
        $rates = [];

        foreach (config('pricing.rates', []) as $code => $config) {
            $rates[$code] = [
                'name' => static::value($organizationId, "rates.{$code}.name", $config['name'] ?? ''),
                'description' => static::value($organizationId, "rates.{$code}.description", $config['description'] ?? ''),
                'type' => $config['type'] ?? null,
                'rate_per_mile' => static::value($organizationId, "rates.{$code}.rate_per_mile", $config['rate_per_mile'] ?? null),
                'flat_amount' => static::value($organizationId, "rates.{$code}.flat_amount", $config['flat_amount'] ?? null),
                'max_miles' => static::value($organizationId, "rates.{$code}.max_miles", $config['max_miles'] ?? null),
                'max_hours' => static::value($organizationId, "rates.{$code}.max_hours", $config['max_hours'] ?? null),
            ];
        }

        return $rates;
    }

    public static function charges(?int $organizationId): array
    {
        $charges = [];

        foreach (config('pricing.charges', []) as $key => $config) {
            $charges[$key] = static::chargeEntry($organizationId, $key, $config, false);
        }

        foreach (static::customChargeKeys($organizationId) as $key) {
            // A config key of the same name always wins: it is the one invoice
            // math and the seeded price sheet know by name.
            if (isset($charges[$key])) {
                continue;
            }

            $charges[$key] = static::chargeEntry($organizationId, $key, [], true);
        }

        return $charges;
    }

    public static function cancellation(?int $organizationId): array
    {
        $config = config('pricing.cancellation', []);

        return [
            'auto_determine' => static::value($organizationId, 'cancellation.auto_determine', $config['auto_determine'] ?? true),
            'hours_before_pickup_for_24hr_charge' => static::value(
                $organizationId,
                'cancellation.hours_before_pickup_for_24hr_charge',
                $config['hours_before_pickup_for_24hr_charge'] ?? 24
            ),
        ];
    }

    public static function paymentTerms(?int $organizationId): array
    {
        $config = config('pricing.payment_terms', []);

        return [
            'due_immediately' => static::value($organizationId, 'payment_terms.due_immediately', $config['due_immediately'] ?? true),
            'grace_period_days' => static::value($organizationId, 'payment_terms.grace_period_days', $config['grace_period_days'] ?? 30),
            'late_fee_percentage' => static::value($organizationId, 'payment_terms.late_fee_percentage', $config['late_fee_percentage'] ?? 10.0),
            'late_fee_period_days' => static::value($organizationId, 'payment_terms.late_fee_period_days', $config['late_fee_period_days'] ?? 30),
            'terms_text' => static::value($organizationId, 'payment_terms.terms_text', $config['terms_text'] ?? ''),
        ];
    }

    /**
     * The org's own charge keys, in the order they were added.
     */
    public static function customChargeKeys(?int $organizationId): array
    {
        if (! $organizationId) {
            return [];
        }

        $registry = PricingSetting::where('organization_id', $organizationId)
            ->where('setting_key', static::CUSTOM_CHARGES_KEY)
            ->first()?->value;

        if (! is_array($registry)) {
            return [];
        }

        return array_values(array_filter($registry, 'is_string'));
    }

    public static function isCustomCharge(?int $organizationId, string $key): bool
    {
        return in_array($key, static::customChargeKeys($organizationId), true);
    }

    /**
     * Add an entry to the org's price sheet. Returns the generated key.
     */
    public static function addCustomCharge(
        int $organizationId,
        string $name,
        ?string $description = null,
        string $unit = 'none',
        $amount = null
    ): string {
        $unit = array_key_exists($unit, static::CUSTOM_UNITS) ? $unit : 'none';
        $key = static::uniqueChargeKey($organizationId, $name);

        static::put($organizationId, "charges.{$key}.name", $name, 'string');
        static::put($organizationId, "charges.{$key}.unit", $unit, 'string');

        if (filled($description)) {
            static::put($organizationId, "charges.{$key}.description", $description, 'string');
        }

        $amountField = static::CUSTOM_UNITS[$unit];

        if ($amountField !== null && $amount !== null && $amount !== '') {
            static::put($organizationId, "charges.{$key}.{$amountField}", (float) $amount, 'float');
        }

        static::putCustomChargeKeys($organizationId, [...static::customChargeKeys($organizationId), $key]);

        return $key;
    }

    /**
     * Take a custom entry off the price sheet, rows and registry alike. Returns
     * false for anything that is not one of this org's own keys, so a
     * config-backed charge can never be deleted out from under invoice math.
     */
    public static function removeCustomCharge(int $organizationId, string $key): bool
    {
        $keys = static::customChargeKeys($organizationId);

        if (! in_array($key, $keys, true)) {
            return false;
        }

        PricingSetting::deletePrefixForOrganization($organizationId, "charges.{$key}.");
        static::putCustomChargeKeys($organizationId, array_values(array_diff($keys, [$key])));

        return true;
    }

    private static function chargeEntry(?int $organizationId, string $key, array $config, bool $isCustom): array
    {
        // Every field is present on every entry, always. The two loaders this
        // replaced each read a slightly different subset with a mix of guarded
        // and unguarded array access, so an entry missing a key fataled on one
        // page while rendering on the other.
        $entry = [
            'name' => static::value($organizationId, "charges.{$key}.name", $config['name'] ?? ''),
            'description' => static::value($organizationId, "charges.{$key}.description", $config['description'] ?? null),
            'type' => $config['type'] ?? null,
            'is_custom' => $isCustom,
            'unit' => null,
        ];

        foreach (static::CHARGE_NUMERIC_FIELDS as $field) {
            $entry[$field] = static::value($organizationId, "charges.{$key}.{$field}", $config[$field] ?? null);
        }

        if ($isCustom) {
            $unit = (string) static::value($organizationId, "charges.{$key}.unit", 'none');
            $entry['unit'] = array_key_exists($unit, static::CUSTOM_UNITS) ? $unit : 'none';

            // Only the amount matching the current unit is live. Switching a
            // charge from per-hour to flat leaves the old row behind (so
            // switching back restores the number), and without this the price
            // sheet would quote both.
            foreach (static::CUSTOM_UNITS as $unitKey => $amountField) {
                if ($amountField !== null && $unitKey !== $entry['unit']) {
                    $entry[$amountField] = null;
                }
            }
        }

        return $entry;
    }

    /**
     * A key no config charge and no existing custom charge is already using.
     */
    private static function uniqueChargeKey(int $organizationId, string $name): string
    {
        $base = Str::slug($name, '_') ?: 'charge';
        $taken = array_merge(
            array_keys(config('pricing.charges', [])),
            static::customChargeKeys($organizationId)
        );

        $key = $base;
        $suffix = 2;

        while (in_array($key, $taken, true)) {
            $key = $base.'_'.$suffix++;
        }

        return $key;
    }

    private static function putCustomChargeKeys(int $organizationId, array $keys): void
    {
        $keys = array_values(array_unique($keys));

        if ($keys === []) {
            PricingSetting::deleteForOrganization($organizationId, static::CUSTOM_CHARGES_KEY);

            return;
        }

        static::put($organizationId, static::CUSTOM_CHARGES_KEY, $keys, 'json');
    }

    private static function put(int $organizationId, string $key, $value, string $type): void
    {
        PricingSetting::setValueForOrganization($organizationId, $key, $value, $type, 'charges');
    }

    /**
     * An org's override for a key, or the config default. With no organization
     * (the public page before any org exists) there is nothing to override, so
     * the default is the answer.
     */
    private static function value(?int $organizationId, string $key, $default = null)
    {
        return $organizationId
            ? PricingSetting::getValueForOrganization($organizationId, $key, $default)
            : $default;
    }
}
