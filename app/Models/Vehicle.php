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
