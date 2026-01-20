<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Organization;
use App\Models\Customer;

/**
 * Pivot model for linking summary invoices to multiple jobs.
 * 
 * This model is ONLY used for summary invoices that need to link to multiple jobs.
 * Single invoices use the pilot_car_job_id foreign key directly on the invoices table.
 * 
 * @property int $invoice_id
 * @property int $pilot_car_job_id
 */
class JobInvoice extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'invoice_id',
        'pilot_car_job_id'
    ];

    public $timestamps = false;

    public $table = "summary_invoice_jobs";

    public function invoice(){
        return $this->belongsTo(Invoice::class);
    }

    public function job(){
        return $this->belongsTo(PilotCarJob::class);
    }
}
