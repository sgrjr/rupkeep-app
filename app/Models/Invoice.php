<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Organization;
use App\Models\Customer;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'paid_in_full',
        'values',
        'organization_id',
        'customer_id'
    ];

    public $timestamps = true;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'paid_in_full' => 'boolean',
            'values' => 'array',
        ];
    }

    public function organization(){
        return $this->belongsTo(Organization::class);
    }

    public function customer(){
        return $this->belongsTo(Customer::class);
    }

    public function jobs(){
        return $this->belongsToMany(PilotCarJob::class, 'jobs_invoices');
    }
}
