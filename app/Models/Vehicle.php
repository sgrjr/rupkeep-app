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
        'organization_id',
        'user_id',
        'current_user_id',
        'current_assignment_started_at',
        'current_assignment_notes',
        'is_in_service',
        'deleted_at',
    ];

    protected $casts = [
        'odometer_updated_at' => 'datetime',
        'last_oil_change_at' => 'date',
        'next_oil_change_due_at' => 'date',
        'last_inspection_at' => 'date',
        'next_inspection_due_at' => 'date',
        'current_assignment_started_at' => 'datetime',
        'is_in_service' => 'boolean',
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

    /**
     * Check if oil change is overdue
     */
    public function isOilChangeOverdue(): bool
    {
        if (!$this->next_oil_change_due_at) {
            return false;
        }
        return $this->next_oil_change_due_at->isPast();
    }

    /**
     * Check if oil change is due soon (within 7 days)
     */
    public function isOilChangeDueSoon(): bool
    {
        if (!$this->next_oil_change_due_at) {
            return false;
        }
        return $this->next_oil_change_due_at->isFuture() && 
               $this->next_oil_change_due_at->lte(now()->addDays(7));
    }

    /**
     * Check if inspection is overdue
     */
    public function isInspectionOverdue(): bool
    {
        if (!$this->next_inspection_due_at) {
            return false;
        }
        return $this->next_inspection_due_at->isPast();
    }

    /**
     * Check if inspection is due soon (within 7 days)
     */
    public function isInspectionDueSoon(): bool
    {
        if (!$this->next_inspection_due_at) {
            return false;
        }
        return $this->next_inspection_due_at->isFuture() && 
               $this->next_inspection_due_at->lte(now()->addDays(7));
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
