<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\PricingSetting;
use App\Models\Organization;
use App\Services\PricingResolver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ManagePricing extends Component
{
    use AuthorizesRequests;

    /**
     * What each editable field may hold (TASK-452). Anything not listed here
     * is refused: these values feed invoice math directly, and before this
     * 'abc' stored as 0, '-5' as -5 (a negative wait rate turned wait time
     * into a credit), and any code or field name at all was written through.
     */
    public const RATE_FIELD_RULES = [
        'name' => ['string', 'max:120'],
        'description' => ['string', 'max:500'],
        'rate_per_mile' => ['numeric', 'min:0', 'max:1000'],
        'flat_amount' => ['numeric', 'min:0', 'max:100000'],
        'max_miles' => ['integer', 'min:0', 'max:10000'],
        'max_hours' => ['integer', 'min:0', 'max:168'],
    ];

    public const CHARGE_FIELD_RULES = [
        'name' => ['string', 'max:120'],
        'description' => ['string', 'max:500'],
        'rate_per_hour' => ['numeric', 'min:0', 'max:1000'],
        'rate_per_stop' => ['numeric', 'min:0', 'max:10000'],
        'rate_per_mile' => ['numeric', 'min:0', 'max:1000'],
        'flat_amount' => ['numeric', 'min:0', 'max:100000'],
        'minimum_hours' => ['numeric', 'min:0', 'max:24'],
        'free_miles' => ['integer', 'min:0', 'max:10000'],
    ];

    public const CANCELLATION_FIELD_RULES = [
        'auto_determine' => ['boolean'],
        'hours_before_pickup_for_24hr_charge' => ['integer', 'min:0', 'max:720'],
    ];

    public const PAYMENT_TERMS_FIELD_RULES = [
        'due_immediately' => ['boolean'],
        'grace_period_days' => ['integer', 'min:0', 'max:365'],
        'late_fee_percentage' => ['numeric', 'min:0', 'max:100'],
        'late_fee_period_days' => ['integer', 'min:1', 'max:365'],
        'terms_text' => ['string', 'max:2000'],
    ];

    public $organization;
    public $rates = [];
    public $charges = [];
    public $cancellation = [];
    public $paymentTerms = [];
    public $activeTab = 'rates';

    /** The "add a charge" form on the Charges tab (TASK-377). */
    public $newCharge = [
        'name' => '',
        'description' => '',
        'unit' => 'none',
        'amount' => '',
    ];

    public function mount()
    {
        $user = Auth::user();

        // Super users can manage any organization's pricing
        // Regular admins can only manage their own organization
        if ($user->isSuper() && request()->has('organization_id')) {
            $this->organization = Organization::findOrFail(request('organization_id'));
        } else {
            $this->organization = $user->organization;
        }

        $this->authorize('createJob', $this->organization);

        $this->loadPricingData();
    }

    public function loadPricingData()
    {
        // One resolver, shared with the public /pricing page, so the two can
        // never disagree about what this org's price sheet says (TASK-377).
        $pricing = PricingResolver::all($this->organization->id);

        $this->rates = $pricing['rates'];
        $this->charges = $pricing['charges'];
        $this->cancellation = $pricing['cancellation'];
        $this->paymentTerms = $pricing['payment_terms'];
    }

    public function updateRate($code, $field, $value)
    {
        $this->authorize('createJob', $this->organization);

        $code = (string) $code;
        $field = (string) $field;
        $errorKey = "rates.{$code}.{$field}";

        if (! array_key_exists($code, config('pricing.rates', []))) {
            return $this->refuse($errorKey, __('That rate is not on the price list.'));
        }

        $rateType = config("pricing.rates.{$code}.type");
        $allowed = array_filter(
            array_keys(self::RATE_FIELD_RULES),
            fn ($f) => match ($f) {
                'rate_per_mile' => $rateType === 'per_mile',
                'flat_amount', 'max_miles', 'max_hours' => $rateType === 'flat',
                default => true,
            }
        );

        if (! in_array($field, $allowed, true)) {
            return $this->refuse($errorKey, __('That is not a field of this rate.'));
        }

        if (! $this->passes($errorKey, $field, $value, self::RATE_FIELD_RULES[$field])) {
            return;
        }

        $type = in_array($field, ['rate_per_mile', 'flat_amount', 'max_miles', 'max_hours']) ? 'float' : 'string';

        if ($value === '' || $value === null) {
            // Delete to revert to config default
            PricingSetting::deleteForOrganization($this->organization->id, "rates.{$code}.{$field}");
        } else {
            PricingSetting::setValueForOrganization(
                $this->organization->id,
                "rates.{$code}.{$field}",
                $value,
                $type,
                'rates'
            );
        }

        $this->loadPricingData();
        session()->flash('success', __('Pricing updated successfully.'));
    }

    public function updateCharge($key, $field, $value)
    {
        $this->authorize('createJob', $this->organization);

        $key = (string) $key;
        $field = (string) $field;
        $errorKey = "charges.{$key}.{$field}";

        $isCustom = PricingResolver::isCustomCharge($this->organization->id, $key);

        if (! $isCustom && ! array_key_exists($key, config('pricing.charges', []))) {
            return $this->refuse($errorKey, __('That charge is not on the price list.'));
        }

        // A custom charge stores its unit; a config charge's fields are fixed
        // by the config entry it overrides.
        if ($field === 'unit') {
            if (! $isCustom) {
                return $this->refuse($errorKey, __('The unit of a standard charge cannot be changed.'));
            }

            if (! array_key_exists((string) $value, PricingResolver::CUSTOM_UNITS)) {
                return $this->refuse($errorKey, __('Choose one of the listed units.'));
            }
        } elseif (! array_key_exists($field, self::CHARGE_FIELD_RULES)) {
            return $this->refuse($errorKey, __('That is not a field of this charge.'));
        } elseif (! $isCustom
            && in_array($field, PricingResolver::CHARGE_NUMERIC_FIELDS, true)
            && ! array_key_exists($field, config("pricing.charges.{$key}", []))) {
            return $this->refuse($errorKey, __('That is not a field of this charge.'));
        } elseif (! $this->passes($errorKey, $field, $value, self::CHARGE_FIELD_RULES[$field])) {
            return;
        }

        // A custom charge has no config entry to fall back to, so clearing its
        // name would publish an unnamed card on the public price sheet rather
        // than reverting anything.
        if ($isCustom && $field === 'name' && trim((string) $value) === '') {
            $this->loadPricingData();
            session()->flash('error', __('A charge you added needs a name. Remove it instead if you no longer publish it.'));

            return;
        }

        $settingKey = "charges.{$key}.{$field}";
        $type = in_array($field, PricingResolver::CHARGE_NUMERIC_FIELDS) ? 'float' : 'string';

        if ($value === '' || $value === null) {
            PricingSetting::deleteForOrganization($this->organization->id, $settingKey);
        } else {
            PricingSetting::setValueForOrganization(
                $this->organization->id,
                $settingKey,
                $value,
                $type,
                'charges'
            );
        }

        $this->loadPricingData();
        session()->flash('success', __('Charge updated successfully.'));
    }

    /**
     * Publish a new entry on this org's price sheet (TASK-377).
     */
    public function addCharge()
    {
        $this->authorize('createJob', $this->organization);

        $this->validate([
            'newCharge.name' => ['required', 'string', 'max:120'],
            'newCharge.description' => ['nullable', 'string', 'max:500'],
            'newCharge.unit' => ['required', Rule::in(array_keys(PricingResolver::CUSTOM_UNITS))],
            'newCharge.amount' => [
                Rule::requiredIf(fn () => ($this->newCharge['unit'] ?? 'none') !== 'none'),
                'nullable',
                'numeric',
                'min:0',
                'max:100000',
            ],
        ], [], [
            'newCharge.name' => __('name'),
            'newCharge.description' => __('description'),
            'newCharge.unit' => __('unit'),
            'newCharge.amount' => __('amount'),
        ]);

        PricingResolver::addCustomCharge(
            $this->organization->id,
            trim($this->newCharge['name']),
            $this->newCharge['description'] === null ? null : trim($this->newCharge['description']),
            $this->newCharge['unit'],
            $this->newCharge['amount']
        );

        $this->newCharge = ['name' => '', 'description' => '', 'unit' => 'none', 'amount' => ''];
        $this->activeTab = 'charges';

        $this->loadPricingData();
        session()->flash('success', __('Charge added to your price list.'));
    }

    /**
     * Take an org-added entry back off the price sheet. Config-backed charges
     * are not removable -- invoice math reads two of them by name.
     */
    public function removeCharge($key)
    {
        $this->authorize('createJob', $this->organization);

        $removed = PricingResolver::removeCustomCharge($this->organization->id, $key);

        $this->loadPricingData();

        if ($removed) {
            session()->flash('success', __('Charge removed from your price list.'));
        } else {
            session()->flash('error', __('That charge is part of the standard price list and cannot be removed.'));
        }
    }

    public function updateCancellation($field, $value)
    {
        $this->authorize('createJob', $this->organization);

        $field = (string) $field;
        $errorKey = "cancellation.{$field}";

        if (! array_key_exists($field, self::CANCELLATION_FIELD_RULES)) {
            return $this->refuse($errorKey, __('That is not a cancellation setting.'));
        }

        if (! $this->passes($errorKey, $field, $value, self::CANCELLATION_FIELD_RULES[$field])) {
            return;
        }

        $key = "cancellation.{$field}";
        $type = $field === 'auto_determine' ? 'boolean' : ($field === 'hours_before_pickup_for_24hr_charge' ? 'integer' : 'string');

        if ($value === '' || $value === null) {
            PricingSetting::deleteForOrganization($this->organization->id, $key);
        } else {
            PricingSetting::setValueForOrganization(
                $this->organization->id,
                $key,
                $value,
                $type,
                'cancellation'
            );
        }

        $this->loadPricingData();
        session()->flash('success', __('Cancellation settings updated successfully.'));
    }

    public function updatePaymentTerms($field, $value)
    {
        $this->authorize('createJob', $this->organization);

        $field = (string) $field;
        $errorKey = "payment_terms.{$field}";

        if (! array_key_exists($field, self::PAYMENT_TERMS_FIELD_RULES)) {
            return $this->refuse($errorKey, __('That is not a payment-terms setting.'));
        }

        if (! $this->passes($errorKey, $field, $value, self::PAYMENT_TERMS_FIELD_RULES[$field])) {
            return;
        }

        $key = "payment_terms.{$field}";
        $type = match($field) {
            'due_immediately' => 'boolean',
            'grace_period_days', 'late_fee_period_days' => 'integer',
            'late_fee_percentage' => 'float',
            default => 'string',
        };

        if ($value === '' || $value === null) {
            PricingSetting::deleteForOrganization($this->organization->id, $key);
        } else {
            PricingSetting::setValueForOrganization(
                $this->organization->id,
                $key,
                $value,
                $type,
                'payment_terms'
            );
        }

        $this->loadPricingData();
        session()->flash('success', __('Payment terms updated successfully.'));
    }

    /**
     * Validate one posted value. A blank is always allowed: it means "revert
     * to the default". Anything else has to satisfy the field's rules, and a
     * failure is shown beside the field and as a toast, with the page reloaded
     * so the input shows the value that is actually stored.
     */
    protected function passes(string $errorKey, string $field, $value, array $rules): bool
    {
        $this->resetErrorBag($errorKey);

        if ($value === '' || $value === null) {
            return true;
        }

        $label = __(ucwords(str_replace('_', ' ', $field)));

        $validator = Validator::make(
            ['value' => is_scalar($value) ? $value : null],
            ['value' => ['required', ...$rules]],
            [],
            ['value' => $label]
        );

        if ($validator->passes()) {
            return true;
        }

        $this->refuse($errorKey, $validator->errors()->first('value'));

        return false;
    }

    protected function refuse(string $errorKey, string $message): void
    {
        $this->addError($errorKey, $message);
        $this->loadPricingData();
        session()->flash('error', __('Not saved: :reason', ['reason' => $message]));
    }

    public function render()
    {
        return view('livewire.manage-pricing', [
            'unitOptions' => [
                'none' => __('No rate — information only'),
                'per_hour' => __('Per hour'),
                'per_stop' => __('Per stop'),
                'per_mile' => __('Per mile'),
                'flat' => __('Flat amount'),
            ],
        ]);
    }
}
