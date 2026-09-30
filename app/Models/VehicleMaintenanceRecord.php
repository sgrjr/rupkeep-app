<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VehicleMaintenanceRecord extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const TYPE_OIL_CHANGE = 'oil_change';
    public const TYPE_INSPECTION = 'inspection';
    public const TYPE_REPAIR = 'repair';
    public const TYPE_ASSIGNMENT = 'assignment';
    public const TYPE_OTHER = 'other';

    protected $fillable = [
        'vehicle_id',
        'organization_id',
        'created_by',
        'type',
        'title',
        'performed_at',
        'next_due_at',
        'mileage',
        'cost',
        'notes',
    ];

    protected $casts = [
        'performed_at' => 'date',
        'next_due_at' => 'date',
        'cost' => 'decimal:2',
    ];

    /**
     * Whether records of this type drive the vehicle's last/next dates.
     */
    public function feedsVehicleDates(): bool
    {
        return array_key_exists($this->type, Vehicle::maintenanceDateColumns());
    }

    protected static function booted(): void
    {
        // A saved or removed oil change / inspection updates the vehicle's own
        // last/next dates, which the badges and the reminder digest read
        // (TASK-466).
        $sync = function (self $record): void {
            if ($record->feedsVehicleDates() && $record->vehicle) {
                $record->vehicle->syncMaintenanceDatesFromRecords();
            }
        };

        static::saved($sync);
        static::deleted($sync);
        static::restored($sync);
    }

    public static function types(): array
    {
        return [
            self::TYPE_OIL_CHANGE => 'Oil Change',
            self::TYPE_INSPECTION => 'Inspection',
            self::TYPE_REPAIR => 'Repair',
            self::TYPE_ASSIGNMENT => 'Assignment Update',
            self::TYPE_OTHER => 'Other',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}


