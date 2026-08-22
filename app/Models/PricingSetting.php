<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PricingSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'setting_key',
        'setting_value',
        'setting_type',
        'category',
        'description',
    ];

    protected $casts = [
        'setting_value' => 'string',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the value as the appropriate type
     */
    public function getValueAttribute()
    {
        return match($this->setting_type) {
            'number', 'float' => (float) $this->setting_value,
            'integer', 'int' => (int) $this->setting_value,
            'boolean', 'bool' => filter_var($this->setting_value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($this->setting_value, true),
            default => $this->setting_value,
        };
    }

    /**
     * Set the value with proper type conversion
     */
    public function setValueAttribute($value)
    {
        $this->attributes['setting_value'] = match($this->setting_type) {
            'json' => json_encode($value),
            'boolean', 'bool' => $value ? '1' : '0',
            default => (string) $value,
        };
    }

    /**
     * Get all pricing settings for an organization organized by category
     */
    public static function getByCategoryForOrganization(int $organizationId): array
    {
        return static::where('organization_id', $organizationId)
            ->get()
            ->groupBy('category')
            ->map(function ($items) {
                return $items->keyBy('setting_key');
            })
            ->toArray();
    }

    /**
     * Get a setting value by key for an organization with fallback to config
     */
    public static function getValueForOrganization(int $organizationId, string $key, $default = null)
    {
        $setting = static::where('organization_id', $organizationId)
            ->where('setting_key', $key)
            ->first();
        
        if ($setting) {
            return $setting->value;
        }

        // Fallback to global config file using dot notation
        // Convert 'rates.lead_chase_per_mile.rate_per_mile' to config('pricing.rates.lead_chase_per_mile.rate_per_mile')
        $configKey = 'pricing.' . $key;
        return config($configKey, $default);
    }

    /**
     * Set a pricing setting value for an organization
     */
    public static function setValueForOrganization(
        int $organizationId,
        string $key,
        $value,
        string $type = 'string',
        string $category = 'rates',
        ?string $description = null
    ): self {
        $setting = static::firstOrNew([
            'organization_id' => $organizationId,
            'setting_key' => $key,
        ]);
        $setting->setting_type = $type;
        $setting->category = $category;
        $setting->description = $description;
        $setting->value = $value;
        $setting->save();

        return $setting;
    }

    /**
     * Delete a pricing setting for an organization (revert to config default)
     */
    public static function deleteForOrganization(int $organizationId, string $key): bool
    {
        return static::where('organization_id', $organizationId)
            ->where('setting_key', $key)
            ->delete();
    }

    /**
     * Delete every setting under a dotted prefix, e.g. "charges.my_key." to
     * remove a custom charge and all of its fields (TASK-377).
     *
     * Matched in PHP rather than with a LIKE: setting keys are snake_case and
     * `_` is a single-character wildcard in SQL LIKE, so "charges.my_key.%"
     * would also match "charges.myXkey.". Escaping that portably across MySQL
     * and SQLite is more trouble than filtering a handful of rows.
     */
    public static function deletePrefixForOrganization(int $organizationId, string $prefix): int
    {
        $ids = static::where('organization_id', $organizationId)
            ->pluck('setting_key', 'id')
            ->filter(fn ($key) => str_starts_with((string) $key, $prefix))
            ->keys();

        if ($ids->isEmpty()) {
            return 0;
        }

        return static::whereIn('id', $ids)->delete();
    }
}
