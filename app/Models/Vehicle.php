<?php

namespace App\Models;

use App\Models\Organization;
use App\Models\User;
use App\Models\VehicleMaintenanceRecord;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use HasFactory;
    use SoftDeletes;

    public $timestamps = true;

    protected $fillable = [
        'name',
        'odometer',
        'odometer_updated_at',
        'last_service_mileage',
        'last_oil_change_at',
        'next_oil_change_due_at',
        'last_inspection_at',
        'next_inspection_due_at',
        'maintenance_reminder_sent_at',
        'organization_id',
        'user_id',
        'current_user_id',
        'current_assignment_started_at',
        'current_assignment_notes',
        'is_in_service',
        'is_in_garage',
        'deleted_at',
    ];

    protected $casts = [
        'odometer_updated_at' => 'datetime',
        'last_oil_change_at' => 'date',
        'next_oil_change_due_at' => 'date',
        'last_inspection_at' => 'date',
        'next_inspection_due_at' => 'date',
        'maintenance_reminder_sent_at' => 'datetime',
        'current_assignment_started_at' => 'datetime',
        'is_in_service' => 'boolean',
        'is_in_garage' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function currentAssignment(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_user_id');
    }

    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(VehicleMaintenanceRecord::class)->latest('performed_at');
    }

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function getIsAssignedAttribute(): bool
    {
        return (bool) $this->current_user_id;
    }

    /** How far ahead "due soon" looks. */
    public const DUE_SOON_DAYS = 7;

    /**
     * Overdue means the due date has gone by: before today, in the display
     * timezone. Due today is due soon, not overdue (TASK-466); the page lists
     * and the badges now agree on that. Calendar dates are compared, not
     * instants: a `date` cast is midnight UTC while today is Eastern.
     */
    protected static function isOverdue(?\Carbon\CarbonInterface $due): bool
    {
        return $due !== null && $due->toDateString() < \App\Support\LocalTime::today()->toDateString();
    }

    protected static function isDueSoon(?\Carbon\CarbonInterface $due): bool
    {
        if ($due === null || static::isOverdue($due)) {
            return false;
        }

        return $due->toDateString() <= \App\Support\LocalTime::today()->addDays(self::DUE_SOON_DAYS)->toDateString();
    }

    /**
     * Check if oil change is overdue
     */
    public function isOilChangeOverdue(): bool
    {
        return static::isOverdue($this->next_oil_change_due_at);
    }

    /**
     * Check if oil change is due soon (within 7 days)
     */
    public function isOilChangeDueSoon(): bool
    {
        return static::isDueSoon($this->next_oil_change_due_at);
    }

    /**
     * Check if inspection is overdue
     */
    public function isInspectionOverdue(): bool
    {
        return static::isOverdue($this->next_inspection_due_at);
    }

    /**
     * Check if inspection is due soon (within 7 days)
     */
    public function isInspectionDueSoon(): bool
    {
        return static::isDueSoon($this->next_inspection_due_at);
    }

    /**
     * The vehicle columns a maintenance record of each type feeds.
     *
     * @return array<string, array{last: string, next: string}>
     */
    public static function maintenanceDateColumns(): array
    {
        return [
            VehicleMaintenanceRecord::TYPE_OIL_CHANGE => ['last' => 'last_oil_change_at', 'next' => 'next_oil_change_due_at'],
            VehicleMaintenanceRecord::TYPE_INSPECTION => ['last' => 'last_inspection_at', 'next' => 'next_inspection_due_at'],
        ];
    }

    /**
     * The newest performed record of a type: what "last service" means.
     */
    public function latestMaintenanceRecord(string $type): ?VehicleMaintenanceRecord
    {
        return $this->maintenanceRecords()
            ->where('type', $type)
            ->whereNotNull('performed_at')
            ->orderByDesc('performed_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Bring last_*_at / next_*_due_at in line with the maintenance records
     * (TASK-466). Logging an oil change used to change nothing: the badges
     * and the reminder digest read these columns, and only the vehicle form
     * wrote them, so every logged service left the vehicle "overdue" forever.
     *
     * A record without a next-due date sets the last-service date and leaves
     * the vehicle's next-due alone, so a driver logging "done" cannot erase
     * the schedule the office set. With no performed record of a type, the
     * columns stay whatever the form last said.
     */
    public function syncMaintenanceDatesFromRecords(): void
    {
        foreach (static::maintenanceDateColumns() as $type => $columns) {
            $latest = $this->latestMaintenanceRecord($type);

            if (! $latest) {
                continue;
            }

            $this->{$columns['last']} = $latest->performed_at;

            if ($latest->next_due_at) {
                $this->{$columns['next']} = $latest->next_due_at;
            }
        }

        if ($this->isDirty()) {
            $this->save();
        }
    }

    /**
     * Get maintenance status for oil change
     * Returns: 'overdue', 'due_soon', 'ok', or 'not_set'
     */
    public function getOilChangeStatus(): string
    {
        if (!$this->next_oil_change_due_at) {
            return 'not_set';
        }
        if ($this->isOilChangeOverdue()) {
            return 'overdue';
        }
        if ($this->isOilChangeDueSoon()) {
            return 'due_soon';
        }
        return 'ok';
    }

    /**
     * Get maintenance status for inspection
     * Returns: 'overdue', 'due_soon', 'ok', or 'not_set'
     */
    public function getInspectionStatus(): string
    {
        if (!$this->next_inspection_due_at) {
            return 'not_set';
        }
        if ($this->isInspectionOverdue()) {
            return 'overdue';
        }
        if ($this->isInspectionDueSoon()) {
            return 'due_soon';
        }
        return 'ok';
    }

    public static function positionOptions()
    {
        return [
            ['name' => '(none selected)', 'value'=>null],
            ['name' => 'Lead', 'value'=>'lead'],
            ['name' => 'Chase', 'value'=>'chase'],
            ['name' => 'Mixed', 'value'=>'mixed'],
        ];
    }
}
